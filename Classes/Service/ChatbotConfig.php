<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Service;

/**
 * Immutable chatbot configuration.
 *
 * Colour scheme (3 configured colours):
 *  - colorPrimary:  background of the whole UI (panel, header, assistant
 *                   messages, launcher).
 *  - colorText:     text colour for everything that sits on the primary
 *                   background (chat title, assistant text, launcher text,
 *                   attribution, input, links, …). Also used as the user
 *                   message bubble background (inverted contrast).
 *  - colorUserText: text colour inside the user's message bubble.
 */
final class ChatbotConfig
{
    private function __construct(
        public readonly bool $enabled,
        public readonly string $baseUrl,
        public readonly string $apiKey,
        public readonly string $model,
        public readonly bool $everywhere,
        public readonly string $colorPrimary,
        public readonly string $colorText,
        public readonly string $colorUserText,
        public readonly string $position,
        public readonly ?string $startMessage,
        public readonly ?string $title,
    ) {}

    public static function disabled(): self
    {
        return new self(
            enabled: false, baseUrl: '', apiKey: '', model: '', everywhere: false,
            colorPrimary: '', colorText: '', colorUserText: '', position: '', startMessage: null, title: null,
        );
    }

    public static function enabled(
        string $baseUrl, string $apiKey, string $model, bool $everywhere,
        string $colorPrimary, string $colorText, string $colorUserText,
        string $position, ?string $startMessage, ?string $title = null,
    ): self {
        return new self(
            enabled: true, baseUrl: $baseUrl, apiKey: $apiKey, model: $model, everywhere: $everywhere,
            colorPrimary: $colorPrimary, colorText: $colorText, colorUserText: $colorUserText,
            position: $position, startMessage: $startMessage, title: $title,
        );
    }

    public function isValid(): bool
    {
        return $this->enabled && $this->baseUrl !== '' && $this->apiKey !== '';
    }
}
