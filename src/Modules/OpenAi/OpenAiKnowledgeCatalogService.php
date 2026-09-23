<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/**
 * Vystavuje Realtime modelu OpenAI doporučení a čtení aktuálního katalogu.
 *
 * Služba neurčuje vhodnost produktu, nefiltruje podle rozhovoru a nesestavuje
 * vlastní pořadí kandidátů. Celý významový výběr provádí Responses model s
 * hostovaným file_search; PHP pouze ověří vstupní kontrakt a zprostředkuje data.
 */
final class OpenAiKnowledgeCatalogService
{
    public const RECOMMEND_PRODUCT = 'recommend_product';
    public const GET_PRODUCT = 'get_product';
    public const MAX_RECOMMENDATION_QUERY_LENGTH = 1000;

    public function __construct(
        private OpenAiCatalogGateway $catalog,
        private ?OpenAiProductRecommender $recommender = null,
    ) {}

    /**
     * Provede jeden úzce povolený read-only nástroj.
     *
     * @param string $name Název funkce deklarované v Realtime relaci.
     * @param array<string, mixed> $arguments Nedůvěryhodné JSON argumenty modelu.
     * @return array<string, mixed> Kompaktní výsledek pro Realtime kontext.
     */
    public function execute(string $name, array $arguments): array
    {
        return match ($name) {
            self::RECOMMEND_PRODUCT => $this->recommendProduct($arguments),
            self::GET_PRODUCT => $this->getProduct($arguments),
            default => throw new \InvalidArgumentException('Unknown AI catalog tool.'),
        };
    }

    /**
     * Předá potvrzený nákupní záměr OpenAI Responses modelu s file_search.
     *
     * PHP nenačítá produktový katalog, dokumenty neporovnává s rozhovorem a
     * nevybírá vítěze. Vrací pouze ID zvolené a doložené OpenAI Vector Store.
     *
     * @param array<string, mixed> $arguments Dotaz, kategorie a cenový záměr.
     * @return array{status:string,product_id:int|null}
     */
    private function recommendProduct(array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        if ($query === '' || mb_strlen($query) > self::MAX_RECOMMENDATION_QUERY_LENGTH) {
            throw new \InvalidArgumentException('Recommendation query is required and must not exceed 1000 characters.');
        }
        $category = trim((string) ($arguments['category'] ?? ''));
        $priceIntent = trim((string) ($arguments['price_intent'] ?? ''));
        if ($category === '' || mb_strlen($category) > 120) {
            throw new \InvalidArgumentException('Concrete product category is required.');
        }
        if ($priceIntent === '' || mb_strlen($priceIntent) > 240) {
            throw new \InvalidArgumentException('Confirmed price intent is required.');
        }
        if ($this->recommender === null) {
            return ['status' => 'unavailable', 'product_id' => null];
        }
        try {
            return $this->recommender->recommend($query, $category, $priceIntent);
        } catch (OpenAiConfigurationException | OpenAiUpstreamException) {
            return ['status' => 'unavailable', 'product_id' => null];
        }
    }

    /**
     * Vrátí aktuální publikovaný katalogový detail podle ID zvoleného modelem.
     *
     * Tato metoda neposuzuje shodu s rozhovorem. Dostupnost, cenu a ostatní
     * aktuální skutečnosti pouze předá modelu a UI jako katalogová data.
     *
     * @param array<string, mixed> $arguments Objekt s kladným `product_id`.
     * @return array{product:array<string, mixed>|null,catalog_status:string}
     */
    private function getProduct(array $arguments): array
    {
        $productId = filter_var($arguments['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId < 1) {
            throw new \InvalidArgumentException('product_id is required.');
        }
        $product = $this->catalog->publishedProduct($productId);
        if ($product !== null) {
            return [
                'product' => array_intersect_key($product, array_fill_keys([
                    'id',
                    'sku',
                    'name',
                    'description',
                    'price_with_vat',
                    'stock_quantity',
                    'kind',
                    'color',
                    'variant',
                    'data',
                    'categories',
                    'alternatives',
                ], true)),
                'catalog_status' => 'current',
            ];
        }
        return ['product' => null, 'catalog_status' => 'not_found'];
    }
}
