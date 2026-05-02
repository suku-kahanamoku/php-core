#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit/integration tests for App\Modules\Order\Order
 *
 * Tests: getAll(), getById(), create(), updateStatus(), delete()
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

$r            = request('POST', "{$base}/categories", ['name' => 'Model Order Cat']);
$modelCatId   = $r['data']['data']['id'] ?? null;

$modelSku = 'MODEL-ORD-PROD-' . time();
$r        = request('POST', "{$base}/products", [
    'name'  => 'Model Order Prod', 'sku' => $modelSku,
    'price' => 199.0, 'category_id' => $modelCatId, 'stock_quantity' => 10,
]);
$modelProdId = $r['data']['data']['id'] ?? null;

$modelUserEmail = 'model_ord_user_' . time() . '@example.com';
$r              = request('POST', "{$base}/auth/register", [
    'first_name' => 'Model', 'last_name' => 'OrdUser',
    'email'      => $modelUserEmail, 'password' => 'TestPass123',
], false);
$modelUserId = $r['data']['data']['id'] ?? null;

// ── Order model – create() ────────────────────────────────────────────────────

section('Order model – create()');
$r     = request('POST', "{$base}/auth/login", ['email' => $modelUserEmail, 'password' => 'TestPass123'], false);
$token = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/orders", [
    'items'    => [['product_id' => $modelProdId, 'quantity' => 1]],
    'currency' => 'CZK', 'payment_method' => 'card',
]);
assert_test('create order 201', $r['status'] === 201, dump_on_fail($r));
$modelOrderId = $r['data']['data']['id'] ?? null;

// ── Order model – getAll() ────────────────────────────────────────────────────

section('Order model – getAll()');
$r = request('GET', "{$base}/orders");
assert_test('returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('has items + pagination', isset($r['data']['data']['items'], $r['data']['data']['total']));

// ── Order model – getById() ───────────────────────────────────────────────────

section('Order model – getById()');
if ($modelOrderId) {
    $r = request('GET', "{$base}/orders/{$modelOrderId}");
    assert_test('getById 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has items array', isset($r['data']['data']['items']));
    assert_test('items not empty', count($r['data']['data']['items']) > 0);
}

// ── Order model – updateStatus() (admin) ─────────────────────────────────────

section('Order model – updateStatus()');
$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

if ($modelOrderId) {
    $r = request('PATCH', "{$base}/orders/{$modelOrderId}/status", ['status' => 'confirmed']);
    assert_test('update status → confirmed 200', $r['status'] === 200, dump_on_fail($r));
    $r2 = request('GET', "{$base}/orders/{$modelOrderId}");
    assert_test('status field updated', $r2['data']['data']['status'] === 'confirmed', dump_on_fail($r2));
}

// ── Order model – delete() ────────────────────────────────────────────────────

section('Order model – delete()');
if ($modelOrderId) {
    $r = request('DELETE', "{$base}/orders/{$modelOrderId}");
    assert_test('delete order 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/orders/{$modelOrderId}");
    assert_test('deleted order → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($modelProdId) {
    request('DELETE', "{$base}/products/{$modelProdId}");
}
if ($modelCatId) {
    request('DELETE', "{$base}/categories/{$modelCatId}");
}
if ($modelUserId) {
    request('DELETE', "{$base}/users/{$modelUserId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
