#!/usr/bin/env php
<?php

declare(strict_types=1);

/** Offline test Responses file_search doporučování bez síťového spojení. */

use App\Modules\OpenAi\OpenAiResponsesProductRecommender;
use App\Modules\OpenAi\OpenAiUpstreamException;
use App\Modules\OpenAi\OpenAiVectorStoreGateway;

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/../../../../tests/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}
require_once __DIR__ . '/../../../../vendor/autoload.php';

section('OpenAI Responses product recommender');
$store = new class implements OpenAiVectorStoreGateway {
    public function acquireSyncLock(): bool
    {
        return true;
    }
    public function releaseSyncLock(): void {}
    public function store(): ?array
    {
        return ['vector_store_id' => 'vs_fun'];
    }
    public function saveStore(string $vectorStoreId, string $name): void {}
    public function productMappings(): array
    {
        return [];
    }
    public function saveProductMapping(int $productId, string $vectorStoreId, string $fileId, string $sourceHash): void {}
    public function deleteProductMapping(int $productId): void {}
};
$captured = null;
$recommender = new OpenAiResponsesProductRecommender(
    $store,
    static function (string $apiKey, array $payload) use (&$captured): array {
        $captured = ['apiKey' => $apiKey, 'payload' => $payload];
        return [
            'status' => 200,
            'body' => json_encode([
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'file_search_call',
                        'results' => [[
                            'attributes' => ['product_id' => 90, 'franchise_code' => 'fann'],
                        ]],
                    ],
                    [
                        'type' => 'message',
                        'content' => [[
                            'type' => 'output_text',
                            'text' => '{"status":"selected","product_id":90,"match_quality":"nearest","reason":"V dané ceně není produkt skladem."}',
                        ]],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ];
    },
    'sk-test',
    'gpt-test-recommender',
);
$result = $recommender->recommend(
    'svěží parfém na den do 2000 Kč',
    'parfém',
    'maximálně 2000 Kč',
    85,
    85,
);
assert_test('returns a product selected by the Responses model', $result === [
    'status' => 'selected',
    'product_id' => 90,
    'match_quality' => 'nearest',
    'reason' => 'V dané ceně není produkt skladem.',
]);
assert_test('uses hosted file_search against the tenant store', $captured['payload']['tools'] === [[
    'type' => 'file_search',
    'vector_store_ids' => ['vs_fun'],
    'max_num_results' => 50,
    'filters' => [
        'type' => 'ne',
        'key' => 'product_id',
        'value' => 85,
    ],
]]);
assert_test(
    'passes the one-request exclusion to the model input',
    json_decode($captured['payload']['input'], true, flags: JSON_THROW_ON_ERROR)['excluded_product_id'] === 85,
);
assert_test('requires a hosted tool call', $captured['payload']['tool_choice'] === 'required');
assert_test('bounds hosted searches without a PHP ranking algorithm', $captured['payload']['max_tool_calls'] === 3);
assert_test(
    'uses strict minimal structured output',
    $captured['payload']['text']['format']['type'] === 'json_schema'
        && $captured['payload']['text']['format']['strict'] === true
        && $captured['payload']['max_output_tokens'] === 512,
);
assert_test('does not expose the server key in the result', !str_contains(json_encode($result), 'sk-test'));
assert_test('passes displayed card context even without relying on exclusion inference',
    json_decode($captured['payload']['input'], true, flags: JSON_THROW_ON_ERROR)['current_product_id'] === 85);
assert_test('requires targeted follow-up searches before a nearest result',
    str_contains($captured['payload']['instructions'], 'run a second targeted search')
        && str_contains($captured['payload']['instructions'], 'do not claim a catalog-wide absence'));
assert_test('searches the revised need and verifies relative price evidence',
    str_contains($captured['payload']['instructions'], 'Use the latest confirmed category and price_intent')
        && str_contains($captured['payload']['instructions'], 'A corrected maximum replaces the old maximum')
        && str_contains($captured['payload']['instructions'], 'unverifiable relative price condition cannot qualify as exact')
        && str_contains($captured['payload']['instructions'], 'searching the new category'));

$excludedCurrentRejected = false;
try {
    (new OpenAiResponsesProductRecommender(
        $store,
        static fn(): array => [
            'status' => 200,
            'body' => json_encode([
                'status' => 'completed',
                'output' => [
                    ['type' => 'file_search_call', 'results' => [[
                        'attributes' => ['product_id' => 90],
                    ]]],
                    ['type' => 'message', 'content' => [[
                        'type' => 'output_text',
                        'text' => '{"status":"selected","product_id":90,"match_quality":"exact","reason":""}',
                    ]]],
                ],
            ], JSON_THROW_ON_ERROR),
        ],
        'sk-test',
    ))->recommend('vůně', 'parfém', 'bez omezení', 90);
} catch (OpenAiUpstreamException) {
    $excludedCurrentRejected = true;
}
assert_test('rejects the current product even if the model returns it', $excludedCurrentRejected);

$recommender->recommend('Bere si líčení ID 85; vhodný odličovač.', 'odličovač', '', 86, 86, 85);
assert_test('passes add-on anchor and excludes both primary and rejected optional card',
    json_decode($captured['payload']['input'], true, flags: JSON_THROW_ON_ERROR)['addon_for_product_id'] === 85
        && $captured['payload']['tools'][0]['filters'] === ['type' => 'and', 'filters' => [
            ['type' => 'ne', 'key' => 'product_id', 'value' => 86],
            ['type' => 'ne', 'key' => 'product_id', 'value' => 85],
        ]]
        && $captured['payload']['text']['format']['schema']['properties']['status']['enum'] === ['selected', 'no_match']);

$optionalDecision = ['status' => 'no_match', 'product_id' => null, 'match_quality' => 'nearest', 'reason' => 'Hledání nedoložilo vhodný skladový doplněk.'];
$optional = new OpenAiResponsesProductRecommender($store,
    static function () use (&$optionalDecision): array {
        return ['status' => 200, 'body' => json_encode(['status' => 'completed', 'output' => [
            ['type' => 'file_search_call', 'results' => [['attributes' => ['product_id' => 85]]]],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($optionalDecision)]]],
        ]], JSON_THROW_ON_ERROR)];
    }, 'sk-test');
assert_test('does not force an unsuitable optional product',
    $optional->recommend('Bere si líčení ID 85; doplněk.', 'odličovač', '', null, 85, 85)
        === ['status' => 'no_match', 'product_id' => null]);
$primaryNoMatchRejected = false;
try {
    $optional->recommend('parfém do 2000 Kč', 'parfém', 'do 2000 Kč');
} catch (OpenAiUpstreamException) {
    $primaryNoMatchRejected = true;
}
assert_test('retains mandatory selected-product rule for primary mode', $primaryNoMatchRejected);
$missingSearchRejected = false;
try {
    (new OpenAiResponsesProductRecommender($store, static function () use ($optionalDecision): array {
        return ['status' => 200, 'body' => json_encode(['status' => 'completed', 'output' => [
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($optionalDecision)]]],
        ]], JSON_THROW_ON_ERROR)];
    }, 'sk-test'))->recommend('Bere ID 85; doplněk.', 'odličovač', '', null, 85, 85);
} catch (OpenAiUpstreamException) {
    $missingSearchRejected = true;
}
assert_test('rejects optional no-match without a hosted search', $missingSearchRejected);
$optionalDecision = ['status' => 'selected', 'product_id' => 85, 'match_quality' => 'exact', 'reason' => ''];
$primaryAsAddonRejected = false;
try {
    $optional->recommend('Bere si líčení ID 85; doplněk.', 'odličovač', '', null, 85, 85);
} catch (OpenAiUpstreamException) {
    $primaryAsAddonRejected = true;
}
assert_test('rejects the primary product as its own add-on', $primaryAsAddonRejected);

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
