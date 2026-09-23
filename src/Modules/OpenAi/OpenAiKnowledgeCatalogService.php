<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/**
 * Vystavuje Realtime modelu pouze retrieval a čtení aktuálního katalogu.
 *
 * Služba neurčuje vhodnost produktu, nefiltruje podle rozhovoru a nesestavuje
 * vlastní pořadí kandidátů. Významový výběr provádí OpenAI nad dokumenty
 * vrácenými z tenantového Vector Store; PHP pouze zprostředkuje data.
 */
final class OpenAiKnowledgeCatalogService
{
    public const RETRIEVE_PRODUCTS = 'retrieve_products';
    public const GET_PRODUCT = 'get_product';
    public const MAX_RETRIEVAL_RESULTS = 20;
    public const MAX_RETRIEVAL_QUERY_LENGTH = 1000;

    public function __construct(
        private OpenAiCatalogGateway $catalog,
        private ?OpenAiProductRetrieval $retrieval = null,
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
            self::RETRIEVE_PRODUCTS => $this->retrieveProducts($arguments),
            self::GET_PRODUCT => $this->getProduct($arguments),
            default => throw new \InvalidArgumentException('Unknown AI catalog tool.'),
        };
    }

    /**
     * Předá přirozený dotaz beze změny tenantovému Vector Store.
     *
     * Vrácené pořadí i podobnost jsou výhradně výsledkem OpenAI Retrieval API.
     * PHP dokumenty neporovnává s rozhovorem a nevybírá vítěze.
     *
     * @param array<string, mixed> $arguments Dotaz a volitelný počet výsledků.
     * @return array{status:string,products:list<array<string, mixed>>,count:int}
     */
    private function retrieveProducts(array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        if ($query === '' || mb_strlen($query) > self::MAX_RETRIEVAL_QUERY_LENGTH) {
            throw new \InvalidArgumentException('Retrieval query is required and must not exceed 1000 characters.');
        }
        $limit = filter_var($arguments['limit'] ?? 10, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > self::MAX_RETRIEVAL_RESULTS) {
            throw new \InvalidArgumentException('Retrieval result limit must be between 1 and 20.');
        }
        if ($this->retrieval === null) {
            return ['status' => 'unavailable', 'products' => [], 'count' => 0];
        }
        $result = $this->retrieval->retrieve($query, $limit);
        return [
            'status' => $result['status'],
            'products' => $result['products'],
            'count' => count($result['products']),
        ];
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
