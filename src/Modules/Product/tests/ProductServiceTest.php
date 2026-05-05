#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration tests for App\Modules\Product\ProductService
 *
 * Tests business logic: validation, duplicate SKU, stock validation
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

$r          = request('POST', "{$base}/categories", ['name' => 'Svc Prod Cat']);
$svcCatId   = $r['data']['data']['id'] ?? null;

// ── ProductService – validation ───────────────────────────────────────────────

section('ProductService – create() validation');
$r = request('POST', "{$base}/products", ['name' => 'x']);
assert_test('missing sku + price → 422', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/products", ['name' => '', 'sku' => 'x', 'price' => 10]);
assert_test('empty name → 422', $r['status'] === 422, dump_on_fail($r));

// ── ProductService – create() success ────────────────────────────────────────

section('ProductService – create() success');
$svcSku = 'SVC-PROD-' . time();
$r      = request('POST', "{$base}/products", [
    'name'  => 'Svc Product', 'sku' => $svcSku,
    'price' => 100.0, 'category_ids' => [$svcCatId], 'stock_quantity' => 10,
]);
assert_test('valid product → 201', $r['status'] === 201, dump_on_fail($r));
$svcProdId = $r['data']['data']['id'] ?? null;

// ── ProductService – updateStock() validation ────────────────────────────────

section('ProductService – updateStock() validation');
if ($svcProdId) {
    $r = request('PATCH', "{$base}/products/{$svcProdId}/stock", ['quantity' => -9999]);
    assert_test('stock below zero → 422', $r['status'] === 422, dump_on_fail($r));

    $r = request('PATCH', "{$base}/products/{$svcProdId}/stock", []);
    assert_test('missing quantity → 422', $r['status'] === 422, dump_on_fail($r));
}

// ── ProductService – admin-only writes ───────────────────────────────────────

section('ProductService – admin-only writes');
$tmpToken = $token;
$token    = null;
$r        = request('POST', "{$base}/products", ['name' => 'x', 'sku' => 'x', 'price' => 1]);
assert_test('POST /products → 401 without token', $r['status'] === 401, dump_on_fail($r));
$token = $tmpToken;

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($svcProdId) {
    request('DELETE', "{$base}/products/{$svcProdId}");
}
if ($svcCatId) {
    request('DELETE', "{$base}/categories/{$svcCatId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
