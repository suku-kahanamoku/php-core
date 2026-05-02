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

$r           = request('POST', "{$base}/categories", ['name' => 'Products Test Cat']);
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
$prodSku = 'PROD-' . time();
$r       = request('POST', "{$base}/products", [
    'name'  => 'Test Product', 'sku' => $prodSku,
    'price' => 199.0, 'category_id' => $prodCatId, 'stock_quantity' => 10,
]);
assert_test('POST /products 201', $r['status'] === 201, dump_on_fail($r));
$prodId = $r['data']['data']['id'] ?? null;

// ── Public routes ─────────────────────────────────────────────────────────────

section('Products – public list');
$r = request('GET', "{$base}/products", [], false);
assert_test('GET /products 200 without token', $r['status'] === 200, dump_on_fail($r));
assert_test('has items array', isset($r['data']['data']['items']));
assert_test('has total', isset($r['data']['data']['total']));
assert_test('has page/limit/totalPages', isset($r['data']['data']['page'], $r['data']['data']['limit'], $r['data']['data']['totalPages']));

section('Products – public get by id');
if ($prodId) {
    $r = request('GET', "{$base}/products/{$prodId}", [], false);
    assert_test('GET /products/:id 200 without token', $r['status'] === 200, dump_on_fail($r));
    assert_test('name matches', $r['data']['data']['name'] === 'Test Product');
    assert_test('has category_name', isset($r['data']['data']['category_name']));

    $r = request('GET', "{$base}/products/999999", [], false);
    assert_test('404 for unknown id', $r['status'] === 404, dump_on_fail($r));
}

// ── Update ────────────────────────────────────────────────────────────────────

section('Products – update');
if ($prodId) {
    $r = request('PATCH', "{$base}/products/{$prodId}", ['description' => 'Patched desc']);
    assert_test('PATCH /products/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/products/{$prodId}", [
        'name' => 'Test Product Updated', 'sku' => $prodSku, 'price' => 249.0, 'stock_quantity' => 10,
    ]);
    assert_test('PUT /products/:id 200', $r['status'] === 200, dump_on_fail($r));

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

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
