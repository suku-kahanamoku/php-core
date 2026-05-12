#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration tests for App\Modules\Role\RoleService
 *
 * Tests business logic: duplicate prevention, built-in role protection,
 * role-in-use protection, validation rules
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

// ── RoleService – duplicate prevention ───────────────────────────────────────

section('RoleService – duplicate name prevention');
$svcRoleName = TEST_PREFIX . 'svc_role_' . time();
$r           = request('POST', "{$base}/roles", ['name' => $svcRoleName, 'label' => 'Svc Role']);
assert_test('create role 201', $r['status'] === 201, dump_on_fail($r));
$svcRoleId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/roles", ['name' => $svcRoleName, 'label' => 'Dup']);
assert_test('duplicate name → 409', $r['status'] === 409, dump_on_fail($r));

// ── RoleService – validation ──────────────────────────────────────────────────

section('RoleService – validation');
$r = request('POST', "{$base}/roles", ['name' => 'Bad Name!', 'label' => 'x']);
assert_test('invalid name format → 422', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/roles", ['name' => 'ok_name']);
assert_test('missing label → 422', $r['status'] === 422, dump_on_fail($r));

$r = request('PUT', "{$base}/roles/{$svcRoleId}", ['name' => '', 'label' => 'x']);
assert_test('PUT empty name → 422', $r['status'] === 422, dump_on_fail($r));

// ── RoleService – role-in-use protection ─────────────────────────────────────

section('RoleService – cannot delete role assigned to user');
$svcUserEmail = TEST_PREFIX . 'role_svc_user_' . time() . '@example.com';
$r            = request('POST', "{$base}/users", [
    'first_name' => 'Role', 'last_name' => 'SvcUser',
    'email'      => $svcUserEmail, 'password' => 'Password123',
    'role_id'    => $svcRoleId,
]);
assert_test('create user with svc_role 201', $r['status'] === 201, dump_on_fail($r));
$svcUserId = $r['data']['data']['id'] ?? null;

$r = request('DELETE', "{$base}/roles/{$svcRoleId}");
assert_test('delete role with user → 409', $r['status'] === 409, dump_on_fail($r));

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($svcUserId) {
    request('DELETE', "{$base}/users/{$svcUserId}");
}
if ($svcRoleId) {
    request('DELETE', "{$base}/roles/{$svcRoleId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
