<?php

declare(strict_types=1);

namespace App\Modules\Transport\Contracts;

use App\Modules\Transport\Model\ProviderDefinition;

/**
 * Základní kontrakt dopravního poskytovatele.
 *
 * Implementace drží definici poskytovatele (adaptér, konfigurace, pokrytí) a
 * deklaruje, které operace umí; síťové volání řeší přes `HttpModule`.
 */
interface Provider
{
    /**
     * @return ProviderDefinition Definice poskytovatele včetně jeho konfigurace a pokrytí.
     */
    public function definition(): ProviderDefinition;

    /**
     * @return list<string> Podporované operace poskytovatele.
     */
    public function capabilities(): array;
}
