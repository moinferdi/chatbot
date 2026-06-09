<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Middleware;

use Moinferdi\Chatbot\Dto\ChatRequest;
use Moinferdi\Chatbot\Service\ChatClient;
use Moinferdi\Chatbot\Service\ConfigurationResolver;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class ChatProxyMiddleware implements MiddlewareInterface
{
    private const string ENDPOINT_PATH = '/chatbot/api/chat';

    public function __construct(
        private readonly ConfigurationResolver $configResolver,
        private readonly ChatClient $chatClient,
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
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
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

        set_time_limit(30);

        try {
            $reply = $this->chatClient->complete($chatRequest, $config);
        } catch (\RuntimeException $e) {
            $this->logger->error('Chatbot proxy upstream failure', ['error' => $e->getMessage()]);
            return $this->errorResponse(502, 'Upstream chat service unavailable.');
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

    private function errorResponse(int $status, string $message): ResponseInterface
    {
        $body = json_encode(['error' => $message], JSON_THROW_ON_ERROR);

        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');

        $response->getBody()->write($body);

        return $response;
    }
}
