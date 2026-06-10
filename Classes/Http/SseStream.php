<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Http;

use TYPO3\CMS\Core\Http\SelfEmittableStreamInterface;

/**
 * A write-only, self-emitting PSR-7 stream that flushes chunks to the client as
 * they are produced.
 *
 * TYPO3's response emitter (AbstractApplication::sendResponse) calls emit() on
 * bodies implementing SelfEmittableStreamInterface instead of buffering the body
 * via (string) cast — which is what lets us stream Server-Sent Events token by
 * token instead of sending the whole response at once.
 *
 * Only emit() and the inert StreamInterface surface are needed; the source is an
 * iterable of already-formatted byte chunks (raw SSE passthrough).
 */
final class SseStream implements SelfEmittableStreamInterface
{
    /**
     * @param iterable<string> $chunks
     */
    public function __construct(
        private readonly iterable $chunks,
    ) {}

    public function emit(): void
    {
        foreach ($this->chunks as $chunk) {
            if ($chunk === '') {
                continue;
            }
            echo $chunk;
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            flush();
            if (connection_aborted()) {
                break;
            }
        }
    }

    public function __toString(): string
    {
        // Fallback for emitters that do not honour SelfEmittableStreamInterface.
        $buffer = '';
        foreach ($this->chunks as $chunk) {
            $buffer .= $chunk;
        }
        return $buffer;
    }

    public function close(): void {}

    public function detach()
    {
        return null;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): int
    {
        return 0;
    }

    public function eof(): bool
    {
        return true;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \RuntimeException('SseStream is not seekable.');
    }

    public function rewind(): void
    {
        throw new \RuntimeException('SseStream is not seekable.');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write($string): int
    {
        throw new \RuntimeException('SseStream is not writable.');
    }

    public function isReadable(): bool
    {
        return false;
    }

    public function read($length): string
    {
        throw new \RuntimeException('SseStream is not readable; use emit().');
    }

    public function getContents(): string
    {
        return $this->__toString();
    }

    public function getMetadata($key = null): mixed
    {
        return $key !== null ? null : [];
    }
}
