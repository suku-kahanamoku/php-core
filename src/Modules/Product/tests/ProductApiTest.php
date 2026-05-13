#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API endpoint tests for App\Modules\Product\ProductApi
 *
 * Tests all /products/* routes
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

// ── Admin login + setup: create a category ───────────────────────────────────

section('Products – setup');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/categories", ['name' => 'Products Test Cat']);
assert_test('create category for products 201', $r['status'] === 201, dump_on_fail($r));
$prodCatId = $r['data']['data']['id'] ?? null;

// ── Non-admin protection ──────────────────────────────────────────────────────

section('Products – non-admin protection');
$tmpToken = $token;
$token    = null;
$r        = request('POST', "{$base}/products", ['name' => 'x', 'sku' => 'x', 'price' => 1]);
assert_test('POST /products → 401 without token', $r['status'] === 401, dump_on_fail($r));
$token = $tmpToken;

$prodRegEmail = 'prod_reg_' . time() . '@example.com';
$r            = request('POST', "{$base}/users", [
    'first_name' => 'Reg', 'last_name' => 'User',
    'email'      => $prodRegEmail, 'password' => 'Password123',
]);
$prodRegId = $r['data']['data']['id'] ?? null;
$r         = request('POST', "{$base}/auth/login", ['email' => $prodRegEmail, 'password' => 'Password123'], false);
$token     = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/products", ['name' => 'x', 'sku' => 'x', 'price' => 1]);
assert_test('POST /products → 403 for non-admin', $r['status'] === 403, dump_on_fail($r));

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;
if ($prodRegId) {
    request('DELETE', "{$base}/users/{$prodRegId}");
}

// ── Create ────────────────────────────────────────────────────────────────────

section('Products – create');
$prodSku = TEST_PREFIX . 'prod_' . time();
$r       = request('POST', "{$base}/products", [
    'name'  => 'Test Product', 'sku' => $prodSku,
    'price' => 199.0, 'category_ids' => [$prodCatId], 'stock_quantity' => 10,
    'kind'  => 'dry', 'color' => 'white', 'variant' => 'riesling',
    'data'  => ['quality' => 'late_harvest', 'volume' => 0.75, 'year' => 2022],
]);
assert_test('POST /products 201', $r['status'] === 201, dump_on_fail($r));
$prodId = $r['data']['data']['id'] ?? null;

// ── Public routes ─────────────────────────────────────────────────────────────

section('Products – public list');
$r = request('GET', "{$base}/products", [], false);
assert_test('GET /products 200 without token', $r['status'] === 200, dump_on_fail($r));
assert_test('has items array', isset($r['data']['data']));
assert_test('has total', isset($r['data']['meta']['total']));
assert_test('has page/limit/totalPages', isset($r['data']['meta']['page'], $r['data']['meta']['limit'], $r['data']['meta']['totalPages']));

section('Products – public get by id');
if ($prodId) {
    $r = request('GET', "{$base}/products/{$prodId}", [], false);
    assert_test('GET /products/:id 200 without token', $r['status'] === 200, dump_on_fail($r));
    assert_test('name matches', $r['data']['data']['name'] === 'Test Product');
    assert_test('has category_ids', isset($r['data']['data']['category_ids']));
    assert_test('kind = dry', $r['data']['data']['kind'] === 'dry', dump_on_fail($r));
    assert_test('color = white', $r['data']['data']['color'] === 'white', dump_on_fail($r));
    assert_test('variant = riesling', $r['data']['data']['variant'] === 'riesling', dump_on_fail($r));
    assert_test('data.quality = late_harvest', ($r['data']['data']['data']['quality'] ?? null) === 'late_harvest', dump_on_fail($r));
    assert_test('data.volume = 0.75', (float)($r['data']['data']['data']['volume'] ?? 0) === 0.75, dump_on_fail($r));
    assert_test('data.year = 2022', (int)($r['data']['data']['data']['year'] ?? 0) === 2022, dump_on_fail($r));

    $r = request('GET', "{$base}/products/999999", [], false);
    assert_test('404 for unknown id', $r['status'] === 404, dump_on_fail($r));
}

// ── Filter by JSON attributes ─────────────────────────────────────────────────

section('Products – filter by JSON attributes');
if ($prodId) {
    $f = urlencode(json_encode(['data.quality' => ['value' => 'late_harvest']]));
    $r = request('GET', "{$base}/products?q={$f}", [], false);
    assert_test('filter by data.quality → 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('filter data.quality returns result', ($r['data']['meta']['total'] ?? 0) >= 1, dump_on_fail($r));

    $f = urlencode(json_encode(['data.year' => ['value' => 2022]]));
    $r = request('GET', "{$base}/products?q={$f}", [], false);
    assert_test('filter by data.year → 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('filter data.year returns result', ($r['data']['meta']['total'] ?? 0) >= 1, dump_on_fail($r));

    $f = urlencode(json_encode(['color' => ['value' => 'white']]));
    $r = request('GET', "{$base}/products?q={$f}", [], false);
    assert_test('filter by color → 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('filter color returns result', ($r['data']['meta']['total'] ?? 0) >= 1, dump_on_fail($r));
}

// ── Update ────────────────────────────────────────────────────────────────────

section('Products – update');
if ($prodId) {
    $r = request('PATCH', "{$base}/products/{$prodId}", [
        'description' => 'Patched desc',
        'kind'        => 'sweet',
        'data'        => ['quality' => 'ice_wine'],
    ]);
    assert_test('PATCH /products/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('kind updated to sweet', $r['data']['data']['kind'] === 'sweet', dump_on_fail($r));
    assert_test('data.quality updated', ($r['data']['data']['data']['quality'] ?? null) === 'ice_wine', dump_on_fail($r));
    assert_test('data.volume preserved', (float)($r['data']['data']['data']['volume'] ?? 0) === 0.75, dump_on_fail($r));

    $r = request('PUT', "{$base}/products/{$prodId}", [
        'name' => 'Test Product Updated', 'sku' => $prodSku, 'price' => 249.0, 'stock_quantity' => 10,
        'kind' => 'dry', 'color' => 'red', 'variant' => 'merlot',
        'data' => ['quality' => 'quality_wine', 'volume' => 0.75, 'year' => 2021],
    ]);
    assert_test('PUT /products/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('PUT color = red', $r['data']['data']['color'] === 'red', dump_on_fail($r));

    $r = request('PUT', "{$base}/products/{$prodId}", ['name' => 'x']);
    assert_test('PUT /products/:id 422 missing sku+price', $r['status'] === 422, dump_on_fail($r));
}

// ── Stock ─────────────────────────────────────────────────────────────────────

section('Products – stock');
if ($prodId) {
    $r = request('PATCH', "{$base}/products/{$prodId}/stock", ['quantity' => 5]);
    assert_test('PATCH /products/:id/stock +5 → 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('stock_quantity = 15', $r['data']['data']['stock_quantity'] === 15, dump_on_fail($r));

    $r = request('PATCH', "{$base}/products/{$prodId}/stock", ['quantity' => -9999]);
    assert_test('PATCH /products/:id/stock insufficient → 422', $r['status'] === 422, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($prodId) {
    request('DELETE', "{$base}/products/{$prodId}");
}
if ($prodCatId) {
    request('DELETE', "{$base}/categories/{$prodCatId}");
}
$token = null;

// ── Projection ────────────────────────────────────────────────────────────────

section('Products – projection');
$r = request('GET', "{$base}/products?projection=name,price", [], false);
assert_test('projection: 200', $r['status'] === 200, dump_on_fail($r));
$firstItem = $r['data']['data'][0] ?? [];
assert_test('projection: has id (system)', isset($firstItem['id']));
assert_test('projection: has name', isset($firstItem['name']));
assert_test('projection: has price', isset($firstItem['price']));
assert_test('projection: no sku', !isset($firstItem['sku']));

$r = request('GET', "{$base}/products?projection=", [], false);
assert_test('empty projection: 200', $r['status'] === 200, dump_on_fail($r));
$firstItem = $r['data']['data'][0] ?? [];
assert_test('empty projection: has id', isset($firstItem['id']));
assert_test('empty projection: no name', !isset($firstItem['name']));

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
