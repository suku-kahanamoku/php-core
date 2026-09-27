<?php

declare(strict_types=1);

namespace App\Modules\Http;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/** Counts decoded bytes, including chunked/compressed responses without Content-Length. */
final class LimitedStream implements StreamInterface
{
    use StreamDecoratorTrait;
    private StreamInterface $stream;
    private int $written = 0;
    public bool $exceeded = false;

    public function __construct(StreamInterface $stream, private readonly int $limit)
    {
        $this->stream = $stream;
    }

    public function write(string $string): int
    {
        if ($this->written + strlen($string) > $this->limit) {
            $this->exceeded = true;
            return 0; // Abort this transfer without throwing inside libcurl's callback.
        }
        $written = $this->stream->write($string);
        $this->written += $written;
        return $written;
    }
}
