#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API endpoint tests for App\Modules\Role\RoleApi
 *
 * Tests all /roles/* routes
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

// ── Public: list roles ────────────────────────────────────────────────────────

section('Roles – public list');
$r = request('GET', "{$base}/roles", [], false);
assert_test('GET /roles 200', $r['status'] === 200, dump_on_fail($r));
assert_test('data is array', is_array($r['data']['data']));
assert_test('contains admin role', count(array_filter($r['data']['data'], fn ($x) => $x['name'] === 'admin')) > 0);

// ── Public: get by id ─────────────────────────────────────────────────────────

section('Roles – public get by id');
$adminRoleId = null;
foreach ($r['data']['data'] ?? [] as $row) {
    if ($row['name'] === 'admin') {
        $adminRoleId = $row['id'];
        break;
    }
}

if ($adminRoleId) {
    $r2 = request('GET', "{$base}/roles/{$adminRoleId}", [], false);
    assert_test('GET /roles/:id 200', $r2['status'] === 200, dump_on_fail($r2));
    assert_test('has user_count', isset($r2['data']['data']['user_count']));
    assert_test('name = admin', $r2['data']['data']['name'] === 'admin');

    $r2 = request('GET', "{$base}/roles/999999", [], false);
    assert_test('GET /roles/:id 404', $r2['status'] === 404, dump_on_fail($r2));
}

// ── Admin login ───────────────────────────────────────────────────────────────

section('Roles – admin login');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

// ── Non-admin protection ──────────────────────────────────────────────────────

section('Roles – non-admin protection');
$tmpToken = $token;

$roleRegEmail = 'role_reg_' . time() . '@example.com';
$r            = request('POST', "{$base}/auth/register", [
    'first_name' => 'Reg', 'last_name' => 'User',
    'email'      => $roleRegEmail, 'password' => 'Password123',
], false);
$roleRegUserId = $r['data']['data']['id'] ?? null;
$r             = request('POST', "{$base}/auth/login", ['email' => $roleRegEmail, 'password' => 'Password123'], false);
$token         = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/roles", ['name' => 'testx', 'label' => 'Test X']);
assert_test('POST /roles → 403 for non-admin', $r['status'] === 403, dump_on_fail($r));

$token = $tmpToken;
if ($roleRegUserId) {
    request('DELETE', "{$base}/users/{$roleRegUserId}");
}

// ── CRUD ──────────────────────────────────────────────────────────────────────

section('Roles – CRUD');
$r = request('POST', "{$base}/roles", ['name' => 'test_role', 'label' => 'Test Role', 'position' => 99]);
assert_test('POST /roles 201', $r['status'] === 201, dump_on_fail($r));
$newRoleId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/roles", ['name' => 'test_role', 'label' => 'Dup']);
assert_test('POST /roles 409 duplicate', $r['status'] === 409, dump_on_fail($r));

$r = request('POST', "{$base}/roles", ['name' => 'Bad Name!', 'label' => 'x']);
assert_test('POST /roles 422 invalid name', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/roles", ['name' => 'ok_name']);
assert_test('POST /roles 422 missing label', $r['status'] === 422, dump_on_fail($r));

if ($newRoleId) {
    $r = request('GET', "{$base}/roles/{$newRoleId}");
    assert_test('GET /roles/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('name = test_role', $r['data']['data']['name'] === 'test_role');

    $r = request('PATCH', "{$base}/roles/{$newRoleId}", ['label' => 'Test Role Patched']);
    assert_test('PATCH /roles/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/roles/{$newRoleId}", ['name' => 'test_role', 'label' => 'Test Role Replaced']);
    assert_test('PUT /roles/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/roles/{$newRoleId}", ['name' => '', 'label' => 'x']);
    assert_test('PUT /roles/:id 422 empty name', $r['status'] === 422, dump_on_fail($r));

    $r = request('DELETE', "{$base}/roles/{$newRoleId}");
    assert_test('DELETE /roles/:id 200', $r['status'] === 200, dump_on_fail($r));
}

// ── Built-in roles protected ──────────────────────────────────────────────────

section('Roles – built-in protection');
if ($adminRoleId) {
    $r = request('DELETE', "{$base}/roles/{$adminRoleId}");
    assert_test('DELETE /roles/admin → 409', $r['status'] === 409, dump_on_fail($r));
}

// ── Cannot delete role with users ─────────────────────────────────────────────

section('Roles – cannot delete role with users');
$tempRoleName = 'temp_role_' . time();
$r            = request('POST', "{$base}/roles", ['name' => $tempRoleName, 'label' => 'Temp Role']);
assert_test('POST /roles 201 (temp)', $r['status'] === 201, dump_on_fail($r));
$tempRoleId = $r['data']['data']['id'] ?? null;

if ($tempRoleId) {
    $tempUserEmail = 'role_user_' . time() . '@example.com';
    $r             = request('POST', "{$base}/users", [
        'first_name' => 'Temp', 'last_name' => 'User',
        'email'      => $tempUserEmail, 'password' => 'Password123',
        'role'       => $tempRoleName,
    ]);
    assert_test('create user with temp_role 201', $r['status'] === 201, dump_on_fail($r));
    $tempUserId = $r['data']['data']['id'] ?? null;

    $r = request('DELETE', "{$base}/roles/{$tempRoleId}");
    assert_test('DELETE role with user → 409', $r['status'] === 409, dump_on_fail($r));

    if ($tempUserId) {
        request('DELETE', "{$base}/users/{$tempUserId}");
    }
    request('DELETE', "{$base}/roles/{$tempRoleId}");
}

// ── Role filter on users ──────────────────────────────────────────────────────

section('Roles – user CRUD with role assignment');
$r = request('GET', "{$base}/users?role=admin");
assert_test('GET /users?role=admin 200', $r['status'] === 200, dump_on_fail($r));
assert_test('all returned users are admin', count(array_filter($r['data']['data']['items'] ?? [], fn ($u) => $u['role'] !== 'admin')) === 0);

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
