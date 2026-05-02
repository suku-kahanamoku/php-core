#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration tests for App\Modules\Address\AddressService
 *
 * Tests business logic: validation, user ownership, required fields
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

$svcUserEmail = 'addr_svc_' . time() . '@example.com';
$r            = request('POST', "{$base}/users", [
    'first_name' => 'Addr', 'last_name' => 'Svc',
    'email'      => $svcUserEmail, 'password' => 'Password123',
]);
$svcUserId = $r['data']['data']['id'] ?? null;

// ── AddressService – create() validation ────────────────────────────────────

section('AddressService – create() validation');
$r = request('POST', "{$base}/addresses", ['type' => 'billing']);
assert_test('missing required fields → 422', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/addresses", [
    'user_id' => $svcUserId, 'type' => 'billing',
    'street'  => 'x', 'city' => '', 'zip' => '1', 'country' => 'CZ',
]);
assert_test('empty city → 422', $r['status'] === 422, dump_on_fail($r));

// ── AddressService – create() success ────────────────────────────────────────

section('AddressService – create() success');
$r = request('POST', "{$base}/addresses", [
    'user_id' => $svcUserId, 'type' => 'billing',
    'street'  => 'Servisní 1', 'city' => 'Praha', 'zip' => '11000', 'country' => 'CZ',
]);
assert_test('valid address → 201', $r['status'] === 201, dump_on_fail($r));
$svcAddrId = $r['data']['data']['id'] ?? null;

// ── AddressService – update() validation ────────────────────────────────────

section('AddressService – update() validation');
if ($svcAddrId) {
    $r = request('PUT', "{$base}/addresses/{$svcAddrId}", [
        'street' => 'x', 'city' => '', 'zip' => '1', 'country' => 'CZ',
    ]);
    assert_test('PUT empty city → 422', $r['status'] === 422, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($svcAddrId) {
    request('DELETE', "{$base}/addresses/{$svcAddrId}");
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
