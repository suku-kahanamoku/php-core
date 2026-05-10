#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration tests for App\Modules\Order\OrderService
 *
 * Tests business logic: empty items, insufficient stock, status validation
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

// ── Setup ─────────────────────────────────────────────────────────────────────

$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$r        = request('POST', "{$base}/categories", ['name' => 'Svc Order Cat']);
$svcCatId = $r['data']['data']['id'] ?? null;

$svcSku = 'SVC-ORD-PROD-' . time();
$r      = request('POST', "{$base}/products", [
    'name'  => 'Svc Order Prod', 'sku' => $svcSku,
    'price' => 199.0, 'category_id' => $svcCatId, 'stock_quantity' => 5,
]);
$svcProdId = $r['data']['data']['id'] ?? null;

$svcUserEmail = 'svc_ord_user_' . time() . '@example.com';
$r            = request('POST', "{$base}/auth/register", [
    'first_name' => 'Svc', 'last_name' => 'OrdUser',
    'email'      => $svcUserEmail, 'password' => 'TestPass123',
], false);
$svcUserId = $r['data']['data']['id'] ?? null;
$r         = request('POST', "{$base}/auth/login", ['email' => $svcUserEmail, 'password' => 'TestPass123'], false);
$token     = $r['data']['data']['token'] ?? null;

// ── OrderService – validation ─────────────────────────────────────────────────

section('OrderService – create() validation');
$r = request('POST', "{$base}/orders", ['carts' => []]);
assert_test('empty items → 422', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/orders", [
    'carts' => [['product_id' => $svcProdId, 'quantity' => 99999]],
]);
assert_test('insufficient stock → 422', $r['status'] === 422, dump_on_fail($r));

// ── OrderService – status validation ─────────────────────────────────────────

section('OrderService – updateStatus() validation');
$r = request('POST', "{$base}/orders", [
    'carts'   => [['product_id' => $svcProdId, 'quantity' => 1]],
    'billing' => ['value' => 'card'],
]);
assert_test('create order 201', $r['status'] === 201, dump_on_fail($r));
$svcOrderId = $r['data']['data']['id'] ?? null;

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

if ($svcOrderId) {
    $r = request('PATCH', "{$base}/orders/{$svcOrderId}/status", []);
    assert_test('missing status → 422', $r['status'] === 422, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($svcOrderId) {
    request('DELETE', "{$base}/orders/{$svcOrderId}");
}
if ($svcProdId) {
    request('DELETE', "{$base}/products/{$svcProdId}");
}
if ($svcCatId) {
    request('DELETE', "{$base}/categories/{$svcCatId}");
}
if ($svcUserId) {
    request('DELETE', "{$base}/users/{$svcUserId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
