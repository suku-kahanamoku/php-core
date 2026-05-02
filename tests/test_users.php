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

// ── Admin login ───────────────────────────────────────────────────────────────

section('Users – admin login');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200',          $r['status'] === 200, dump_on_fail($r));
assert_test('role = admin',             $r['data']['data']['role'] === 'admin', dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

// ── Non-admin protection ──────────────────────────────────────────────────────

section('Users – non-admin protection');
$tmpToken = $token;
$token    = null;
$r = request('GET', "{$base}/users");
assert_test('GET /users → 401 without token', $r['status'] === 401, dump_on_fail($r));
$token = $tmpToken;

// ── List ──────────────────────────────────────────────────────────────────────

section('Users – list');
$r = request('GET', "{$base}/users");
assert_test('GET /users 200',           $r['status'] === 200, dump_on_fail($r));
assert_test('has items + total',        isset($r['data']['data']['items'], $r['data']['data']['total']));

// ── Create ────────────────────────────────────────────────────────────────────

section('Users – create');
$userEmail     = 'users_test_' . time() . '@example.com';
$r = request('POST', "{$base}/users", [
    'first_name' => 'Created', 'last_name' => 'ByAdmin',
    'email' => $userEmail, 'password' => 'Password123',
]);
assert_test('POST /users 201',          $r['status'] === 201, dump_on_fail($r));
$userCreatedId = $r['data']['data']['id'] ?? null;

// ── Read / update ─────────────────────────────────────────────────────────────

section('Users – read & update');
if ($userCreatedId) {
    $r = request('GET', "{$base}/users/{$userCreatedId}");
    assert_test('GET /users/:id 200',   $r['status'] === 200, dump_on_fail($r));
    assert_test('email matches',        $r['data']['data']['email'] === $userEmail);

    $r = request('PATCH', "{$base}/users/{$userCreatedId}", ['phone' => '+420123456789']);
    assert_test('PATCH /users/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/users/{$userCreatedId}", ['first_name' => 'Updated', 'last_name' => 'Name']);
    assert_test('PUT /users/:id 200',   $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/users/{$userCreatedId}", ['first_name' => '', 'last_name' => 'Name']);
    assert_test('PUT /users/:id 422 empty first_name', $r['status'] === 422, dump_on_fail($r));
}

// ── Delete ────────────────────────────────────────────────────────────────────

section('Users – delete');
if ($userCreatedId) {
    $r = request('DELETE', "{$base}/users/{$userCreatedId}");
    assert_test('DELETE /users/:id 200', $r['status'] === 200, dump_on_fail($r));
}

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
