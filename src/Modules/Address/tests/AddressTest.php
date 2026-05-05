#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit/integration tests for App\Modules\Address\Address
 *
 * Tests: getAllByUser(), getById(), create(), update(), delete()
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

$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$addrModelUserEmail = 'addr_model_' . time() . '@example.com';
$r                  = request('POST', "{$base}/users", [
    'first_name' => 'Addr', 'last_name' => 'Model',
    'email'      => $addrModelUserEmail, 'password' => 'Password123',
]);
$addrModelUserId = $r['data']['data']['id'] ?? null;

// ── Address model – create() ──────────────────────────────────────────────────

section('Address model – create()');
$r = request('POST', "{$base}/address", [
    'user_id' => $addrModelUserId, 'type' => 'billing',
    'street'  => 'Modelová 1', 'city' => 'Praha', 'zip' => '11000', 'country' => 'CZ',
]);
assert_test('create address 201', $r['status'] === 201, dump_on_fail($r));
$addrModelId = $r['data']['data']['id'] ?? null;

// ── Address model – getAllByUser() ────────────────────────────────────────────

section('Address model – getAllByUser()');
if ($addrModelUserId) {
    $r = request('GET', "{$base}/users/{$addrModelUserId}/address");
    assert_test('list by user 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('returns array', is_array($r['data']['data']['items']));
    assert_test('contains created address', count(array_filter($r['data']['data']['items'], fn ($a) => $a['id'] === $addrModelId)) > 0);
}

// ── Address model – getById() ─────────────────────────────────────────────────

section('Address model – getById()');
if ($addrModelId) {
    $r = request('GET', "{$base}/address/{$addrModelId}");
    assert_test('getById 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('street matches', $r['data']['data']['street'] === 'Modelová 1');
    assert_test('city matches', $r['data']['data']['city'] === 'Praha');

    $r = request('GET', "{$base}/address/999999");
    assert_test('unknown id → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Address model – update() ──────────────────────────────────────────────────

section('Address model – update()');
if ($addrModelId) {
    $r = request('PATCH', "{$base}/address/{$addrModelId}", ['city' => 'Brno']);
    assert_test('PATCH address 200', $r['status'] === 200, dump_on_fail($r));
    $r2 = request('GET', "{$base}/address/{$addrModelId}");
    assert_test('city updated', $r2['data']['data']['city'] === 'Brno', dump_on_fail($r2));

    $r = request('PUT', "{$base}/address/{$addrModelId}", [
        'type' => 'shipping', 'street' => 'Nová 10',
        'city' => 'Ostrava', 'zip' => '70200', 'country' => 'CZ',
    ]);
    assert_test('PUT address 200', $r['status'] === 200, dump_on_fail($r));
}

// ── Address model – delete() ──────────────────────────────────────────────────

section('Address model – delete()');
if ($addrModelId) {
    $r = request('DELETE', "{$base}/address/{$addrModelId}");
    assert_test('delete address 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/address/{$addrModelId}");
    assert_test('deleted address → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($addrModelUserId) {
    request('DELETE', "{$base}/users/{$addrModelUserId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
