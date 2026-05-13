#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit/integration tests for App\Modules\Enumeration\Enumeration
 *
 * Tests: getAll(), getTypes(), getById(), create(), update(), delete()
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

// ── Enumeration model – getAll() (public, paginated) ─────────────────────────────

section('Enumeration model – getAll()');
$r = request('GET', "{$base}/enumerations", [], false);
assert_test('returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('has items array', is_array($r['data']['data']));
assert_test('has order_status group', count(array_filter($r['data']['data'], fn ($x) => $x['type'] === 'order_status')) > 0);
assert_test('has invoice_status group', count(array_filter($r['data']['data'], fn ($x) => $x['type'] === 'invoice_status')) > 0);

$firstEnumId = null;
foreach ($r['data']['data'] as $item) {
    if (!empty($item['id'])) {
        $firstEnumId = $item['id'];
        break;
    }
}

// ── Enumeration model – getTypes() (public) ──────────────────────────────────

section('Enumeration model – getTypes()');
$r = request('GET', "{$base}/enumerations/types", [], false);
assert_test('returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('is array of strings', is_array($r['data']['data']));
assert_test('contains order_status', in_array('order_status', $r['data']['data']));

// ── Enumeration model – getById() (public) ───────────────────────────────────

section('Enumeration model – getById()');
if ($firstEnumId) {
    $r = request('GET', "{$base}/enumerations/{$firstEnumId}", [], false);
    assert_test('getById 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has type + syscode', isset($r['data']['data']['type'], $r['data']['data']['syscode']));
}

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

// ── Enumeration model – create() ─────────────────────────────────────────────

section('Enumeration model – create()');
$enumType = TEST_PREFIX . 'model_type_' . time();
$r        = request('POST', "{$base}/enumerations", ['type' => $enumType, 'syscode' => 'syscode_m', 'label' => 'Code M']);
assert_test('create enumeration 201', $r['status'] === 201, dump_on_fail($r));
$enumId = $r['data']['data']['id'] ?? null;

// ── Enumeration model – update() ─────────────────────────────────────────────

section('Enumeration model – update()');
if ($enumId) {
    $r = request('PATCH', "{$base}/enumerations/{$enumId}", ['label' => 'Code M Patched']);
    assert_test('PATCH enumeration 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/enumerations/{$enumId}", [
        'type' => $enumType, 'syscode' => 'syscode_m', 'label' => 'Code M Updated',
    ]);
    assert_test('PUT enumeration 200', $r['status'] === 200, dump_on_fail($r));
}

// ── Enumeration model – delete() ─────────────────────────────────────────────

section('Enumeration model – delete()');
if ($enumId) {
    // Verify 'deleted' field is 0 before deletion.
    $r = request('GET', "{$base}/enumerations/{$enumId}", [], false);
    assert_test('deleted field is 0 before delete', ($r['data']['data']['deleted'] ?? -1) === 0, dump_on_fail($r));

    $r = request('DELETE', "{$base}/enumerations/{$enumId}");
    assert_test('delete enumeration 200', $r['status'] === 200, dump_on_fail($r));

    // Soft delete: GET by ID returns 404.
    $r = request('GET', "{$base}/enumerations/{$enumId}", [], false);
    assert_test('deleted enumeration → 404', $r['status'] === 404, dump_on_fail($r));

    // Soft delete: not visible in normal list.
    $r = request('GET', "{$base}/enumerations?q=" . urlencode(json_encode(['type' => $enumType])), [], false);
    assert_test('deleted enum hidden in list', ($r['data']['meta']['total'] ?? -1) === 0, dump_on_fail($r));

    // Soft delete: visible with deleted=1 filter.
    $r = request('GET', "{$base}/enumerations?q=" . urlencode(json_encode(['type' => $enumType, 'deleted' => 1])), [], false);
    assert_test('deleted enum visible with deleted:1', ($r['data']['meta']['total'] ?? 0) >= 1, dump_on_fail($r));
    assert_test('deleted field is 1 in trash', ($r['data']['data'][0]['deleted'] ?? 0) === 1, dump_on_fail($r));
}

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
