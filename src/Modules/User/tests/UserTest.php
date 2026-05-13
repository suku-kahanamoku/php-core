#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit/integration tests for App\Modules\User\User
 *
 * Tests: getAll(), getById(), create(), update(), delete()
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

// ── User model – getAll() ─────────────────────────────────────────────────────

section('User model – getAll()');
$r = request('GET', "{$base}/users");
assert_test('returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('has items + total', isset($r['data']['data'], $r['data']['meta']['total']));
assert_test('has pagination fields', isset($r['data']['meta']['page'], $r['data']['meta']['limit'], $r['data']['meta']['totalPages']));

// ── User model – getById() ────────────────────────────────────────────────────

section('User model – getById()');
$userEmail = TEST_PREFIX . 'user_model_' . time() . '@example.com';
$r         = request('POST', "{$base}/users", [
    'first_name' => 'Model', 'last_name' => 'Test',
    'email'      => $userEmail, 'password' => 'Password123',
]);
assert_test('create user 201', $r['status'] === 201, dump_on_fail($r));
$userId = $r['data']['data']['id'] ?? null;

if ($userId) {
    $r = request('GET', "{$base}/users/{$userId}");
    assert_test('getById 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('email matches', $r['data']['data']['email'] === $userEmail);
    assert_test('has role field', isset($r['data']['data']['role']));

    $r = request('GET', "{$base}/users/999999");
    assert_test('unknown id → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── User model – update() ─────────────────────────────────────────────────────

section('User model – update()');
if ($userId) {
    $r = request('PATCH', "{$base}/users/{$userId}", ['phone' => '+420123456789']);
    assert_test('PATCH user 200', $r['status'] === 200, dump_on_fail($r));
    $r2 = request('GET', "{$base}/users/{$userId}");
    assert_test('phone updated', $r2['data']['data']['phone'] === '+420123456789', dump_on_fail($r2));

    $r = request('PUT', "{$base}/users/{$userId}", ['first_name' => 'Updated', 'last_name' => 'Model']);
    assert_test('PUT user 200', $r['status'] === 200, dump_on_fail($r));
}

// ── User model – delete() ─────────────────────────────────────────────────────

section('User model – delete()');
if ($userId) {
    // Verify 'deleted' field is 0 before deletion.
    $r = request('GET', "{$base}/users/{$userId}");
    assert_test('deleted field is 0 before delete', ($r['data']['data']['deleted'] ?? -1) === 0, dump_on_fail($r));

    $r = request('DELETE', "{$base}/users/{$userId}");
    assert_test('DELETE user 200', $r['status'] === 200, dump_on_fail($r));

    // Soft delete: GET by ID returns 404.
    $r = request('GET', "{$base}/users/{$userId}");
    assert_test('deleted user returns 404', $r['status'] === 404, dump_on_fail($r));

    // Soft delete: visible with deleted=1 filter.
    $r = request('GET', "{$base}/users?q=" . urlencode(json_encode(['deleted' => 1])));
    assert_test('deleted users visible with deleted:1', ($r['data']['meta']['total'] ?? 0) >= 1, dump_on_fail($r));
}

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
