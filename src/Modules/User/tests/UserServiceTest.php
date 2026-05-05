#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration tests for App\Modules\User\UserService
 *
 * Tests business logic: validation, duplicate email prevention, admin-only operations
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

// ── UserService – validation ──────────────────────────────────────────────────

section('UserService – create() validation');
$r = request('POST', "{$base}/users", ['first_name' => '', 'last_name' => 'X', 'email' => 'bad', 'password' => '123']);
assert_test('invalid data → 422', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/users", ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'notanemail', 'password' => 'Password123']);
assert_test('invalid email → 422', $r['status'] === 422, dump_on_fail($r));

// ── UserService – duplicate email ─────────────────────────────────────────────

section('UserService – duplicate email prevention');
$dupEmail = 'user_svc_dup_' . time() . '@example.com';
$r        = request('POST', "{$base}/users", [
    'first_name' => 'Dup', 'last_name' => 'User',
    'email'      => $dupEmail, 'password' => 'Password123',
]);
assert_test('first user 201', $r['status'] === 201, dump_on_fail($r));
$dupUserId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/users", [
    'first_name' => 'Dup2', 'last_name' => 'User',
    'email'      => $dupEmail, 'password' => 'Password123',
]);
assert_test('duplicate email → 409', $r['status'] === 409, dump_on_fail($r));

// ── UserService – PUT validation ──────────────────────────────────────────────

section('UserService – update() validation');
if ($dupUserId) {
    $r = request('PUT', "{$base}/users/{$dupUserId}", ['first_name' => '', 'last_name' => 'Name']);
    assert_test('PUT empty first_name → 422', $r['status'] === 422, dump_on_fail($r));
}

// ── UserService – admin-only access ───────────────────────────────────────────

section('UserService – admin-only access');
$token = null;
$r     = request('GET', "{$base}/users");
assert_test('GET /users → 401 without token', $r['status'] === 401, dump_on_fail($r));

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
$token = $r['data']['data']['token'] ?? null;

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($dupUserId) {
    request('DELETE', "{$base}/users/{$dupUserId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
