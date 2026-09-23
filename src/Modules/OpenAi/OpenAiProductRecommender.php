<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/** Odděluje výběr produktu modelem OpenAI od HTTP a katalogové vrstvy. */
interface OpenAiProductRecommender
{
    /**
     * @return array{status:string,product_id:int|null}
     */
    public function recommend(string $query, string $category, string $priceIntent): array;
}
