#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration tests for App\Modules\Enumeration\EnumerationService
 *
 * Tests business logic: validation, duplicate type+code prevention
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

// ── EnumerationService – validation ──────────────────────────────────────────

section('EnumerationService – create() validation');
$r = request('POST', "{$base}/enumerations", ['type' => 'x']);
assert_test('missing syscode + label → 422', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/enumerations", ['syscode' => 'x', 'label' => 'x']);
assert_test('missing type → 422', $r['status'] === 422, dump_on_fail($r));

// ── EnumerationService – PUT validation ──────────────────────────────────────

section('EnumerationService – update() validation');
$svcEnumType = 'svc_enum_' . time();
$r           = request('POST', "{$base}/enumerations", [
    'type' => $svcEnumType, 'syscode' => 'syscode_s', 'label' => 'Code S',
]);
assert_test('create enumeration 201', $r['status'] === 201, dump_on_fail($r));
$svcEnumId = $r['data']['data']['id'] ?? null;

if ($svcEnumId) {
    $r = request('PUT', "{$base}/enumerations/{$svcEnumId}", ['type' => $svcEnumType]);
    assert_test('PUT missing syscode+label → 422', $r['status'] === 422, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($svcEnumId) {
    request('DELETE', "{$base}/enumerations/{$svcEnumId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
