#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit/integration tests for App\Modules\Invoice\Invoice
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

$modelCatSlug = 'model-inv-cat-' . time();
$r            = request('POST', "{$base}/categories", ['name' => 'Model Inv Cat', 'slug' => $modelCatSlug]);
$modelCatId   = $r['data']['data']['id'] ?? null;

$modelSku = 'MODEL-INV-PROD-' . time();
$r        = request('POST', "{$base}/products", [
    'name'  => 'Model Inv Prod', 'sku' => $modelSku,
    'price' => 199.0, 'category_id' => $modelCatId, 'stock_quantity' => 10,
]);
$modelProdId = $r['data']['data']['id'] ?? null;

$modelUserEmail = 'model_inv_user_' . time() . '@example.com';
$r              = request('POST', "{$base}/auth/register", [
    'first_name' => 'Model', 'last_name' => 'InvUser',
    'email'      => $modelUserEmail, 'password' => 'TestPass123',
], false);
$modelUserId = $r['data']['data']['id'] ?? null;

$r     = request('POST', "{$base}/auth/login", ['email' => $modelUserEmail, 'password' => 'TestPass123'], false);
$token = $r['data']['data']['token'] ?? null;
$r     = request('POST', "{$base}/orders", [
    'items'    => [['product_id' => $modelProdId, 'quantity' => 1]],
    'currency' => 'CZK', 'payment_method' => 'card',
]);
$modelOrderId = $r['data']['data']['id'] ?? null;

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

// ── Invoice model – create() ──────────────────────────────────────────────────

section('Invoice model – create()');
$r = request('POST', "{$base}/invoices", ['order_id' => $modelOrderId]);
assert_test('create invoice 201', $r['status'] === 201, dump_on_fail($r));
$modelInvoiceId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/invoices", ['order_id' => $modelOrderId]);
assert_test('duplicate invoice → 409', $r['status'] === 409, dump_on_fail($r));

// ── Invoice model – getAll() ──────────────────────────────────────────────────

section('Invoice model – getAll()');
$r = request('GET', "{$base}/invoices");
assert_test('returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('has items + pagination', isset($r['data']['data']['items'], $r['data']['data']['total']));

// ── Invoice model – getById() ─────────────────────────────────────────────────

section('Invoice model – getById()');
if ($modelInvoiceId) {
    $r = request('GET', "{$base}/invoices/{$modelInvoiceId}");
    assert_test('getById 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has items array', isset($r['data']['data']['items']));
    assert_test('invoice_number set', !empty($r['data']['data']['invoice_number']));
}

// ── Invoice model – updateStatus() ───────────────────────────────────────────

section('Invoice model – updateStatus()');
if ($modelInvoiceId) {
    $r = request('PATCH', "{$base}/invoices/{$modelInvoiceId}/status", ['status' => 'paid']);
    assert_test('update status → paid 200', $r['status'] === 200, dump_on_fail($r));
}

// ── Invoice model – delete() (soft delete) ───────────────────────────────────

section('Invoice model – delete()');
if ($modelInvoiceId) {
    $r = request('DELETE', "{$base}/invoices/{$modelInvoiceId}");
    assert_test('delete invoice 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/invoices/{$modelInvoiceId}");
    assert_test('deleted invoice → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($modelOrderId) {
    request('DELETE', "{$base}/orders/{$modelOrderId}");
}
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
