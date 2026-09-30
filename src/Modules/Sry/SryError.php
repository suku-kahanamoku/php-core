<?php

declare(strict_types=1);

namespace App\Modules\Sry;

/**
 * Doménová výjimka modulu Sry nesoucí strojový kód chyby a HTTP stav.
 *
 * Klienti se kód (`errorCode`) propírá do těla chybové odpovědi, takže se
 * vrací lokalizovaná hláška bez úniku interních detailů. HTTP vrstva (api/sry)
 * mapuje kód na stavový kód.
 */
final class SryError extends \RuntimeException
{
    /**
     * @param  string $errorCode Strojový kód chyby (např. 'invalidInput', 'notFound', 'conflict').
     * @param  int    $status    HTTP stavový kód odpovědi; výchozí 422.
     * @return void
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status = 422,
    ) {
        parent::__construct($errorCode);
    }
}
