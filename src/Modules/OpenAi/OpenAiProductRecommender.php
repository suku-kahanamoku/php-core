<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/** Odděluje výběr produktu modelem OpenAI od HTTP a katalogové vrstvy. */
interface OpenAiProductRecommender
{
    /**
     * Vybere hlavní produkt nebo volitelný doplněk přes OpenAI, nikoli lokálním skóre.
     *
     * @param string $query Úplný záměr včetně důkazů potvrzení hlavního nákupu u doplňku.
     * @param string $category Konkrétní kategorie hledaného produktu.
     * @param string $priceIntent Cenový záměr; pouze u doplňku smí zůstat neznámý.
     * @param int|null $excludedProductId Jednorázově vyloučená karta.
     * @param int|null $currentProductId Aktuálně zobrazená karta.
     * @param int|null $addonForProductId Doložený hlavní produkt, k němuž se hledá doplněk.
     * @return array{status:string,product_id:int|null,match_quality?:string,reason?:string}
     */
    public function recommend(string $query, string $category, string $priceIntent, ?int $excludedProductId = null, ?int $currentProductId = null, ?int $addonForProductId = null): array;
}
