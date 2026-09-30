<?php

declare(strict_types=1);

namespace App\Modules\Http;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/**
 * Počítá dekódované bajty, včetně chunked/komprimovaných odpovědí bez Content-Length.
 *
 * Prvek obálkuje PSR-7 stream a po překročení limitu přestane zapisovat a nastaví
 * `exceeded`. Neprovádí žádné volání HTTP klienta.
 */
final class LimitedStream implements StreamInterface
{
    use StreamDecoratorTrait;
    private StreamInterface $stream;
    private int $written = 0;
    public bool $exceeded = false;

    /**
     * @param  StreamInterface $stream Podkladový cílový stream (soubor nebo php://temp).
     * @param  int             $limit  Maximální povolený počet bajtů.
     * @return void
     */
    public function __construct(StreamInterface $stream, private readonly int $limit)
    {
        $this->stream = $stream;
    }

    /**
     * Zapíše chunk do cílového streamu, pokud limit ještě nebyl vyčerpán.
     *
     * @param  string $string Část těla k zápisu.
     * @return int            Počet zapsaných bajtů; 0, pokud byl limit překročen.
     */
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
