<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Middleware;

use Moinferdi\Chatbot\Dto\ChatRequest;
use Moinferdi\Chatbot\Http\SseStream;
use Moinferdi\Chatbot\Service\ChatClient;
use Moinferdi\Chatbot\Service\ConfigurationResolver;
use Moinferdi\Chatbot\Service\ChatbotConfig;
use Moinferdi\Chatbot\Service\RateLimiter;
use Moinferdi\Chatbot\Service\UpstreamException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ChatProxyMiddleware implements MiddlewareInterface
{
    private const string ENDPOINT_PATH = '/chatbot/api/chat';

    public function __construct(
        private readonly ConfigurationResolver $configResolver,
        private readonly ChatClient $chatClient,
        private readonly RateLimiter $rateLimiter,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== self::ENDPOINT_PATH) {
            return $handler->handle($request);
        }

        if ($request->getMethod() !== 'POST') {
            return $this->errorResponse(405, 'Method Not Allowed');
        }

        if (!$this->isSameOrigin($request)) {
            $this->logger->warning('Chatbot: cross-origin request blocked', [
                'origin' => $request->getHeaderLine('Origin'),
                'ip' => $this->clientIp(),
            ]);
            return $this->errorResponse(403, 'Forbidden');
        }

        $rawBody = (string) $request->getBody();
        $data = json_decode($rawBody, true);

        if (!is_array($data)) {
            return $this->errorResponse(400, 'Invalid JSON body.');
        }

        try {
            $chatRequest = ChatRequest::fromArray($data);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(400, $e->getMessage());
        }

        $config = $this->configResolver->resolve($request);
        if (!$config->isValid()) {
            return $this->errorResponse(503, 'Chatbot not configured or disabled.');
        }

        if (!str_starts_with(strtolower($config->baseUrl), 'https://')) {
            $this->logger->warning('Chatbot: non-HTTPS upstream blocked');
            return $this->errorResponse(502, 'Chatbot upstream must use HTTPS.');
        }

        // Rate limiting
        $clientIp = $this->clientIp();
        if (!$this->rateLimiter->attempt($clientIp)) {
            $this->logger->warning('Chatbot: rate limit exceeded', ['ip' => $clientIp]);
            return $this->errorResponse(429, 'Too Many Requests', [
                'Retry-After' => (string) $this->rateLimiter->retryAfter($clientIp),
            ]);
        }

        if (!empty($data['stream'])) {
            return $this->handleStream($chatRequest, $config);
        }

        return $this->handleNonStream($chatRequest, $config);
    }

    private function clientIp(): string
    {
        $ip = GeneralUtility::getIndpEnv('REMOTE_ADDR');
        return is_string($ip) && $ip !== '' ? $ip : 'unknown';
    }

    private function handleNonStream(ChatRequest $chatRequest, ChatbotConfig $config): ResponseInterface
    {
        try {
            $reply = $this->chatClient->complete($chatRequest, $config);
        } catch (UpstreamException $e) {
            $this->logger->error('Chatbot upstream API error', [
                'httpStatus' => $e->httpStatus,
                'detail' => $e->getMessage(),
            ]);
            return $this->errorResponse(502, 'Bad Gateway');
        } catch (\RuntimeException $e) {
            $this->logger->error('Chatbot proxy connection failure', ['error' => $e->getMessage()]);
            return $this->errorResponse(502, 'Bad Gateway');
        }

        $responseBody = json_encode([
            'content' => $reply,
            'role' => 'assistant',
        ], JSON_THROW_ON_ERROR);

        $response = $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');

        $response->getBody()->write($responseBody);

        return $response;
    }

    private function handleStream(ChatRequest $chatRequest, ChatbotConfig $config): ResponseInterface
    {
        // The body is a self-emitting stream: TYPO3's emitter calls emit() on it,
        // which flushes each chunk as the upstream produces it (real SSE streaming).
        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Connection', 'keep-alive')
            // Disable nginx proxy buffering so chunks reach the client immediately.
            ->withHeader('X-Accel-Buffering', 'no')
            ->withBody(new SseStream($this->streamFrames($chatRequest, $config)));
    }

    /**
     * Forwards upstream SSE bytes verbatim, translating failures into an SSE
     * error event (the 200 headers are already on the wire by the time the
     * generator runs, so errors cannot be signalled via status code).
     *
     * @return \Generator<string>
     */
    private function streamFrames(ChatRequest $chatRequest, ChatbotConfig $config): \Generator
    {
        try {
            yield from $this->chatClient->completeStream($chatRequest, $config);
        } catch (UpstreamException $e) {
            $this->logger->error('Chatbot upstream stream error', [
                'httpStatus' => $e->httpStatus,
                'detail' => $e->getMessage(),
            ]);
            yield "event: error\ndata: " . json_encode(['error' => 'Bad Gateway'], JSON_THROW_ON_ERROR) . "\n\n";
        } catch (\Throwable $e) {
            // Once the 200/text-event-stream headers are out, the only way to
            // signal failure is an SSE error frame — never let it bubble into a
            // 500 page mid-stream.
            $this->logger->error('Chatbot stream failure', ['error' => $e->getMessage()]);
            yield "event: error\ndata: " . json_encode(['error' => 'Bad Gateway'], JSON_THROW_ON_ERROR) . "\n\n";
        }
    }

    private function isSameOrigin(ServerRequestInterface $request): bool
    {
        $secFetchSite = $request->getHeaderLine('Sec-Fetch-Site');
        if ($secFetchSite === 'same-origin') {
            return true;
        }

        $ownHost = $request->getUri()->getHost();
        $ownScheme = $request->getUri()->getScheme();

        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '') {
            $parts = parse_url($origin);
            return ($parts['host'] ?? '') === $ownHost && ($parts['scheme'] ?? '') === $ownScheme;
        }

        $referer = $request->getHeaderLine('Referer');
        if ($referer !== '') {
            $parts = parse_url($referer);
            return ($parts['host'] ?? '') === $ownHost && ($parts['scheme'] ?? '') === $ownScheme;
        }

        return false;
    }

    /**
     * @param array<string, string> $headers
     */
    private function errorResponse(int $status, string $message, array $headers = []): ResponseInterface
    {
        $body = json_encode(['error' => $message], JSON_THROW_ON_ERROR);

        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response->getBody()->write($body);

        return $response;
    }
}
