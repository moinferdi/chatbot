<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Service;

use Moinferdi\Chatbot\Dto\ChatRequest;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

final class ChatClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws UpstreamException when the upstream API returns an error (4xx/5xx)
     * @throws \RuntimeException when the upstream is unreachable
     */
    public function complete(ChatRequest $chatRequest, ChatbotConfig $config): string
    {
        $url = rtrim($config->baseUrl, '/') . '/api/chat/completions';

        $payload = [
            'model' => $config->model,
            'messages' => $chatRequest->messages,
            'stream' => false,
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        // Build the request with a proper writable body stream
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Authorization', 'Bearer ' . $config->apiKey)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        // Ensure body is properly set using php://temp stream
        $bodyStream = fopen('php://temp', 'r+');
        if ($bodyStream === false) {
            throw new \RuntimeException('Failed to create request body stream.');
        }
        fwrite($bodyStream, $body);
        rewind($bodyStream);

        // Create a proper PSR-7 stream and attach it
        $request = $request->withBody($this->createStreamFromResource($bodyStream));

        try {
            $response = $this->httpClient->sendRequest($request);
            $responseBody = (string) $response->getBody();

            if ($response->getStatusCode() !== 200) {
                // Try to extract upstream error detail
                $detail = '';
                try {
                    $errData = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
                    $detail = $errData['detail'] ?? $errData['error'] ?? $errData['message'] ?? '';
                } catch (\JsonException) {
                    $detail = mb_substr($responseBody, 0, 200);
                }

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
     * Wrap a PHP stream resource in a PSR-7 StreamInterface.
     */
    private function createStreamFromResource($resource): \Psr\Http\Message\StreamInterface
    {
        return new class($resource) implements \Psr\Http\Message\StreamInterface {
            /** @var resource|null */
            private $stream;

            /** @param resource $stream */
            public function __construct($stream)
            {
                $this->stream = $stream;
            }

            public function __toString(): string
            {
                if ($this->stream === null) {
                    return '';
                }
                try {
                    $this->rewind();
                    return (string) stream_get_contents($this->stream);
                } catch (\Throwable) {
                    return '';
                }
            }

            public function close(): void
            {
                if (is_resource($this->stream)) {
                    fclose($this->stream);
                }
                $this->stream = null;
            }

            public function detach()
            {
                $r = $this->stream;
                $this->stream = null;
                return $r;
            }

            public function getSize(): ?int
            {
                if ($this->stream === null) {
                    return null;
                }
                $stat = fstat($this->stream);
                return $stat['size'] ?? null;
            }

            public function tell(): int
            {
                if ($this->stream === null) {
                    throw new \RuntimeException('Stream is detached.');
                }
                $pos = ftell($this->stream);
                if ($pos === false) {
                    throw new \RuntimeException('Cannot determine stream position.');
                }
                return $pos;
            }

            public function eof(): bool
            {
                return $this->stream === null || feof($this->stream);
            }

            public function isSeekable(): bool
            {
                return $this->stream !== null;
            }

            public function seek($offset, $whence = SEEK_SET): void
            {
                if ($this->stream === null) {
                    throw new \RuntimeException('Stream is detached.');
                }
                fseek($this->stream, $offset, $whence);
            }

            public function rewind(): void
            {
                if ($this->stream !== null) {
                    fseek($this->stream, 0);
                }
            }

            public function isWritable(): bool
            {
                return $this->stream !== null;
            }

            public function write($string): int
            {
                if ($this->stream === null) {
                    throw new \RuntimeException('Stream is detached.');
                }
                $bytes = fwrite($this->stream, $string);
                if ($bytes === false) {
                    throw new \RuntimeException('Failed to write to stream.');
                }
                return $bytes;
            }

            public function isReadable(): bool
            {
                return $this->stream !== null;
            }

            public function read($length): string
            {
                if ($this->stream === null) {
                    throw new \RuntimeException('Stream is detached.');
                }
                $data = fread($this->stream, $length);
                if ($data === false) {
                    throw new \RuntimeException('Failed to read from stream.');
                }
                return $data;
            }

            public function getContents(): string
            {
                if ($this->stream === null) {
                    throw new \RuntimeException('Stream is detached.');
                }
                $data = stream_get_contents($this->stream);
                if ($data === false) {
                    throw new \RuntimeException('Failed to get stream contents.');
                }
                return $data;
            }

            public function getMetadata($key = null): mixed
            {
                if ($this->stream === null) {
                    return $key !== null ? null : [];
                }
                $meta = stream_get_meta_data($this->stream);
                return $key !== null ? ($meta[$key] ?? null) : $meta;
            }

            public function __destruct()
            {
                $this->close();
            }
        };
    }
}
