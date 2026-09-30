<?php

declare(strict_types=1);

namespace App\Modules\Transport\Model;

/**
 * Chyba dopravního modulu s ustáleným kódem příčiny a stavem HTTP.
 *
 * `reason` je strojový kód (`invalid_query`, `not_found`, ...), `status` stav
 * pro odpověď API a `details` další kontext (např. stav zdrojů). Zpráva je
 * určena uživateli.
 */
final class TransportException extends \RuntimeException
{
    /**
     * @param  string               $reason  Strojový kód příčiny chyby.
     * @param  string               $message Zpráva pro uživatele.
     * @param  int                  $status  Stav HTTP odpovědi.
     * @param  array<string, mixed> $details  Další kontext chyby.
     * @return void
     */
    public function __construct(public readonly string $reason, string $message, public readonly int $status = 422, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
