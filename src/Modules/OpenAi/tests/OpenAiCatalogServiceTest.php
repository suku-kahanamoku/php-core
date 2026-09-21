#!/usr/bin/env php
<?php

declare(strict_types=1);

/** Offline test validace, filtrovani a razeni AI katalogovych nastroju. */

use App\Modules\OpenAi\OpenAiCatalogGateway;
use App\Modules\OpenAi\OpenAiCatalogService;

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/../../../../tests/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}
require_once __DIR__ . '/../../../../vendor/autoload.php';

$gateway = new class implements OpenAiCatalogGateway {
    public function publishedProfiles(): array
    {
        return [[
            'id' => 11,
            'profile_number' => 1,
            'syscode' => 'gift_buyer',
            'name' => 'Kupujici darek',
            'selection_need' => 'Chce jistotu vyberu.',
            'published' => 1,
            'franchise_code' => 'fun',
            'questions' => ['Pro koho darek vybira?'],
            'objections' => [],
            'preferences' => [],
        ]];
    }

    public function publishedProducts(): array
    {
        return [
            [
                'id' => 90,
                'sku' => 'FUN-P016',
                'name' => 'Vecerni parfem',
                'description' => 'Vyrazna kavova vune.',
                'price_with_vat' => 1400.0,
                'stock_quantity' => 2,
                'published' => 1,
                'data' => ['selection_attributes' => [
                    'category' => ['vůně'],
                    'product_type' => ['parfémová voda'],
                    'fragrance_character' => ['sladká', 'kávová', 'výrazná'],
                    'occasion' => ['večer'],
                ]],
                'categories' => [['name' => 'Parfemy']],
                'alternatives' => [],
                'profile_probabilities' => [[
                    'customer_profile_id' => 11,
                    'probability_percent' => 5,
                ]],
            ],
            [
                'id' => 91,
                'sku' => 'FUN-P017',
                'name' => 'Denni krem',
                'description' => 'Lehky krem.',
                'price_with_vat' => 900.0,
                'stock_quantity' => 4,
                'published' => 1,
                'data' => ['selection_attributes' => [
                    'category' => ['péče o pleť'],
                    'product_type' => ['denní krém'],
                    'needs' => ['hydratace'],
                ]],
                'categories' => [['name' => 'Pece']],
                'alternatives' => [],
                'profile_probabilities' => [[
                    'customer_profile_id' => 11,
                    'probability_percent' => 100,
                ]],
            ],
        ];
    }
};

$service = new OpenAiCatalogService($gateway);

section('OpenAI catalog tools');
$profiles = $service->execute(OpenAiCatalogService::LIST_PROFILES, []);
assert_test('lists one published profile', count($profiles['profiles']) === 1);
assert_test('does not expose franchise code', !isset($profiles['profiles'][0]['franchise_code']));

$search = $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, [
    'query' => 'parfem',
    'category' => 'vůně',
    'attributes' => ['sladká', 'kávová', 'večer'],
    'max_price' => 1500,
    'limit' => 2,
]);
assert_test('ranks matching product first', $search['products'][0]['id'] === 90);
assert_test('reports all matched product attributes', $search['products'][0]['attribute_match_count'] === 3);
assert_test('does not expose profile probability in primary search', !isset($search['products'][0]['profile_probability']));
assert_test('returns description so model can compare candidates', $search['products'][0]['description'] === 'Vyrazna kavova vune.');

$required = $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, [
    'category' => 'péče',
    'required_attributes' => ['hydratace'],
    'preferred_attributes' => ['denní'],
]);
assert_test('uses mandatory attributes as eligibility filters', array_column($required['products'], 'id') === [91]);
assert_test('reports matched mandatory evidence', $required['products'][0]['matched_required_attributes'] === ['hydratace']);
assert_test('reports a complete successful catalog scan', $required['status'] === 'candidates' && $required['catalog_scan_complete'] === true);

$noRequiredMatch = $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, [
    'category' => 'vůně',
    'required_attributes' => ['bez parfemace'],
]);
assert_test('returns explicit no-match state for unmet mandatory attributes', $noRequiredMatch['status'] === 'no_match');

$displayed = $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, [
    'query' => 'produkt',
    'displayed_product_ids' => [90],
]);
assert_test('ranks a new candidate before an already displayed product', $displayed['products'][0]['id'] === 91);
assert_test('keeps displayed products available for explicit reconsideration', in_array(90, array_column($displayed['products'], 'id'), true));

$rejected = $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, [
    'query' => 'produkt',
    'rejected_product_ids' => [90],
]);
assert_test('hard excludes explicitly rejected products', array_column($rejected['products'], 'id') === [91]);

$excludedByAttribute = $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, [
    'query' => 'produkt',
    'excluded_attributes' => ['kávová'],
]);
assert_test('hard excludes explicitly rejected attributes', array_column($excludedByAttribute['products'], 'id') === [91]);

$streamingGateway = new class implements OpenAiCatalogGateway {
    public function publishedProfiles(): iterable
    {
        return [];
    }

    public function publishedProducts(): iterable
    {
        for ($id = 1; $id <= 120; $id++) {
            yield [
                'id' => $id,
                'sku' => "SKU-{$id}",
                'name' => "Produkt {$id}",
                'description' => '',
                'price_with_vat' => 100.0,
                'stock_quantity' => 1,
                'categories' => [['name' => 'Test']],
                'profile_probabilities' => [],
            ];
        }
    }
};
$streamed = (new OpenAiCatalogService($streamingGateway))->execute(
    OpenAiCatalogService::SEARCH_PRODUCTS,
    ['category' => 'Test', 'limit' => 3],
);
assert_test(
    'streams catalogs larger than one database page and keeps only requested results',
    array_column($streamed['products'], 'id') === [1, 2, 3],
);

$alternative = $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, [
    'attributes' => ['hydratace'],
    'excluded_product_ids' => [90, 90],
]);
assert_test('excludes all previously shown product IDs', $alternative['products'][0]['id'] === 91);

$detail = $service->execute(OpenAiCatalogService::GET_PRODUCT, ['product_id' => 90]);
assert_test('returns requested product detail', $detail['product']['sku'] === 'FUN-P016');
assert_test('marks an eligible detail as backend verified', $detail['verification']['status'] === 'verified');

$overBudgetDetail = $service->execute(OpenAiCatalogService::GET_PRODUCT, [
    'product_id' => 90,
    'required_attributes' => ['parfémová voda'],
    'excluded_attributes' => [],
    'max_price' => 1000,
    'category' => 'vůně',
]);
assert_test('refuses to display a product over the hard budget', $overBudgetDetail['product'] === null);
assert_test('reports the hard-budget verification failure', in_array('max_price_exceeded', $overBudgetDetail['verification']['violations'], true));

$excludedDetail = $service->execute(OpenAiCatalogService::GET_PRODUCT, [
    'product_id' => 90,
    'required_attributes' => [],
    'excluded_attributes' => ['kávová'],
]);
assert_test('refuses to display a product matching an explicit exclusion', $excludedDetail['product'] === null);

$question = $service->execute(OpenAiCatalogService::SHOW_QUESTION, [
    'question' => 'Jaký máte cenový rozpočet?',
]);
assert_test('returns a validated customer question', $question['question'] === 'Jaký máte cenový rozpočet?');

$invalidThrown = false;
try {
    $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, []);
} catch (InvalidArgumentException) {
    $invalidThrown = true;
}
assert_test('rejects search without criteria', $invalidThrown);

$invalidExclusionThrown = false;
try {
    $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, [
        'attributes' => ['péče'],
        'excluded_product_ids' => [0],
    ]);
} catch (InvalidArgumentException) {
    $invalidExclusionThrown = true;
}
assert_test('rejects invalid excluded product IDs', $invalidExclusionThrown);

$invalidAttributesThrown = false;
try {
    $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, [
        'attributes' => array_fill(0, OpenAiCatalogService::MAX_SEARCH_ATTRIBUTES + 1, 'atribut'),
    ]);
} catch (InvalidArgumentException) {
    $invalidAttributesThrown = true;
}
assert_test('rejects too many product attributes', $invalidAttributesThrown);

$emptyQuestionThrown = false;
try {
    $service->execute(OpenAiCatalogService::SHOW_QUESTION, ['question' => '  ']);
} catch (InvalidArgumentException) {
    $emptyQuestionThrown = true;
}
assert_test('rejects an empty customer question', $emptyQuestionThrown);

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
