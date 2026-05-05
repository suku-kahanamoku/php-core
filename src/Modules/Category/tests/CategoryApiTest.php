#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API endpoint tests for App\Modules\Category\CategoryApi
 *
 * Tests all /categories/* routes
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

// ── Public routes ─────────────────────────────────────────────────────────────

section('Categories – public list');
$r = request('GET', "{$base}/categories", [], false);
assert_test('GET /categories 200', $r['status'] === 200, dump_on_fail($r));
assert_test('data is array', is_array($r['data']['data']));

// ── Admin login ───────────────────────────────────────────────────────────────

section('Categories – admin login');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

// ── Non-admin protection ──────────────────────────────────────────────────────

section('Categories – non-admin protection');
$tmpToken = $token;
$token    = null;
$r        = request('POST', "{$base}/categories", ['name' => 'x']);
assert_test('POST /categories → 401 without token', $r['status'] === 401, dump_on_fail($r));
$token = $tmpToken;

$catRegEmail = TEST_PREFIX . 'cat_reg_' . time() . '@example.com';
$r           = request('POST', "{$base}/users", [
    'first_name' => 'Reg', 'last_name' => 'User',
    'email'      => $catRegEmail, 'password' => 'Password123',
]);
$catRegId = $r['data']['data']['id'] ?? null;
$r        = request('POST', "{$base}/auth/login", ['email' => $catRegEmail, 'password' => 'Password123'], false);
$token    = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/categories", ['name' => 'x']);
assert_test('POST /categories → 403 for non-admin', $r['status'] === 403, dump_on_fail($r));

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
$token = $r['data']['data']['token'] ?? null;
if ($catRegId) {
    request('DELETE', "{$base}/users/{$catRegId}");
}

// ── Create ────────────────────────────────────────────────────────────────────

section('Categories – create');

$r = request('POST', "{$base}/categories", ['name' => TEST_PREFIX . 'category_' . time()]);
assert_test('POST /categories 201', $r['status'] === 201, dump_on_fail($r));
$catId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/categories", ['name' => TEST_PREFIX . 'category_empty_' . time()]);
assert_test('POST /categories 201 (empty)', $r['status'] === 201, dump_on_fail($r));
$emptyCatId = $r['data']['data']['id'] ?? null;

// ── Public: get by id ─────────────────────────────────────────────────────────

section('Categories – public get by id');
if ($catId) {
    $r = request('GET', "{$base}/categories/{$catId}", [], false);
    assert_test('GET /categories/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has products array', isset($r['data']['data']['products']));
}

// ── Update ────────────────────────────────────────────────────────────────────

section('Categories – update');
if ($catId) {
    $r = request('PATCH', "{$base}/categories/{$catId}", ['description' => 'Updated desc']);
    assert_test('PATCH /categories/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/categories/{$catId}", ['name' => TEST_PREFIX . 'category_upd_' . time()]);
    assert_test('PUT /categories/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/categories/{$catId}", ['name' => '']);
    assert_test('PUT /categories/:id 422 empty name', $r['status'] === 422, dump_on_fail($r));
}

// ── Delete: 409 when has products ─────────────────────────────────────────────

section('Categories – delete with product → 409');
if ($catId) {
    $catProdSku = TEST_PREFIX . 'cat_prod_' . time();
    $r          = request('POST', "{$base}/products", [
        'name'  => 'Cat Test Product', 'sku' => $catProdSku,
        'price' => 10.0, 'category_ids' => [$catId], 'stock_quantity' => 1,
    ]);
    $catProdId = $r['data']['data']['id'] ?? null;

    $r = request('DELETE', "{$base}/categories/{$catId}");
    assert_test('DELETE /categories/:id 409 (has product)', $r['status'] === 409, dump_on_fail($r));

    if ($catProdId) {
        request('DELETE', "{$base}/products/{$catProdId}");
    }
    $r = request('DELETE', "{$base}/categories/{$catId}");
    assert_test('DELETE /categories/:id 200 (after product removed)', $r['status'] === 200, dump_on_fail($r));
}

if ($emptyCatId) {
    $r = request('DELETE', "{$base}/categories/{$emptyCatId}");
    assert_test('DELETE /categories/:id 200 (empty)', $r['status'] === 200, dump_on_fail($r));
}

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
