#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration tests for App\Modules\Invoice\InvoiceService
 *
 * Tests business logic: admin-only creation, duplicate prevention, status validation
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

$r          = request('POST', "{$base}/categories", ['name' => 'Svc Inv Cat']);
$svcCatId   = $r['data']['data']['id'] ?? null;

$svcSku = 'SVC-INV-PROD-' . time();
$r      = request('POST', "{$base}/products", [
    'name'  => 'Svc Inv Prod', 'sku' => $svcSku,
    'price' => 199.0, 'category_id' => $svcCatId, 'stock_quantity' => 10,
]);
$svcProdId = $r['data']['data']['id'] ?? null;

$svcUserEmail = 'svc_inv_user_' . time() . '@example.com';
$r            = request('POST', "{$base}/auth/register", [
    'first_name' => 'Svc', 'last_name' => 'InvUser',
    'email'      => $svcUserEmail, 'password' => 'TestPass123',
], false);
$svcUserId = $r['data']['data']['id'] ?? null;

$r     = request('POST', "{$base}/auth/login", ['email' => $svcUserEmail, 'password' => 'TestPass123'], false);
$token = $r['data']['data']['token'] ?? null;
$r     = request('POST', "{$base}/orders", [
    'items'    => [['product_id' => $svcProdId, 'quantity' => 1]],
    'currency' => 'CZK', 'payment_method' => 'card',
]);
$svcOrderId = $r['data']['data']['id'] ?? null;

// ── InvoiceService – admin-only ───────────────────────────────────────────────

section('InvoiceService – admin-only creation');
$r = request('POST', "{$base}/invoices", ['order_id' => $svcOrderId]);
assert_test('POST /invoices → 403 for non-admin', $r['status'] === 403, dump_on_fail($r));

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

// ── InvoiceService – duplicate prevention ─────────────────────────────────────

section('InvoiceService – duplicate order prevention');
$r = request('POST', "{$base}/invoices", ['order_id' => $svcOrderId]);
assert_test('create invoice 201', $r['status'] === 201, dump_on_fail($r));
$svcInvoiceId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/invoices", ['order_id' => $svcOrderId]);
assert_test('duplicate invoice → 409', $r['status'] === 409, dump_on_fail($r));

// ── InvoiceService – updateStatus() ──────────────────────────────────────────

section('InvoiceService – updateStatus() validation');
if ($svcInvoiceId) {
    $r = request('PATCH', "{$base}/invoices/{$svcInvoiceId}/status", []);
    assert_test('missing status → 422', $r['status'] === 422, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($svcInvoiceId) {
    request('DELETE', "{$base}/invoices/{$svcInvoiceId}");
}
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
