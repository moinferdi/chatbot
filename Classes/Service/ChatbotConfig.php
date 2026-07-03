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
 *  - colorOutline:  1px outline colour drawn around the combined widget
 *                  (launcher + panel) via directional box-shadows so no
 *                  line runs across the seam where they meet.
 *  - offsetX/offsetY: pixel distance from the screen corner (0–100).
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
        public readonly string $colorOutline,
        public readonly string $position,
        public readonly int $offsetX,
        public readonly int $offsetY,
        public readonly ?string $startMessage,
        public readonly ?string $title,
    ) {}

    public static function disabled(): self
    {
        return new self(
            enabled: false, baseUrl: '', apiKey: '', model: '', everywhere: false,
            colorPrimary: '', colorText: '', colorUserText: '', colorOutline: '',
            position: '', offsetX: 0, offsetY: 0, startMessage: null, title: null,
        );
    }

    public static function enabled(
        string $baseUrl, string $apiKey, string $model, bool $everywhere,
        string $colorPrimary, string $colorText, string $colorUserText,
        string $position, ?string $startMessage, ?string $title = null,
        string $colorOutline = '#ffffff', int $offsetX = 0, int $offsetY = 0,
    ): self {
        return new self(
            enabled: true, baseUrl: $baseUrl, apiKey: $apiKey, model: $model, everywhere: $everywhere,
            colorPrimary: $colorPrimary, colorText: $colorText, colorUserText: $colorUserText,
            colorOutline: $colorOutline, position: $position, offsetX: $offsetX, offsetY: $offsetY,
            startMessage: $startMessage, title: $title,
        );
    }

    public function isValid(): bool
    {
        return $this->enabled && $this->baseUrl !== '' && $this->apiKey !== '';
    }
}
