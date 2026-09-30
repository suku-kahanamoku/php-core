<?php

declare(strict_types=1);

namespace App\Modules\Etymolog;

/**
 * Doménová výjimka modulu Etymolog nesoucí HTTP stav.
 *
 * Výjimku chytá `EtymologApi::respond()` (resp. `EtymologPublicApi`), které z ní
 * sestaví JSON chybu se stavem `$status`.
 */
final class EtymologException extends \RuntimeException
{
    /**
     * @param  string $message Text chyby, který se propírá do těla odpovědi.
     * @param  int    $status  HTTP stavový kód; výchozí 422.
     * @return void
     */
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
