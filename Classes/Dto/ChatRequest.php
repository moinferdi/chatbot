<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Dto;

final class ChatRequest
{
    /** @var list<array{role: string, content: string}> */
    public readonly array $messages;
    public readonly string $model;

    private const MAX_MESSAGES = 50;
    private const MAX_MESSAGE_LENGTH = 4000;

    public static function fromArray(array $data): self
    {
        if (!isset($data['messages']) || !is_array($data['messages'])) {
            throw new \InvalidArgumentException('Missing or invalid "messages" field.');
        }

        if (count($data['messages']) > self::MAX_MESSAGES) {
            throw new \InvalidArgumentException(
                'Too many messages. Maximum is ' . self::MAX_MESSAGES . '.'
            );
        }

        foreach ($data['messages'] as $msg) {
            if (!isset($msg['role'], $msg['content']) || !is_string($msg['role']) || !is_string($msg['content'])) {
                throw new \InvalidArgumentException('Each message must have string "role" and "content".');
            }
            if (!in_array($msg['role'], ['user', 'assistant', 'system'], true)) {
                throw new \InvalidArgumentException('Invalid message role: ' . $msg['role']);
            }
            if (mb_strlen($msg['content']) > self::MAX_MESSAGE_LENGTH) {
                throw new \InvalidArgumentException(
                    'Message too long. Maximum is ' . self::MAX_MESSAGE_LENGTH . ' characters.'
                );
            }
        }

        $model = isset($data['model']) && is_string($data['model']) ? trim($data['model']) : '';

        $self = new self();
        $self->messages = $data['messages'];
        $self->model = $model;
        return $self;
    }
}
