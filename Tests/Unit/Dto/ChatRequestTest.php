<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Tests\Unit\Dto;

use Moinferdi\Chatbot\Dto\ChatRequest;
use PHPUnit\Framework\TestCase;

final class ChatRequestTest extends TestCase
{
    /** @test */
    public function createsFromValidArray(): void
    {
        $data = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ];

        $request = ChatRequest::fromArray($data);

        $this->assertCount(1, $request->messages);
        $this->assertSame('user', $request->messages[0]['role']);
    }

    /** @test */
    public function throwsOnMissingMessages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "messages" field.');

        ChatRequest::fromArray([]);
    }

    /** @test */
    public function throwsOnNonArrayMessages(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ChatRequest::fromArray(['messages' => 'not-an-array']);
    }

    /** @test */
    public function throwsOnTooManyMessages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many messages');

        $messages = [];
        for ($i = 0; $i <= 60; $i++) {
            $messages[] = ['role' => 'user', 'content' => "Message $i"];
        }

        ChatRequest::fromArray(['messages' => $messages]);
    }

    /** @test */
    public function throwsOnInvalidRole(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid message role');

        ChatRequest::fromArray([
            'messages' => [
                ['role' => 'hacker', 'content' => 'hi'],
            ],
        ]);
    }

    /** @test */
    public function throwsOnOversizedMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('too long');

        ChatRequest::fromArray([
            'messages' => [
                ['role' => 'user', 'content' => str_repeat('a', 5000)],
            ],
        ]);
    }

    /** @test */
    public function acceptsMessagesMaxCount(): void
    {
        $messages = [];
        for ($i = 0; $i < 50; $i++) {
            $messages[] = ['role' => 'user', 'content' => "Message $i"];
        }

        $request = ChatRequest::fromArray(['messages' => $messages]);

        $this->assertCount(50, $request->messages);
    }
}
