#!/usr/bin/env php
<?php

declare(strict_types=1);

/** Offline test katalogového mostu bez PHP rozhodování. */

use App\Modules\OpenAi\OpenAiCatalogGateway;
use App\Modules\OpenAi\OpenAiKnowledgeCatalogService;
use App\Modules\OpenAi\OpenAiProductRecommender;

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
$receivedNeed = (object) ['value' => null];
$recommender = new class($receivedNeed) implements OpenAiProductRecommender {
    public function __construct(private object $receivedNeed) {}
    public function recommend(string $query, string $category, string $priceIntent): array
    {
        $this->receivedNeed->value = [$query, $category, $priceIntent];
        return ['status' => 'selected', 'product_id' => 90];
    }
};
$service = new OpenAiKnowledgeCatalogService($gateway, $recommender);

$recommendation = $service->execute(OpenAiKnowledgeCatalogService::RECOMMEND_PRODUCT, [
    'query' => 'Výrazná vůně na večer do 2000 Kč',
    'category' => 'parfém',
    'price_intent' => 'maximálně 2000 Kč',
]);
assert_test(
    'passes complete evidence to the OpenAI Responses recommender',
    $receivedNeed->value === [
        'Výrazná vůně na večer do 2000 Kč',
        'parfém',
        'maximálně 2000 Kč',
    ],
);
assert_test('returns only the OpenAI-selected product ID', $recommendation === [
    'status' => 'selected',
    'product_id' => 90,
]);
assert_test(
    'recommendation does not query the PHP product catalog',
    $gateway->publishedProductsCalls === 0 && $gateway->publishedProductCalls === 0,
);

$detail = $service->execute(OpenAiKnowledgeCatalogService::GET_PRODUCT, ['product_id' => 90]);
assert_test('loads the current product selected by OpenAI', $detail['product']['sku'] === 'FUN-P016');
assert_test('loads exactly one catalog product only after selection', $gateway->publishedProductCalls === 1);
assert_test('does not expose tenant internals in product detail', !isset($detail['product']['franchise_code']));
assert_test('does not claim PHP verification of the model decision', $detail['catalog_status'] === 'current' && !isset($detail['verification']));

$unavailable = (new OpenAiKnowledgeCatalogService($gateway))->execute(
    OpenAiKnowledgeCatalogService::RECOMMEND_PRODUCT,
    [
        'query' => 'hydratační péče do 1000 Kč',
        'category' => 'pleťový krém',
        'price_intent' => 'do 1000 Kč',
    ],
);
assert_test('does not fall back to PHP product selection', $unavailable === [
    'status' => 'unavailable',
    'product_id' => null,
]);

foreach ([
    ['query' => '', 'category' => 'parfém', 'price_intent' => 'do 2000 Kč'],
    ['query' => 'vůně', 'category' => '', 'price_intent' => 'do 2000 Kč'],
    ['query' => 'vůně', 'category' => 'parfém', 'price_intent' => ''],
] as $invalidArguments) {
    $thrown = false;
    try {
        $service->execute(OpenAiKnowledgeCatalogService::RECOMMEND_PRODUCT, $invalidArguments);
    } catch (InvalidArgumentException) {
        $thrown = true;
    }
    assert_test('rejects incomplete recommendation evidence', $thrown);
}

$unknownToolThrown = false;
try {
    $service->execute('retrieve_products', ['query' => 'parfém']);
} catch (InvalidArgumentException) {
    $unknownToolThrown = true;
}
assert_test('rejects the removed legacy retrieval tool', $unknownToolThrown);

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
