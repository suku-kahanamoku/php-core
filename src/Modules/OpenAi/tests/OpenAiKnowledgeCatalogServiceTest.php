#!/usr/bin/env php
<?php

declare(strict_types=1);

/** Offline test čistého katalogového mostu bez PHP rozhodování. */

use App\Modules\OpenAi\OpenAiCatalogGateway;
use App\Modules\OpenAi\OpenAiKnowledgeCatalogService;
use App\Modules\OpenAi\OpenAiProductRetrieval;

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/../../../../tests/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}
require_once __DIR__ . '/../../../../vendor/autoload.php';

section('OpenAI knowledge catalog tools');
$gateway = new class implements OpenAiCatalogGateway {
    public int $publishedProductsCalls = 0;
    public int $publishedProductCalls = 0;
    private array $product = [
        'id' => 90,
        'sku' => 'FUN-P016',
        'name' => 'Večerní parfém',
        'description' => 'Výrazná kávová vůně.',
        'price_with_vat' => 1400.0,
        'stock_quantity' => 2,
        'published' => 1,
        'franchise_code' => 'fun',
        'data' => ['selection_attributes' => ['occasion' => ['večer']]],
        'categories' => [['name' => 'Fragrances']],
        'alternatives' => [],
    ];
    public function publishedProfiles(): iterable { return []; }
    public function publishedProducts(): iterable
    {
        $this->publishedProductsCalls++;
        yield $this->product;
    }
    public function publishedProduct(int $productId): ?array
    {
        $this->publishedProductCalls++;
        return $productId === 90 ? $this->product : null;
    }
};
$receivedQuery = (object) ['value' => null];
$retrieval = new class($receivedQuery) implements OpenAiProductRetrieval {
    public function __construct(private object $receivedQuery) {}
    public function retrieve(string $query, int $limit): array
    {
        $this->receivedQuery->value = [$query, $limit];
        return [
            'status' => 'results',
            'products' => [[
                'product_id' => 90,
                'similarity' => 0.82,
                'document' => [
                    'product_id' => 90,
                    'name' => 'Večerní parfém',
                    'selection_attributes' => ['occasion' => ['večer']],
                ],
            ]],
        ];
    }
};
$service = new OpenAiKnowledgeCatalogService($gateway, $retrieval);

$results = $service->execute(OpenAiKnowledgeCatalogService::RETRIEVE_PRODUCTS, [
    'query' => 'Výrazná vůně na večer',
    'limit' => 8,
    'rejected_product_ids' => [91],
]);
assert_test('passes the model query unchanged to Vector Store retrieval', $receivedQuery->value === ['Výrazná vůně na večer', 8]);
assert_test('returns raw retrieval documents without PHP ranking', $results['products'][0]['document']['product_id'] === 90);
assert_test('preserves OpenAI retrieval similarity as evidence', $results['products'][0]['similarity'] === 0.82);
assert_test(
    'retrieval does not query the PHP product catalog',
    $gateway->publishedProductsCalls === 0 && $gateway->publishedProductCalls === 0,
);

$detail = $service->execute(OpenAiKnowledgeCatalogService::GET_PRODUCT, ['product_id' => 90]);
assert_test('loads the current product selected by OpenAI', $detail['product']['sku'] === 'FUN-P016');
assert_test('loads exactly one catalog product only after selection', $gateway->publishedProductCalls === 1);
assert_test('does not expose tenant internals in product detail', !isset($detail['product']['franchise_code']));
assert_test('does not claim PHP verification of the model decision', $detail['catalog_status'] === 'current' && !isset($detail['verification']));

$unavailable = (new OpenAiKnowledgeCatalogService($gateway))->execute(
    OpenAiKnowledgeCatalogService::RETRIEVE_PRODUCTS,
    ['query' => 'hydratační péče'],
);
assert_test('does not fall back to PHP product selection', $unavailable['status'] === 'unavailable' && $unavailable['products'] === []);

$invalidQueryThrown = false;
try {
    $service->execute(OpenAiKnowledgeCatalogService::RETRIEVE_PRODUCTS, ['query' => '']);
} catch (InvalidArgumentException) {
    $invalidQueryThrown = true;
}
assert_test('rejects an empty retrieval query', $invalidQueryThrown);

$unknownToolThrown = false;
try {
    $service->execute('search_products', ['query' => 'parfém']);
} catch (InvalidArgumentException) {
    $unknownToolThrown = true;
}
assert_test('rejects the removed PHP decision tool', $unknownToolThrown);

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
