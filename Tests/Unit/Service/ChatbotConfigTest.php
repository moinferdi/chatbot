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
            colorText: '#ffffff',
            colorUserText: '#1a1a1a',
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
            colorText: '#ffffff',
            colorUserText: '#1a1a1a',
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
            colorText: '#ffffff',
            colorUserText: '#1a1a1a',
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
            colorText: '#ffffff',
            colorUserText: '#1a1a1a',
            position: 'bottom-right',
            startMessage: null,
        );

        $this->assertNull($config->startMessage);
        $this->assertTrue($config->isValid());
    }

    /** @test */
    public function enabledDefaultsToWhiteOutlineAndZeroOffset(): void
    {
        $config = ChatbotConfig::enabled(
            baseUrl: 'https://api.example.com',
            apiKey: 'sk-key',
            model: 'gpt-4o',
            everywhere: false,
            colorPrimary: '#4F9EF7',
            colorText: '#ffffff',
            colorUserText: '#1a1a1a',
            position: 'bottom-right',
            startMessage: null,
        );

        $this->assertSame('#ffffff', $config->colorOutline);
        $this->assertSame(0, $config->offsetX);
        $this->assertSame(0, $config->offsetY);
    }

    /** @test */
    public function enabledAcceptsOutlineAndOffsetValues(): void
    {
        $config = ChatbotConfig::enabled(
            baseUrl: 'https://api.example.com',
            apiKey: 'sk-key',
            model: 'gpt-4o',
            everywhere: false,
            colorPrimary: '#4F9EF7',
            colorText: '#ffffff',
            colorUserText: '#1a1a1a',
            position: 'bottom-left',
            startMessage: null,
            colorOutline: '#000000',
            offsetX: 24,
            offsetY: 100,
        );

        $this->assertSame('#000000', $config->colorOutline);
        $this->assertSame(24, $config->offsetX);
        $this->assertSame(100, $config->offsetY);
    }
}
