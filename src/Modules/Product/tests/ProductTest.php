#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit/integration tests for App\Modules\Product\Product
 *
 * Tests: getAll(), getById(), create(), update(), updateStock(), delete()
 */

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/../../../../tests/bootstrap.php';
}
if (!isset($base)) {
    $base = rtrim($argv[1] ?? 'http://localhost/php/php-core/api', '/');
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}
$token = null;

$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$r            = request('POST', "{$base}/categories", ['name' => 'Model Prod Cat']);
$modelCatId   = $r['data']['data']['id'] ?? null;

// ── Product model – create() ──────────────────────────────────────────────────

section('Product model – create()');
$modelSku = TEST_PREFIX . 'model_prod_' . time();
$r        = request('POST', "{$base}/products", [
    'name'         => 'Model Product', 'sku' => $modelSku,
    'price'        => 299.0, 'category_id' => $modelCatId, 'stock_quantity' => 5,
    'kind'         => 'dry', 'color' => 'white', 'variant' => 'chardonnay',
    'data'         => ['quality' => 'kabinett', 'volume' => 0.75, 'year' => 2023],
]);
assert_test('create product 201', $r['status'] === 201, dump_on_fail($r));
$modelProdId = $r['data']['data']['id'] ?? null;

// ── Product model – getAll() (public) ────────────────────────────────────────

section('Product model – getAll()');
$r = request('GET', "{$base}/products", [], false);
assert_test('returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('has items + total', isset($r['data']['data']['items'], $r['data']['data']['total']));
assert_test('has pagination', isset($r['data']['data']['page'], $r['data']['data']['totalPages']));

// ── Product model – getById() (public) ───────────────────────────────────────

section('Product model – getById()');
if ($modelProdId) {
    $r = request('GET', "{$base}/products/{$modelProdId}", [], false);
    assert_test('getById 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('name matches', $r['data']['data']['name'] === 'Model Product', dump_on_fail($r));
    assert_test('has category_names', isset($r['data']['data']['category_names']));
    assert_test('stock_quantity = 5', $r['data']['data']['stock_quantity'] === 5);
    assert_test('kind = dry', $r['data']['data']['kind'] === 'dry', dump_on_fail($r));
    assert_test('color = white', $r['data']['data']['color'] === 'white', dump_on_fail($r));
    assert_test('variant = chardonnay', $r['data']['data']['variant'] === 'chardonnay', dump_on_fail($r));
    assert_test('data.quality = kabinett', ($r['data']['data']['data']['quality'] ?? null) === 'kabinett', dump_on_fail($r));
    assert_test('data.year = 2023', (int)($r['data']['data']['data']['year'] ?? 0) === 2023, dump_on_fail($r));

    $r = request('GET', "{$base}/products/999999", [], false);
    assert_test('unknown id → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Product model – update() ─────────────────────────────────────────────────

section('Product model – update()');
if ($modelProdId) {
    $r = request('PATCH', "{$base}/products/{$modelProdId}", [
        'description' => 'Model desc',
        'kind'        => 'sweet',
        'data'        => ['quality' => 'late_harvest'],
    ]);
    assert_test('PATCH product 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('kind updated', $r['data']['data']['kind'] === 'sweet', dump_on_fail($r));
    assert_test('data.quality updated', ($r['data']['data']['data']['quality'] ?? null) === 'late_harvest', dump_on_fail($r));
    assert_test('data.year preserved', (int)($r['data']['data']['data']['year'] ?? 0) === 2023, dump_on_fail($r));

    $r = request('PUT', "{$base}/products/{$modelProdId}", [
        'name' => 'Model Product Updated', 'sku' => $modelSku, 'price' => 349.0, 'stock_quantity' => 5,
        'kind' => 'dry', 'color' => 'red', 'data' => ['quality' => 'quality_wine', 'volume' => 0.75, 'year' => 2021],
    ]);
    assert_test('PUT product 200', $r['status'] === 200, dump_on_fail($r));
}

// ── Product model – updateStock() ────────────────────────────────────────────

section('Product model – updateStock()');
if ($modelProdId) {
    $r = request('PATCH', "{$base}/products/{$modelProdId}/stock", ['quantity' => 3]);
    assert_test('stock +3 → 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('stock_quantity = 8', $r['data']['data']['stock_quantity'] === 8, dump_on_fail($r));

    $r = request('PATCH', "{$base}/products/{$modelProdId}/stock", ['quantity' => -9999]);
    assert_test('insufficient stock → 422', $r['status'] === 422, dump_on_fail($r));
}

// ── Product model – delete() (soft delete) ───────────────────────────────────

section('Product model – delete()');
if ($modelProdId) {
    $r = request('DELETE', "{$base}/products/{$modelProdId}");
    assert_test('delete product 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/products/{$modelProdId}", [], false);
    assert_test('deleted product → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($modelCatId) {
    request('DELETE', "{$base}/categories/{$modelCatId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
