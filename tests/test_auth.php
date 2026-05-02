#!/usr/bin/env php
<?php

declare(strict_types=1);

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/bootstrap.php';
}
if (!isset($base)) {
    $base = rtrim($argv[1] ?? 'http://localhost/php/php-core/api', '/');
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}
$token = null;

// ── Setup: register a fresh test user ────────────────────────────────────────

$authEmail    = 'auth_test_' . time() . '@example.com';
$authPassword = 'TestPass123';
$authUserId   = null;

section('Auth – register');
$r = request('POST', "{$base}/auth/register", [
    'first_name' => 'Test', 'last_name' => 'User',
    'email' => $authEmail, 'password' => $authPassword,
], false);
assert_test('POST /auth/register 201',  $r['status'] === 201, dump_on_fail($r));
assert_test('returns new user id',      isset($r['data']['data']['id']), dump_on_fail($r));
$authUserId = $r['data']['data']['id'] ?? null;

// ── Login validation ──────────────────────────────────────────────────────────

section('Auth – login validation errors');
$r = request('POST', "{$base}/auth/login", [], false);
assert_test('422 on empty body',        $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/auth/login", ['email' => 'notanemail', 'password' => '123'], false);
assert_test('422 on invalid email',     $r['status'] === 422, dump_on_fail($r));

section('Auth – wrong credentials');
$r = request('POST', "{$base}/auth/login", ['email' => 'nobody@example.com', 'password' => 'wrong'], false);
assert_test('401 for unknown user',     $r['status'] === 401, dump_on_fail($r));

section('Auth – duplicate email');
$r = request('POST', "{$base}/auth/register", [
    'first_name' => 'Test', 'last_name' => 'User',
    'email' => $authEmail, 'password' => $authPassword,
], false);
assert_test('409 on duplicate email',   $r['status'] === 409, dump_on_fail($r));

// ── Valid login ───────────────────────────────────────────────────────────────

section('Auth – valid login');
$r = request('POST', "{$base}/auth/login", ['email' => $authEmail, 'password' => $authPassword], false);
assert_test('200 on valid login',       $r['status'] === 200, dump_on_fail($r));
assert_test('returns token',            isset($r['data']['data']['token']), dump_on_fail($r));
assert_test('returns email',            $r['data']['data']['email'] === $authEmail);
assert_test('returns role',             isset($r['data']['data']['role']));
$token = $r['data']['data']['token'] ?? null;

// ── /auth/me ─────────────────────────────────────────────────────────────────

section('GET /auth/me');
$r = request('GET', "{$base}/auth/me");
assert_test('200 with valid token',     $r['status'] === 200, dump_on_fail($r));
assert_test('returns correct email',    $r['data']['data']['email'] === $authEmail);

$r = request('GET', "{$base}/auth/me", [], false);
assert_test('401 without token',        $r['status'] === 401, dump_on_fail($r));

// ── Change password ───────────────────────────────────────────────────────────

section('Auth – change password');
$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => 'WrongPass999', 'new_password' => 'NewPass123!',
]);
assert_test('401 on wrong current password', $r['status'] === 401, dump_on_fail($r));

$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => $authPassword, 'new_password' => 'short',
]);
assert_test('422 on short new password', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => $authPassword, 'new_password' => 'NewPass123!',
]);
assert_test('200 on valid change',      $r['status'] === 200, dump_on_fail($r));

// ── Logout ────────────────────────────────────────────────────────────────────

section('Auth – logout');
$r = request('POST', "{$base}/auth/logout");
assert_test('200',                      $r['status'] === 200, dump_on_fail($r));
$token = null;

$r = request('GET', "{$base}/auth/me");
assert_test('401 after logout',         $r['status'] === 401, dump_on_fail($r));

// ── Cleanup ───────────────────────────────────────────────────────────────────

$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;
if ($authUserId) {
    request('DELETE', "{$base}/users/{$authUserId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
