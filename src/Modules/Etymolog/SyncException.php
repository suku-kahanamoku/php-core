<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

/**
 * Výjimka jednoho zdroje synchronizace.
 *
 * Nese strojový důvod (`reason`) a doporučené zpoždění dalšího pokusu, které
 * správce fronty přebírá do `retry_at`. Neočekávané chyby se zde nepřevádějí —
 * dávka se řádně označí jako neúspěšná a pokračuje se dalším zdrojem.
 */
final class SyncException extends \RuntimeException
{
    /**
     * @param  string $reason     Strojový kód důvodu (např. 'upstream_rate_limited', 'unsupported_provider').
     * @param  int    $retryAfter Počet sekund do doporučeného dalšího pokusu.
     * @return void
     */
    public function __construct(public readonly string $reason, public readonly int $retryAfter = 300)
    {
        parent::__construct($reason);
    }
}
