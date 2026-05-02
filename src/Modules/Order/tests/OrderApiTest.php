#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API endpoint tests for App\Modules\Order\OrderApi
 *
 * Tests all /orders/* routes
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

// ── Setup: admin creates category + product, registers test user ──────────────

section('Orders – setup');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$ordCatSlug = 'ord-cat-' . time();
$r          = request('POST', "{$base}/categories", ['name' => 'Orders Cat', 'slug' => $ordCatSlug]);
assert_test('create category 201', $r['status'] === 201, dump_on_fail($r));
$ordCatId = $r['data']['data']['id'] ?? null;

$ordSku = 'ORD-PROD-' . time();
$r      = request('POST', "{$base}/products", [
    'name'  => 'Orders Product', 'sku' => $ordSku,
    'price' => 199.0, 'category_id' => $ordCatId, 'stock_quantity' => 10,
]);
assert_test('create product 201', $r['status'] === 201, dump_on_fail($r));
$ordProductId = $r['data']['data']['id'] ?? null;

$ordUserEmail = 'ord_user_' . time() . '@example.com';
$ordPassword  = 'TestPass123';
$r            = request('POST', "{$base}/auth/register", [
    'first_name' => 'Order', 'last_name' => 'User',
    'email'      => $ordUserEmail, 'password' => $ordPassword,
], false);
assert_test('register test user 201', $r['status'] === 201, dump_on_fail($r));
$ordUserId = $r['data']['data']['id'] ?? null;

// ── Test user creates order ───────────────────────────────────────────────────

section('Orders – test user creates order');
$r = request('POST', "{$base}/auth/login", ['email' => $ordUserEmail, 'password' => $ordPassword], false);
assert_test('test user login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/orders", [
    'items'    => [['product_id' => $ordProductId, 'quantity' => 1]],
    'currency' => 'CZK', 'payment_method' => 'card',
]);
assert_test('POST /orders 201', $r['status'] === 201, dump_on_fail($r));
$orderId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/orders", ['items' => []]);
assert_test('POST /orders 422 empty items', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/orders", [
    'items' => [['product_id' => $ordProductId, 'quantity' => 99999]],
]);
assert_test('POST /orders 422 insufficient stock', $r['status'] === 422, dump_on_fail($r));

section('Orders – list & get');
$r = request('GET', "{$base}/orders");
assert_test('GET /orders 200', $r['status'] === 200, dump_on_fail($r));
assert_test('has items array', isset($r['data']['data']['items']));

if ($orderId) {
    $r = request('GET', "{$base}/orders/{$orderId}");
    assert_test('GET /orders/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has items array', isset($r['data']['data']['items']));
    assert_test('items not empty', count($r['data']['data']['items']) > 0);
}

// ── Admin manages status ──────────────────────────────────────────────────────

section('Orders – admin manages status');
$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

if ($orderId) {
    $r = request('PATCH', "{$base}/orders/{$orderId}/status", ['status' => 'confirmed']);
    assert_test('PATCH /orders/:id/status → confirmed 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PATCH', "{$base}/orders/{$orderId}/status", []);
    assert_test('PATCH /orders/:id/status 422 missing status', $r['status'] === 422, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($orderId) {
    request('DELETE', "{$base}/orders/{$orderId}");
}
if ($ordProductId) {
    request('DELETE', "{$base}/products/{$ordProductId}");
}
if ($ordCatId) {
    request('DELETE', "{$base}/categories/{$ordCatId}");
}
if ($ordUserId) {
    request('DELETE', "{$base}/users/{$ordUserId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
