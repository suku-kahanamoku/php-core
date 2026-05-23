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
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/categories", ['name' => 'Invoices Cat']);
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
    'carts'   => [['product_id' => $invProductId, 'quantity' => 1]],
    'billing' => ['value' => 'card'],
]);
assert_test('create order 201', $r['status'] === 201, dump_on_fail($r));
$invOrderId = $r['data']['data']['id'] ?? null;

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

// ── Non-admin protection ──────────────────────────────────────────────────────

// ── Non-admin can also create invoice ───────────────────────────────────────

section('Invoices – non-admin can create');
$tmpToken = $token;
$r        = request('POST', "{$base}/auth/login", ['email' => $invUserEmail, 'password' => $invPassword], false);
$token    = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/invoices", [
    'order_id' => $invOrderId,
    'status'   => 'issued',
]);
assert_test('POST /invoices → 201 for non-admin', $r['status'] === 201, dump_on_fail($r));

$token = $tmpToken;

// ── List ──────────────────────────────────────────────────────────────────────

section('Invoices – list');
$r = request('GET', "{$base}/invoices");
assert_test('GET /invoices 200', $r['status'] === 200, dump_on_fail($r));
assert_test('has items array', isset($r['data']['data']));

// ── Create ────────────────────────────────────────────────────────────────────

section('Invoices – CRUD');
$invoiceId = null;
if ($invOrderId) {
    $invPayload = [
        'order_id' => $invOrderId,
        'status'   => 'issued',
    ];
    $r = request('POST', "{$base}/invoices", $invPayload);
    assert_test('POST /invoices 201', $r['status'] === 201, dump_on_fail($r));
    $invoiceId = $r['data']['data']['id'] ?? null;

    $r = request('POST', "{$base}/invoices", $invPayload);
    assert_test('duplicate invoice → 201 (multiple allowed)', $r['status'] === 201, dump_on_fail($r));
}

if ($invoiceId) {
    $r = request('GET', "{$base}/invoices/{$invoiceId}");
    assert_test('GET /invoices/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has items array', isset($r['data']['data']));
    assert_test('invoice_number set', !empty($r['data']['data']['invoice_number']));

    $r = request('PATCH', "{$base}/invoices/{$invoiceId}/status", ['status' => 'paid']);
    assert_test('PATCH /invoices/:id/status paid 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('DELETE', "{$base}/invoices/{$invoiceId}?force=true");
    assert_test('DELETE /invoices/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/invoices/{$invoiceId}");
    assert_test('GET /invoices/:id 404 after delete', $r['status'] === 404, dump_on_fail($r));
}

// ── Files projection ──────────────────────────────────────────────────────────

section('Invoices – files projection setup: upload + commit file');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

$invFileTmp = tempnam(sys_get_temp_dir(), 'phpcore_inv_file_') . '.txt';
file_put_contents($invFileTmp, 'invoice file projection test content');

$ch = curl_init("{$base}/files/upload");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => new CURLFile($invFileTmp, 'text/plain', 'inv_proj_test.txt')]);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
$raw = curl_exec($ch);
$invUploadStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$invUploadData = json_decode($raw, true) ?? [];
assert_test('inv files projection: upload → 201', $invUploadStatus === 201, json_encode($invUploadData));
$invFileTempPath = $invUploadData['data']['path'] ?? null;

$invFileId = null;
if ($invFileTempPath) {
    $r = request('POST', "{$base}/files/commit", [
        'path'        => $invFileTempPath,
        'name'        => TEST_PREFIX . 'inv_proj_file_' . time() . '.txt',
        'entity_type' => 'invoice',
    ]);
    assert_test('inv files projection: commit → 200', $r['status'] === 200, dump_on_fail($r));
    $invFileId = $r['data']['data']['id'] ?? null;
}

section('Invoices – files projection setup: create invoice with file');
$invFilesInvoiceId = null;
if ($invFileId && $invOrderId2 = null) {
    // Vytvorim novou objednavku pro tento test (invOrderId uz je smazan)
}

// Vytvorim novou objednavku + fakturu pro file projection test
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

$invFileSku = TEST_PREFIX . 'inv_file_proj_' . time();
$r = request('POST', "{$base}/products", [
    'name' => TEST_PREFIX . 'inv_file_proj_prod', 'sku' => $invFileSku, 'price' => 5.0, 'stock_quantity' => 5,
]);
$invFileProdId = $r['data']['data']['id'] ?? null;

$invFileUserEmail = TEST_PREFIX . 'inv_file_proj_' . time() . '@example.com';
$r = request('POST', "{$base}/auth/register", [
    'first_name' => 'InvFile', 'last_name' => 'User',
    'email' => $invFileUserEmail, 'password' => 'TestPass123',
], false);
$invFileUserId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

$invFileOrderId = null;
if ($invFileProdId && $invFileUserId) {
    // Pro objednavku musime byt prihlaseni jako invFileUser
    $r = request('POST', "{$base}/auth/login", ['email' => $invFileUserEmail, 'password' => 'TestPass123'], false);
    $token = $r['data']['data']['token'] ?? null;

    $r = request('POST', "{$base}/orders", [
        'carts'   => [['product_id' => $invFileProdId, 'quantity' => 1]],
        'billing' => ['value' => 'card'],
    ]);
    assert_test('inv files projection: create order → 201', $r['status'] === 201, dump_on_fail($r));
    $invFileOrderId = $r['data']['data']['id'] ?? null;

    $r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
    $token = $r['data']['data']['token'] ?? null;
}

if ($invFileOrderId && $invFileId) {
    $r = request('POST', "{$base}/invoices", [
        'order_id'  => $invFileOrderId,
        'status'    => 'issued',
    ]);
    assert_test('inv files projection: create invoice with file → 201', $r['status'] === 201, dump_on_fail($r));
    $invFilesInvoiceId = $r['data']['data']['id'] ?? null;

    // Prirad soubor k fakture pres PATCH /invoices/:id/files
    if ($invFilesInvoiceId) {
        $r = request('PATCH', "{$base}/invoices/{$invFilesInvoiceId}/files", [
            'file_ids' => [$invFileId],
        ]);
        assert_test('inv files projection: sync file → 200', $r['status'] === 200, dump_on_fail($r));
    }
}

section('Invoices – files projection in getById');
if ($invFilesInvoiceId && $invFileId) {
    $r = request('GET', "{$base}/invoices/{$invFilesInvoiceId}?projection=id,status,files");
    assert_test('GET /invoices/:id?projection=files → 200', $r['status'] === 200, dump_on_fail($r));
    $data = $r['data']['data'] ?? [];
    assert_test('getById: files field is array', is_array($data['files'] ?? null), dump_on_fail($r));
    assert_test('getById: file_ids field is array', is_array($data['file_ids'] ?? null), dump_on_fail($r));
    assert_test('getById: files count = 1', count($data['files'] ?? []) === 1, dump_on_fail($r));
    $file = $data['files'][0] ?? [];
    assert_test('getById: file id matches', ($file['id'] ?? null) === $invFileId, dump_on_fail($r));
    assert_test('getById: file has name', isset($file['name']), dump_on_fail($r));
    assert_test('getById: file_ids contains file id', in_array($invFileId, $data['file_ids'] ?? [], true), dump_on_fail($r));
}

section('Invoices – no files when not in projection');
if ($invFilesInvoiceId) {
    $r = request('GET', "{$base}/invoices/{$invFilesInvoiceId}?projection=id,status,total_price");
    assert_test('GET /invoices/:id without files projection → 200', $r['status'] === 200, dump_on_fail($r));
    $data = $r['data']['data'] ?? [];
    assert_test('no files field when not projected', !isset($data['files']), dump_on_fail($r));
    assert_test('no file_ids field when not projected', !isset($data['file_ids']), dump_on_fail($r));
}

section('Invoices – files in list');
if ($invFilesInvoiceId && $invFileId) {
    $f = urlencode(json_encode(['id' => ['value' => $invFilesInvoiceId]]));
    $r = request('GET', "{$base}/invoices?q={$f}&projection=id,status,files");
    assert_test('GET /invoices?projection=files → 200', $r['status'] === 200, dump_on_fail($r));
    $item = $r['data']['data'][0] ?? [];
    assert_test('list: files field is array', is_array($item['files'] ?? null), dump_on_fail($r));
    assert_test('list: file_ids field is array', is_array($item['file_ids'] ?? null), dump_on_fail($r));
    assert_test('list: files count = 1', count($item['files'] ?? []) === 1, dump_on_fail($r));
    assert_test('list: file_ids contains file id', in_array($invFileId, $item['file_ids'] ?? [], true), dump_on_fail($r));
}

section('Invoices – no files in list when not in projection');
if ($invFilesInvoiceId) {
    $f = urlencode(json_encode(['id' => ['value' => $invFilesInvoiceId]]));
    $r = request('GET', "{$base}/invoices?q={$f}&projection=id,status,total_price");
    assert_test('GET /invoices list without files projection → 200', $r['status'] === 200, dump_on_fail($r));
    $item = $r['data']['data'][0] ?? [];
    assert_test('list: no files field when not projected', !isset($item['files']), dump_on_fail($r));
    assert_test('list: no file_ids field when not projected', !isset($item['file_ids']), dump_on_fail($r));
}

if ($invFilesInvoiceId) {
    request('DELETE', "{$base}/invoices/{$invFilesInvoiceId}?force=true");
}
if ($invFileOrderId) {
    request('DELETE', "{$base}/orders/{$invFileOrderId}?force=true");
}
if ($invFileProdId) {
    request('DELETE', "{$base}/products/{$invFileProdId}?force=true");
}
if ($invFileUserId) {
    request('DELETE', "{$base}/users/{$invFileUserId}?force=true");
}
if ($invFileId) {
    request('DELETE', "{$base}/files/{$invFileId}?force=true");
}
@unlink($invFileTmp);

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($invOrderId) {
    request('DELETE', "{$base}/orders/{$invOrderId}?force=true");
}
if ($invProductId) {
    request('DELETE', "{$base}/products/{$invProductId}?force=true");
}
if ($invCatId) {
    request('DELETE', "{$base}/categories/{$invCatId}?force=true");
}
if ($invUserId) {
    request('DELETE', "{$base}/users/{$invUserId}?force=true");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
