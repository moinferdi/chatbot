<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Service;

use Moinferdi\Chatbot\Dto\ChatRequest;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

final class ChatClient
{
    public function __construct(
        private readonly GuzzleClient $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Resolve the chat-completions endpoint from the configured base URL.
     *
     * - If the base already points at a completions endpoint, it is used
     *   verbatim — so any OpenAI-compatible provider works, e.g. OpenRouter:
     *   https://openrouter.ai/api/v1/chat/completions
     * - Otherwise an OpenWebUI host is assumed and its native path is appended
     *   (keeps existing configs working): {base}/api/chat/completions
     */
    private function endpointUrl(ChatbotConfig $config): string
    {
        $base = rtrim($config->baseUrl, '/');
        if (str_contains($base, '/chat/completions')) {
            return $base;
        }
        return $base . '/api/chat/completions';
    }

    /**
     * Extract a human-readable error message from an upstream error body,
     * tolerating the different shapes providers use:
     *  - OpenAI / OpenRouter: {"error": {"message": "...", "code": "..."}}
     *  - OpenWebUI / FastAPI: {"detail": "..."}
     *  - others:              {"error": "..."} or {"message": "..."}
     */
    private function extractErrorDetail(string $rawBody): string
    {
        try {
            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return mb_substr($rawBody, 0, 200);
        }

        if (!is_array($data)) {
            return mb_substr($rawBody, 0, 200);
        }

        // OpenAI / OpenRouter: error is a nested object.
        if (isset($data['error']) && is_array($data['error'])) {
            $message = $data['error']['message'] ?? $data['error']['code'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        // Flat string variants.
        foreach (['detail', 'error', 'message'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return mb_substr($rawBody, 0, 200);
    }

    /**
     * @throws UpstreamException when the upstream API returns an error (4xx/5xx)
     * @throws \RuntimeException when the upstream is unreachable
     */
    public function complete(ChatRequest $chatRequest, ChatbotConfig $config): string
    {
        $url = $this->endpointUrl($config);

        $payload = [
            'model' => $config->model,
            'messages' => $chatRequest->messages,
            'stream' => false,
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Authorization', 'Bearer ' . $config->apiKey)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->send($request);
            $responseBody = (string) $response->getBody();

            if ($response->getStatusCode() !== 200) {
                $detail = $this->extractErrorDetail($responseBody);

                $this->logger->error('Chatbot upstream error', [
                    'status' => $response->getStatusCode(),
                    'detail' => $detail,
                ]);

                throw new UpstreamException(
                    $response->getStatusCode(),
                    $detail ?: 'Unknown upstream error',
                );
            }

            $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new UpstreamException(502, 'Invalid upstream JSON response.');
            }

            $content = $data['choices'][0]['message']['content'] ?? '';
            if ($content === '' && isset($data['message']['content'])) {
                $content = $data['message']['content'];
            }

            if ($content === '') {
                $this->logger->warning('Chatbot received empty reply', ['data' => $data]);
            }

            return (string) $content;

        } catch (UpstreamException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Chatbot HTTP client failure', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Failed to reach chat backend.', 0, $e);
        }
    }

    /**
     * Stream completions from the upstream, yielding raw SSE bytes for passthrough.
     *
     * The upstream (OpenWebUI / OpenAI-compatible) emits a `text/event-stream`
     * body; we forward its bytes verbatim so the browser's SSE parser sees the
     * original framing (including the terminating `data: [DONE]`).
     *
     * @return \Generator<string>
     * @throws UpstreamException when the upstream rejects the request (non-200)
     * @throws \RuntimeException when the upstream is unreachable
     */
    public function completeStream(ChatRequest $chatRequest, ChatbotConfig $config): \Generator
    {
        $url = $this->endpointUrl($config);

        $payload = [
            'model' => $config->model,
            'messages' => $chatRequest->messages,
            'stream' => true,
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Authorization', 'Bearer ' . $config->apiKey)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'text/event-stream')
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->send($request, [
                // Stream the body instead of buffering it.
                'stream' => true,
                // No total cap: a long generation must not be cut off mid-stream.
                'timeout' => 0,
                // Instead, abort only if the upstream stalls (no new bytes) for 30s.
                'read_timeout' => 30,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Chatbot stream failure', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Stream connection to chat backend lost.', 0, $e);
        }

        $responseBody = $response->getBody();

        if ($response->getStatusCode() !== 200) {
            $detail = $this->extractErrorDetail((string) $responseBody);

            $this->logger->error('Chatbot upstream stream error', [
                'status' => $response->getStatusCode(),
                'detail' => $detail,
            ]);

            throw new UpstreamException(
                $response->getStatusCode(),
                $detail ?: 'Unknown upstream error',
            );
        }

        while (!$responseBody->eof()) {
            $chunk = $responseBody->read(8192);
            if ($chunk === '') {
                break;
            }
            yield $chunk;
        }
    }
}
