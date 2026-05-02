#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration tests for App\Modules\Category\CategoryService
 *
 * Tests business logic: validation, deletion blocked by products
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

$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

// ── CategoryService – validation ─────────────────────────────────────────────

section('CategoryService – create() validation');
$r = request('POST', "{$base}/categories", ['name' => '']);
assert_test('empty name → 422', $r['status'] === 422, dump_on_fail($r));

$r = request('PUT', "{$base}/categories/1", ['name' => '']);
assert_test('PUT empty name → 422', $r['status'] === 422, dump_on_fail($r));

// ── CategoryService – cannot delete if has products ──────────────────────────

section('CategoryService – delete blocked by products');
$r          = request('POST', "{$base}/categories", ['name' => 'Svc Category']);
assert_test('create category 201', $r['status'] === 201, dump_on_fail($r));
$svcCatId = $r['data']['data']['id'] ?? null;

if ($svcCatId) {
    $svcProdSku = 'SVC-CAT-PROD-' . time();
    $r          = request('POST', "{$base}/products", [
        'name'  => 'Svc Cat Product', 'sku' => $svcProdSku,
        'price' => 10.0, 'category_id' => $svcCatId, 'stock_quantity' => 1,
    ]);
    assert_test('create product in category 201', $r['status'] === 201, dump_on_fail($r));
    $svcProdId = $r['data']['data']['id'] ?? null;

    $r = request('DELETE', "{$base}/categories/{$svcCatId}");
    assert_test('delete category with products → 409', $r['status'] === 409, dump_on_fail($r));

    if ($svcProdId) {
        request('DELETE', "{$base}/products/{$svcProdId}");
    }
    $r = request('DELETE', "{$base}/categories/{$svcCatId}");
    assert_test('delete category after products removed → 200', $r['status'] === 200, dump_on_fail($r));
}

// ── CategoryService – admin-only writes ──────────────────────────────────────

section('CategoryService – admin-only writes');
$token = null;
$r     = request('POST', "{$base}/categories", ['name' => 'x']);
assert_test('POST /categories → 401 without token', $r['status'] === 401, dump_on_fail($r));

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
