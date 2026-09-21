<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/**
 * Oddeluje AI katalogovou logiku od konkretni databazove implementace.
 *
 * Rozhrani umoznuje testovat vyber profilu a produktu bez pripojeni k MySQL.
 */
interface OpenAiCatalogGateway
{
    /** @return iterable<array<string, mixed>> Publikovane profily aktualniho tenanta. */
    public function publishedProfiles(): iterable;

    /** @return iterable<array<string, mixed>> Publikovane produkty aktualniho tenanta. */
    public function publishedProducts(): iterable;
}
