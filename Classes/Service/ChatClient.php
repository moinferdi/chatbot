<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Service;

use Moinferdi\Chatbot\Dto\ChatRequest;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

final class ChatClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function complete(ChatRequest $chatRequest, ChatbotConfig $config): string
    {
        $url = rtrim($config->baseUrl, '/') . '/api/chat/completions';

        $payload = [
            'model' => $config->model,
            'messages' => $chatRequest->messages,
            'stream' => false,
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $body);
        rewind($stream);

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Authorization', 'Bearer ' . $config->apiKey)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        // Write body to request
        $request->getBody()->write($body);

        try {
            $response = $this->httpClient->sendRequest($request);
            $responseBody = (string) $response->getBody();

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Chatbot upstream error', [
                    'status' => $response->getStatusCode(),
                    'body' => mb_substr($responseBody, 0, 500),
                ]);
                throw new \RuntimeException('Upstream chat error (HTTP ' . $response->getStatusCode() . ').');
            }

            $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new \RuntimeException('Invalid upstream JSON response.');
            }

            $content = $data['choices'][0]['message']['content'] ?? '';
            if ($content === '' && isset($data['message']['content'])) {
                $content = $data['message']['content'];
            }

            if ($content === '') {
                $this->logger->warning('Chatbot received empty reply', ['data' => $data]);
            }

            return (string) $content;

        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Chatbot HTTP client failure', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Failed to reach chat backend.', 0, $e);
        }
    }
}
