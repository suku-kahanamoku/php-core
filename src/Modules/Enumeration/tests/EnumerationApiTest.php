#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API endpoint tests for App\Modules\Enumeration\EnumerationApi
 *
 * Tests all /enumerations/* routes
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

// ── Public routes ─────────────────────────────────────────────────────────────

section('Enumerations – public list');
$r = request('GET', "{$base}/enumerations", [], false);
assert_test('GET /enumerations 200', $r['status'] === 200, dump_on_fail($r));
assert_test('has items array', is_array($r['data']['data']));
assert_test('has order_status group', count(array_filter($r['data']['data'], fn ($x) => $x['type'] === 'order_status')) > 0);
assert_test('has invoice_status group', count(array_filter($r['data']['data'], fn ($x) => $x['type'] === 'invoice_status')) > 0);

$firstEnumId = null;
if (!empty($r['data']['data'])) {
    $firstGroup  = $r['data']['data'][0];
    $firstEnumId = $firstGroup['id'] ?? null;
}

section('Enumerations – public types');
$r = request('GET', "{$base}/enumerations/types", [], false);
assert_test('GET /enumerations/types 200', $r['status'] === 200, dump_on_fail($r));
assert_test('is array of strings', is_array($r['data']['data']));

section('Enumerations – public get by id');
if ($firstEnumId) {
    $r = request('GET', "{$base}/enumerations/{$firstEnumId}", [], false);
    assert_test('GET /enumerations/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has type + syscode', isset($r['data']['data']['type'], $r['data']['data']['syscode']));
}

// ── Admin login ───────────────────────────────────────────────────────────────

section('Enumerations – admin login');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

// ── Create ────────────────────────────────────────────────────────────────────

section('Enumerations – CRUD');
$enumType = TEST_PREFIX . 'type_' . time();
$r        = request('POST', "{$base}/enumerations", [
    'type' => $enumType, 'syscode' => 'syscode_a', 'label' => 'Code A',
]);
assert_test('POST /enumerations 201', $r['status'] === 201, dump_on_fail($r));
$enumId = $r['data']['data']['id'] ?? null;

if ($enumId) {
    $r = request('GET', "{$base}/enumerations/{$enumId}");
    assert_test('GET /enumerations/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('syscode matches', $r['data']['data']['syscode'] === 'syscode_a');

    $r = request('PATCH', "{$base}/enumerations/{$enumId}", ['label' => 'Code A Patched']);
    assert_test('PATCH /enumerations/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/enumerations/{$enumId}", [
        'type' => $enumType, 'syscode' => 'syscode_a', 'label' => 'Code A Updated',
    ]);
    assert_test('PUT /enumerations/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/enumerations/{$enumId}", ['type' => $enumType]);
    assert_test('PUT /enumerations/:id 422 missing syscode+label', $r['status'] === 422, dump_on_fail($r));

    $r = request('DELETE', "{$base}/enumerations/{$enumId}");
    assert_test('DELETE /enumerations/:id 200', $r['status'] === 200, dump_on_fail($r));
}

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
