<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Tests\Unit\Service;

use Moinferdi\Chatbot\Service\RateLimiter;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class RateLimiterTest extends TestCase
{
    private array $cacheStorage = [];

    private function createCacheMock(): FrontendInterface
    {
        $storage = &$this->cacheStorage;

        $mock = $this->createMock(FrontendInterface::class);
        $mock->method('get')->willReturnCallback(
            static function (string $key) use (&$storage): mixed {
                return $storage[$key] ?? false;
            }
        );
        $mock->method('set')->willReturnCallback(
            static function (string $key, mixed $value, array $_tags, int $_ttl) use (&$storage): void {
                $storage[$key] = $value;
            }
        );

        return $mock;
    }

    protected function setUp(): void
    {
        $this->cacheStorage = [];
    }

    /** @test */
    public function allowsFirstRequest(): void
    {
        $limiter = new RateLimiter($this->createCacheMock());

        $this->assertTrue($limiter->attempt('192.168.1.1'));
    }

    /** @test */
    public function allowsUpToMaxRequests(): void
    {
        $limiter = new RateLimiter($this->createCacheMock());

        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue($limiter->attempt('192.168.1.2'), "Request $i should be allowed");
        }
    }

    /** @test */
    public function blocksAfterMaxRequests(): void
    {
        $limiter = new RateLimiter($this->createCacheMock());

        for ($i = 0; $i < 20; $i++) {
            $limiter->attempt('192.168.1.3');
        }

        $this->assertFalse($limiter->attempt('192.168.1.3'));
    }

    /** @test */
    public function tracksSeparateIps(): void
    {
        $limiter = new RateLimiter($this->createCacheMock());

        // Exhaust IP1
        for ($i = 0; $i < 20; $i++) {
            $limiter->attempt('10.0.0.1');
        }

        $this->assertFalse($limiter->attempt('10.0.0.1'));
        $this->assertTrue($limiter->attempt('10.0.0.2'));
    }

    /** @test */
    public function retryAfterIsZeroForUnknownIpAndWithinWindowOtherwise(): void
    {
        $limiter = new RateLimiter($this->createCacheMock());

        // No prior attempts → nothing to wait for.
        $this->assertSame(0, $limiter->retryAfter('new-ip'));

        $limiter->attempt('10.0.0.5');

        // Inside the 60s window the caller is asked to wait between 1 and 60s.
        $retryAfter = $limiter->retryAfter('10.0.0.5');
        $this->assertGreaterThanOrEqual(1, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }
}
