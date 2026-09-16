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
                'data' => ['character' => 'sladka'],
                'categories' => [['name' => 'Parfemy']],
                'alternatives' => [],
                'profile_probabilities' => [[
                    'customer_profile_id' => 11,
                    'probability_percent' => 85,
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
                'data' => [],
                'categories' => [['name' => 'Pece']],
                'alternatives' => [],
                'profile_probabilities' => [[
                    'customer_profile_id' => 11,
                    'probability_percent' => 40,
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
    'profile_id' => 11,
    'query' => 'parfem',
    'max_price' => 1500,
    'limit' => 2,
]);
assert_test('ranks matching product first', $search['products'][0]['id'] === 90);
assert_test('returns selected profile probability', $search['products'][0]['profile_probability'] === 85);

$detail = $service->execute(OpenAiCatalogService::GET_PRODUCT, ['product_id' => 90]);
assert_test('returns requested product detail', $detail['product']['sku'] === 'FUN-P016');

$invalidThrown = false;
try {
    $service->execute(OpenAiCatalogService::SEARCH_PRODUCTS, []);
} catch (InvalidArgumentException) {
    $invalidThrown = true;
}
assert_test('rejects search without criteria', $invalidThrown);

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
