#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API endpoint tests for App\Modules\Address\AddressApi
 *
 * Tests all /addresses/* and /users/:userId/addresses routes
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

// ── Setup: admin login + create a temp user ───────────────────────────────────

section('Addresses – setup');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$addrUserEmail = 'addr_user_' . time() . '@example.com';
$r             = request('POST', "{$base}/users", [
    'first_name' => 'Addr', 'last_name' => 'User',
    'email'      => $addrUserEmail, 'password' => 'Password123',
]);
assert_test('create temp user 201', $r['status'] === 201, dump_on_fail($r));
$addrUserId = $r['data']['data']['id'] ?? null;

// ── Create ────────────────────────────────────────────────────────────────────

section('Addresses – create');
$r = request('POST', "{$base}/addresses", [
    'user_id' => $addrUserId, 'type' => 'billing',
    'street'  => 'Testovací 1', 'city' => 'Praha', 'zip' => '11000', 'country' => 'CZ',
]);
assert_test('POST /addresses 201', $r['status'] === 201, dump_on_fail($r));
$addrId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/addresses", ['type' => 'billing']);
assert_test('POST /addresses 422 missing fields', $r['status'] === 422, dump_on_fail($r));

// ── List by user ──────────────────────────────────────────────────────────────

section('Addresses – list by user');
if ($addrUserId) {
    $r = request('GET', "{$base}/users/{$addrUserId}/addresses");
    assert_test('GET /users/:userId/addresses 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('returns array', is_array($r['data']['data']));
}

// ── Read / update / delete ────────────────────────────────────────────────────

section('Addresses – read & update');
if ($addrId) {
    $r = request('GET', "{$base}/addresses/{$addrId}");
    assert_test('GET /addresses/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('street matches', $r['data']['data']['street'] === 'Testovací 1');

    $r = request('PATCH', "{$base}/addresses/{$addrId}", ['city' => 'Brno']);
    assert_test('PATCH /addresses/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/addresses/{$addrId}", [
        'type' => 'shipping', 'street' => 'Nová 5',
        'city' => 'Brno', 'zip' => '60200', 'country' => 'CZ',
    ]);
    assert_test('PUT /addresses/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/addresses/{$addrId}", ['street' => 'x', 'city' => '', 'zip' => '1', 'country' => 'CZ']);
    assert_test('PUT /addresses/:id 422 missing city', $r['status'] === 422, dump_on_fail($r));

    $r = request('DELETE', "{$base}/addresses/{$addrId}");
    assert_test('DELETE /addresses/:id 200', $r['status'] === 200, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($addrUserId) {
    request('DELETE', "{$base}/users/{$addrUserId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
