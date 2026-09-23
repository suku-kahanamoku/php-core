#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\OpenAi\OpenAiCatalogGateway;
use App\Modules\OpenAi\OpenAiProductDocumentBuilder;
use App\Modules\OpenAi\OpenAiVectorStoreClient;
use App\Modules\OpenAi\OpenAiVectorStoreGateway;
use App\Modules\OpenAi\OpenAiVectorStoreSyncService;

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/../../../../tests/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}
require_once __DIR__ . '/../../../../vendor/autoload.php';

section('OpenAI Vector Store');
$calls = [];
$fileNumber = 0;
$client = new OpenAiVectorStoreClient(
    static function (string $method, string $path, array $payload, bool $multipart) use (&$calls, &$fileNumber): array {
        $calls[] = [$method, $path, $payload, $multipart];
        if ($method === 'POST' && $path === '/vector_stores') {
            return ['status' => 200, 'body' => '{"id":"vs_test"}'];
        }
        if ($method === 'POST' && $path === '/files') {
            $fileNumber++;
            return ['status' => 200, 'body' => json_encode(['id' => 'file_' . $fileNumber])];
        }
        if ($method === 'GET' && str_contains($path, '/files/file_')) {
            return ['status' => 200, 'body' => '{"status":"completed"}'];
        }
        return ['status' => 200, 'body' => '{"id":"ok","deleted":true}'];
    },
    'sk-test',
);

$catalog = new class implements OpenAiCatalogGateway {
    public array $products = [[
        'id' => 1, 'sku' => 'A', 'name' => 'Vůně', 'description' => 'Svěží vůně',
        'price_with_vat' => 100.0, 'stock_quantity' => 1,
        'data' => ['brand' => 'Test', 'currency' => 'CZK', 'selection_attributes' => ['character' => ['svěží']]],
        'categories' => [['name' => 'Fragrances']],
    ], [
        'id' => 2, 'sku' => 'B', 'name' => 'Krém', 'description' => 'Hydratační krém',
        'price_with_vat' => 200.0, 'stock_quantity' => 1,
        'data' => ['brand' => 'Test', 'currency' => 'CZK', 'selection_attributes' => ['need' => ['hydratace']]],
        'categories' => [['name' => 'Skin Care']],
    ]];
    public function publishedProfiles(): iterable { return []; }
    public function publishedProducts(): iterable { yield from $this->products; }
    public function publishedProduct(int $productId): ?array
    {
        foreach ($this->products as $product) {
            if ((int) $product['id'] === $productId) {
                return $product;
            }
        }
        return null;
    }
};
$gateway = new class implements OpenAiVectorStoreGateway {
    public ?array $stored = null;
    public array $mappings = [];
    public function store(): ?array { return $this->stored; }
    public function saveStore(string $vectorStoreId, string $name): void
    {
        $this->stored = ['vector_store_id' => $vectorStoreId, 'name' => $name];
    }
    public function productMappings(): array { return $this->mappings; }
    public function saveProductMapping(int $productId, string $vectorStoreId, string $fileId, string $sourceHash): void
    {
        $this->mappings[$productId] = compact('productId', 'vectorStoreId', 'fileId', 'sourceHash') + [
            'product_id' => $productId, 'vector_store_id' => $vectorStoreId,
            'openai_file_id' => $fileId, 'source_hash' => $sourceHash,
        ];
    }
    public function deleteProductMapping(int $productId): void { unset($this->mappings[$productId]); }
};
$sync = new OpenAiVectorStoreSyncService(
    $catalog,
    $gateway,
    $client,
    new OpenAiProductDocumentBuilder(),
    'fun',
);
$first = $sync->sync();
assert_test('creates one tenant vector store', $first['vector_store_id'] === 'vs_test');
assert_test('indexes every published product', $first['created'] === 2 && count($gateway->mappings) === 2);
$second = $sync->sync();
assert_test('skips unchanged product documents', $second['unchanged'] === 2 && $second['created'] === 0);

$document = (new OpenAiProductDocumentBuilder())->build($catalog->products[1], 'fun');
assert_test('product document contains verifiable ID and attributes', str_contains($document, '"product_id": 2') && str_contains($document, 'hydratace'));
assert_test('product document contains exact VAT price and currency', str_contains($document, '"amount_with_vat": 200') && str_contains($document, '"currency": "CZK"'));

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
