#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit tests for App\Modules\Auth\Auth
 *
 * Tests static methods: check(), require(), id(), role(), hasRole()
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

// ── Auth::check() – returns null when no token present ───────────────────────

section('Auth model – unauthenticated state');

$r = request('GET', "{$base}/auth/me", [], false);
assert_test('GET /auth/me returns 401 without token', $r['status'] === 401);
assert_test('success = false', $r['data']['success'] === false);

// ── Auth::check() – returns user when valid token ────────────────────────────

section('Auth model – authenticated state');

$testEmail    = 'auth_model_' . time() . '@example.com';
$testPassword = 'TestPass123';
$testUserId   = null;

$r = request('POST', "{$base}/auth/register", [
    'first_name' => 'Auth', 'last_name' => 'Model',
    'email'      => $testEmail, 'password' => $testPassword,
], false);
assert_test('register test user 201', $r['status'] === 201, dump_on_fail($r));
$testUserId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/auth/login", ['email' => $testEmail, 'password' => $testPassword], false);
assert_test('login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$r = request('GET', "{$base}/auth/me");
assert_test('GET /auth/me 200 with valid token', $r['status'] === 200, dump_on_fail($r));
assert_test('returned user email matches', $r['data']['data']['email'] === $testEmail);
assert_test('returned user has role', isset($r['data']['data']['role']));
assert_test('returned user has id', isset($r['data']['data']['id']));

// ── Auth::hasRole() – role check ─────────────────────────────────────────────

section('Auth model – role check');

assert_test('registered user has role = user', $r['data']['data']['role'] === 'user');

// ── Cleanup ───────────────────────────────────────────────────────────────────

$r          = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
$adminToken = $r['data']['data']['token'] ?? null;
$savedToken = $token;
$token      = $adminToken;
if ($testUserId) {
    request('DELETE', "{$base}/users/{$testUserId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
