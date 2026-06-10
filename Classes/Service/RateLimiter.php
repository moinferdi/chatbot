<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Service;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Simple IP-based rate limiter using TYPO3's cache framework.
 *
 * Limits to a configurable number of requests per minute per IP.
 */
final class RateLimiter
{
    private const int MAX_REQUESTS = 20;
    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private readonly FrontendInterface $cache,
    ) {}

    public function attempt(string $ip): bool
    {
        $key = 'ratelimit_' . md5($ip);
        $entry = $this->cache->get($key);

        if (!is_array($entry)) {
            $this->cache->set($key, [
                'count' => 1,
                'windowStart' => time(),
            ], [], self::WINDOW_SECONDS);
            return true;
        }

        $now = time();
        if ($now - $entry['windowStart'] > self::WINDOW_SECONDS) {
            // Window expired, start fresh
            $this->cache->set($key, [
                'count' => 1,
                'windowStart' => $now,
            ], [], self::WINDOW_SECONDS);
            return true;
        }

        if ($entry['count'] >= self::MAX_REQUESTS) {
            return false;
        }

        $entry['count']++;
        // Preserve remaining TTL
        $remainingTtl = self::WINDOW_SECONDS - ($now - $entry['windowStart']);
        $this->cache->set($key, $entry, [], max(1, $remainingTtl));
        return true;
    }

    /**
     * Seconds the caller should wait before retrying (for the Retry-After header).
     */
    public function retryAfter(string $ip): int
    {
        $key = 'ratelimit_' . md5($ip);
        $entry = $this->cache->get($key);

        if (!is_array($entry)) {
            return 0;
        }

        $elapsed = time() - $entry['windowStart'];
        return max(1, self::WINDOW_SECONDS - $elapsed);
    }
}
