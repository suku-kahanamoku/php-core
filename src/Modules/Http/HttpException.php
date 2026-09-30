<?php

declare(strict_types=1);

namespace App\Modules\Http;

/** Výjimka odchozího HTTP volání; neobsahuje tělo odpovědi, URL, credentials ani řetězec transportní výjimky. */
final class HttpException extends \RuntimeException
{
    /**
     * @param  string $reason Krátký kód důvodu selhání (např. 'invalid_json', 'http_status').
     * @param  int    $status HTTP stavový kód, pokud byl zjištěn (0 při chybě transportu).
     * @return void
     */
    public function __construct(public readonly string $reason, public readonly int $status = 0)
    {
        parent::__construct('HTTP request failed: '.$reason);
    }
}
