#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API endpoint tests for App\Modules\Invoice\InvoiceApi
 *
 * Tests all /invoices/* routes
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

// ── Setup: admin creates category + product + order ───────────────────────────

section('Invoices – setup');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$r          = request('POST', "{$base}/categories", ['name' => 'Invoices Cat']);
assert_test('create category 201', $r['status'] === 201, dump_on_fail($r));
$invCatId = $r['data']['data']['id'] ?? null;

$invSku = TEST_PREFIX . 'inv_prod_' . time();
$r      = request('POST', "{$base}/products", [
    'name'  => 'Invoices Product', 'sku' => $invSku,
    'price' => 199.0, 'category_id' => $invCatId, 'stock_quantity' => 10,
]);
assert_test('create product 201', $r['status'] === 201, dump_on_fail($r));
$invProductId = $r['data']['data']['id'] ?? null;

$invUserEmail = TEST_PREFIX . 'inv_user_' . time() . '@example.com';
$invPassword  = 'TestPass123';
$r            = request('POST', "{$base}/auth/register", [
    'first_name' => 'Inv', 'last_name' => 'User',
    'email'      => $invUserEmail, 'password' => $invPassword,
], false);
assert_test('register test user 201', $r['status'] === 201, dump_on_fail($r));
$invUserId = $r['data']['data']['id'] ?? null;

$r     = request('POST', "{$base}/auth/login", ['email' => $invUserEmail, 'password' => $invPassword], false);
$token = $r['data']['data']['token'] ?? null;
$r     = request('POST', "{$base}/orders", [
    'items'    => [['product_id' => $invProductId, 'quantity' => 1]],
    'currency' => 'CZK', 'payment_method' => 'card',
]);
assert_test('create order 201', $r['status'] === 201, dump_on_fail($r));
$invOrderId = $r['data']['data']['id'] ?? null;

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
$token = $r['data']['data']['token'] ?? null;

// ── Non-admin protection ──────────────────────────────────────────────────────

section('Invoices – non-admin protection');
$tmpToken = $token;
$r        = request('POST', "{$base}/auth/login", ['email' => $invUserEmail, 'password' => $invPassword], false);
$token    = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/invoices", ['order_id' => $invOrderId]);
assert_test('POST /invoices → 403 for non-admin', $r['status'] === 403, dump_on_fail($r));

$token = $tmpToken;

// ── List ──────────────────────────────────────────────────────────────────────

section('Invoices – list');
$r = request('GET', "{$base}/invoices");
assert_test('GET /invoices 200', $r['status'] === 200, dump_on_fail($r));
assert_test('has items array', isset($r['data']['data']['items']));

// ── Create ────────────────────────────────────────────────────────────────────

section('Invoices – CRUD');
$invoiceId = null;
if ($invOrderId) {
    $r = request('POST', "{$base}/invoices", ['order_id' => $invOrderId]);
    assert_test('POST /invoices 201', $r['status'] === 201, dump_on_fail($r));
    $invoiceId = $r['data']['data']['id'] ?? null;

    $r = request('POST', "{$base}/invoices", ['order_id' => $invOrderId]);
    assert_test('POST /invoices 409 duplicate', $r['status'] === 409, dump_on_fail($r));
}

if ($invoiceId) {
    $r = request('GET', "{$base}/invoices/{$invoiceId}");
    assert_test('GET /invoices/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has items array', isset($r['data']['data']['items']));
    assert_test('invoice_number set', !empty($r['data']['data']['invoice_number']));

    $r = request('PATCH', "{$base}/invoices/{$invoiceId}/status", ['status' => 'paid']);
    assert_test('PATCH /invoices/:id/status paid 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('DELETE', "{$base}/invoices/{$invoiceId}");
    assert_test('DELETE /invoices/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/invoices/{$invoiceId}");
    assert_test('GET /invoices/:id 404 after delete', $r['status'] === 404, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($invOrderId) {
    request('DELETE', "{$base}/orders/{$invOrderId}");
}
if ($invProductId) {
    request('DELETE', "{$base}/products/{$invProductId}");
}
if ($invCatId) {
    request('DELETE', "{$base}/categories/{$invCatId}");
}
if ($invUserId) {
    request('DELETE', "{$base}/users/{$invUserId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
