<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Tests\Unit\Service;

use Moinferdi\Chatbot\Dto\ChatRequest;
use Moinferdi\Chatbot\Service\ChatClient;
use Moinferdi\Chatbot\Service\ChatbotConfig;
use Moinferdi\Chatbot\Service\UpstreamException;
use GuzzleHttp\Client as GuzzleClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

final class ChatClientTest extends TestCase
{
    /** @test */
    public function sendsRequestAndReturnsContent(): void
    {
        $httpClient = $this->createMock(GuzzleClient::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $bodyStream = $this->createMock(StreamInterface::class);

        $config = ChatbotConfig::enabled(
            baseUrl: 'https://api.example.com',
            apiKey: 'sk-test',
            model: 'gpt-4o',
            everywhere: false,
            colorPrimary: '#4F9EF7',
            colorText: '#ffffff',
            colorUserText: '#1a1a1a',
            position: 'bottom-right',
            startMessage: null,
        );

        $chatRequest = ChatRequest::fromArray([
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        $streamFactory->method('createStream')
            ->willReturn($bodyStream);

        $requestFactory->method('createRequest')
            ->with('POST', 'https://api.example.com/api/chat/completions')
            ->willReturn($request);

        $request->method('withHeader')->willReturn($request);
        $request->method('withBody')->willReturn($request);

        $httpClient->method('send')
            ->with($request)
            ->willReturn($response);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($bodyStream);
        $bodyStream->method('__toString')->willReturn(
            json_encode(['choices' => [['message' => ['content' => 'Hi there!']]]])
        );

        $client = new ChatClient($httpClient, $requestFactory, $streamFactory, $logger);
        $result = $client->complete($chatRequest, $config);

        $this->assertSame('Hi there!', $result);
    }

    /** @test */
    public function throwsUpstreamExceptionOnNon200(): void
    {
        $httpClient = $this->createMock(GuzzleClient::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $bodyStream = $this->createMock(StreamInterface::class);

        $config = ChatbotConfig::enabled(
            baseUrl: 'https://api.example.com',
            apiKey: 'sk-test',
            model: 'gpt-4o',
            everywhere: false,
            colorPrimary: '#4F9EF7',
            colorText: '#ffffff',
            colorUserText: '#1a1a1a',
            position: 'bottom-right',
            startMessage: null,
        );

        $chatRequest = ChatRequest::fromArray([
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        $streamFactory->method('createStream')->willReturn($bodyStream);
        $requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $request->method('withBody')->willReturn($request);

        $httpClient->method('send')->with($request)->willReturn($response);

        $response->method('getStatusCode')->willReturn(429);
        $response->method('getBody')->willReturn($bodyStream);
        $bodyStream->method('__toString')->willReturn('Too many requests');

        $this->expectException(UpstreamException::class);
        $this->expectExceptionMessage('Too many requests');

        $client = new ChatClient($httpClient, $requestFactory, $streamFactory, $logger);
        $client->complete($chatRequest, $config);
    }

    /** @test */
    public function throwsRuntimeExceptionOnConnectionFailure(): void
    {
        $httpClient = $this->createMock(GuzzleClient::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $bodyStream = $this->createMock(StreamInterface::class);

        $config = ChatbotConfig::enabled(
            baseUrl: 'https://api.example.com',
            apiKey: 'sk-test',
            model: 'gpt-4o',
            everywhere: false,
            colorPrimary: '#4F9EF7',
            colorText: '#ffffff',
            colorUserText: '#1a1a1a',
            position: 'bottom-right',
            startMessage: null,
        );

        $chatRequest = ChatRequest::fromArray([
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        $streamFactory->method('createStream')->willReturn($bodyStream);
        $requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $request->method('withBody')->willReturn($request);

        $httpClient->method('send')
            ->with($request)
            ->willThrowException(new \RuntimeException('Connection timed out'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to reach chat backend.');

        $client = new ChatClient($httpClient, $requestFactory, $streamFactory, $logger);
        $client->complete($chatRequest, $config);
    }
}
