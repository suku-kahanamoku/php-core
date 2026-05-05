#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit/integration tests for App\Modules\Role\Role
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

// ── Role model – read operations ─────────────────────────────────────────────

section('Role model – getAll()');
$r = request('GET', "{$base}/roles?limit=100", [], false);
assert_test('returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('data is array', is_array($r['data']['data']['items']));
assert_test('at least 3 default roles', ($r['data']['data']['total'] ?? 0) >= 3);

section('Role model – getById()');
$adminRoleId = null;
foreach ($r['data']['data']['items'] ?? [] as $row) {
    if ($row['name'] === 'admin') {
        $adminRoleId = $row['id'];
        break;
    }
}
assert_test('admin role found in list', $adminRoleId !== null);

if ($adminRoleId) {
    $r2 = request('GET', "{$base}/roles/{$adminRoleId}", [], false);
    assert_test('getById returns 200', $r2['status'] === 200, dump_on_fail($r2));
    assert_test('has user_count field', isset($r2['data']['data']['user_count']));
    assert_test('name = admin', $r2['data']['data']['name'] === 'admin');

    $r2 = request('GET', "{$base}/roles/999999", [], false);
    assert_test('unknown id → 404', $r2['status'] === 404, dump_on_fail($r2));
}

// ── Role model – write operations (admin required) ────────────────────────────

section('Role model – create()');
$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
$token = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/roles", ['name' => 'model_role_' . time(), 'label' => 'Model Role']);
assert_test('create role 201', $r['status'] === 201, dump_on_fail($r));
$modelRoleId = $r['data']['data']['id'] ?? null;

section('Role model – update() / delete()');
if ($modelRoleId) {
    $r = request('PATCH', "{$base}/roles/{$modelRoleId}", ['label' => 'Model Role Updated']);
    assert_test('update role 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('DELETE', "{$base}/roles/{$modelRoleId}");
    assert_test('delete role 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/roles/{$modelRoleId}", [], false);
    assert_test('deleted role → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Built-in roles are protected ─────────────────────────────────────────────

section('Role model – built-in role protection');
if ($adminRoleId) {
    $r = request('DELETE', "{$base}/roles/{$adminRoleId}");
    assert_test('delete admin role → 409', $r['status'] === 409, dump_on_fail($r));
}

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
