<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Service;

final class ChatbotConfig
{
    private function __construct(
        public readonly bool $enabled,
        public readonly string $baseUrl,
        public readonly string $apiKey,
        public readonly string $model,
        public readonly bool $everywhere,
        public readonly string $colorPrimary,
        public readonly string $colorBackground,
        public readonly string $colorText,
        public readonly string $position,
        public readonly ?string $startMessage,
    ) {}

    public static function disabled(): self
    {
        return new self(
            enabled: false, baseUrl: '', apiKey: '', model: '', everywhere: false,
            colorPrimary: '', colorBackground: '', colorText: '', position: '', startMessage: null,
        );
    }

    public static function enabled(
        string $baseUrl, string $apiKey, string $model, bool $everywhere,
        string $colorPrimary, string $colorBackground, string $colorText,
        string $position, ?string $startMessage,
    ): self {
        return new self(
            enabled: true, baseUrl: $baseUrl, apiKey: $apiKey, model: $model, everywhere: $everywhere,
            colorPrimary: $colorPrimary, colorBackground: $colorBackground, colorText: $colorText,
            position: $position, startMessage: $startMessage,
        );
    }

    public function isValid(): bool
    {
        return $this->enabled && $this->baseUrl !== '' && $this->apiKey !== '';
    }
}
