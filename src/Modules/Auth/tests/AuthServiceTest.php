#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration tests for App\Modules\Auth\AuthService
 *
 * Tests: register, login, logout, changePassword, getMe
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

// ── register() ────────────────────────────────────────────────────────────────

$svcEmail    = TEST_PREFIX . 'auth_svc_' . time() . '@example.com';
$svcPassword = 'TestPass123';
$svcUserId   = null;

section('AuthService – register()');

$r = request('POST', "{$base}/auth/register", [
    'first_name' => 'Svc', 'last_name' => 'Test',
    'email'      => $svcEmail, 'password' => $svcPassword,
], false);
assert_test('register 201', $r['status'] === 201, dump_on_fail($r));
assert_test('returns id', isset($r['data']['data']['id']));
$svcUserId = $r['data']['data']['id'] ?? null;

section('AuthService – register() duplicate');
$r = request('POST', "{$base}/auth/register", [
    'first_name' => 'Svc', 'last_name' => 'Test',
    'email'      => $svcEmail, 'password' => $svcPassword,
], false);
assert_test('duplicate email → 409', $r['status'] === 409, dump_on_fail($r));

section('AuthService – register() validation');
$r = request('POST', "{$base}/auth/register", ['email' => 'notvalid', 'password' => '123'], false);
assert_test('invalid data → 422', $r['status'] === 422, dump_on_fail($r));

// ── login() ───────────────────────────────────────────────────────────────────

section('AuthService – login()');

$r = request('POST', "{$base}/auth/login", ['email' => $svcEmail, 'password' => $svcPassword], false);
assert_test('login 200', $r['status'] === 200, dump_on_fail($r));
assert_test('returns token', isset($r['data']['data']['token']));
assert_test('token is non-empty string', is_string($r['data']['data']['token'] ?? null) && strlen($r['data']['data']['token'] ?? '') === 64);
$token = $r['data']['data']['token'] ?? null;

section('AuthService – login() bad credentials');
$r = request('POST', "{$base}/auth/login", ['email' => $svcEmail, 'password' => 'WrongPass!'], false);
assert_test('wrong password → 401', $r['status'] === 401, dump_on_fail($r));

$r = request('POST', "{$base}/auth/login", ['email' => 'nobody@nowhere.com', 'password' => $svcPassword], false);
assert_test('unknown user → 401', $r['status'] === 401, dump_on_fail($r));

// ── getMe() ───────────────────────────────────────────────────────────────────

section('AuthService – getMe()');
$r = request('GET', "{$base}/auth/me");
assert_test('me 200', $r['status'] === 200, dump_on_fail($r));
assert_test('email matches', $r['data']['data']['email'] === $svcEmail);

// ── changePassword() ─────────────────────────────────────────────────────────

section('AuthService – changePassword()');
$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => 'WrongPass!', 'new_password' => 'NewPass123!',
]);
assert_test('wrong current → 401', $r['status'] === 401, dump_on_fail($r));

$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => $svcPassword, 'new_password' => 'short',
]);
assert_test('short new password → 422', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => $svcPassword, 'new_password' => 'NewPass123!',
]);
assert_test('valid change → 200', $r['status'] === 200, dump_on_fail($r));

// ── logout() ─────────────────────────────────────────────────────────────────

section('AuthService – logout()');
$r = request('POST', "{$base}/auth/logout");
assert_test('logout 200', $r['status'] === 200, dump_on_fail($r));
$token = null;

$r = request('GET', "{$base}/auth/me");
assert_test('me → 401 after logout', $r['status'] === 401, dump_on_fail($r));

// ── Cleanup ───────────────────────────────────────────────────────────────────

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
$token = $r['data']['data']['token'] ?? null;
if ($svcUserId) {
    request('DELETE', "{$base}/users/{$svcUserId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
