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
    'max_num_results' => 20,
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
assert_test(
    'uses strict minimal structured output',
    $captured['payload']['text']['format']['type'] === 'json_schema'
        && $captured['payload']['text']['format']['strict'] === true
        && $captured['payload']['max_output_tokens'] === 192,
);
assert_test('does not expose the server key in the result', !str_contains(json_encode($result), 'sk-test'));

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

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
