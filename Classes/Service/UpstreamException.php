<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Service;

/**
 * Thrown when the upstream API returns a non-200 response.
 * Carries the HTTP status and upstream error detail for the client.
 */
final class UpstreamException extends \RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        string $detail,
    ) {
        parent::__construct($detail);
    }
}
