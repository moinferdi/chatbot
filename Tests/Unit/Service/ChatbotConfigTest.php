<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Tests\Unit\Service;

use Moinferdi\Chatbot\Service\ChatbotConfig;
use PHPUnit\Framework\TestCase;

final class ChatbotConfigTest extends TestCase
{
    /** @test */
    public function disabledReturnsInvalidConfig(): void
    {
        $config = ChatbotConfig::disabled();

        $this->assertFalse($config->enabled);
        $this->assertFalse($config->isValid());
    }

    /** @test */
    public function enabledWithAllFieldsIsValid(): void
    {
        $config = ChatbotConfig::enabled(
            baseUrl: 'https://api.example.com',
            apiKey: 'sk-test-key',
            model: 'gpt-4o',
            everywhere: false,
            colorPrimary: '#4F9EF7',
            colorBackground: '#ffffff',
            colorText: '#1a1a1a',
            colorTitle: '#ffffff',
            position: 'bottom-right',
            startMessage: 'Hello!',
        );

        $this->assertTrue($config->enabled);
        $this->assertTrue($config->isValid());
    }

    /** @test */
    public function isValidReturnsFalseWhenBaseUrlEmpty(): void
    {
        $config = ChatbotConfig::enabled(
            baseUrl: '',
            apiKey: 'sk-test-key',
            model: 'gpt-4o',
            everywhere: false,
            colorPrimary: '#4F9EF7',
            colorBackground: '#ffffff',
            colorText: '#1a1a1a',
            colorTitle: '#ffffff',
            position: 'bottom-right',
            startMessage: null,
        );

        $this->assertFalse($config->isValid());
    }

    /** @test */
    public function isValidReturnsFalseWhenApiKeyEmpty(): void
    {
        $config = ChatbotConfig::enabled(
            baseUrl: 'https://api.example.com',
            apiKey: '',
            model: 'gpt-4o',
            everywhere: false,
            colorPrimary: '#4F9EF7',
            colorBackground: '#ffffff',
            colorText: '#1a1a1a',
            colorTitle: '#ffffff',
            position: 'bottom-right',
            startMessage: null,
        );

        $this->assertFalse($config->isValid());
    }

    /** @test */
    public function startMessageCanBeNull(): void
    {
        $config = ChatbotConfig::enabled(
            baseUrl: 'https://api.example.com',
            apiKey: 'sk-key',
            model: 'gpt-4o',
            everywhere: false,
            colorPrimary: '#4F9EF7',
            colorBackground: '#ffffff',
            colorText: '#1a1a1a',
            colorTitle: '#ffffff',
            position: 'bottom-right',
            startMessage: null,
        );

        $this->assertNull($config->startMessage);
        $this->assertTrue($config->isValid());
    }
}
