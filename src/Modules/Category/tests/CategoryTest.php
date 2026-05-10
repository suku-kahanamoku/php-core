#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit/integration tests for App\Modules\Category\Category
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

// ── Category model – getAll() (public) ───────────────────────────────────────

section('Category model – getAll()');
$r = request('GET', "{$base}/categories", [], false);
assert_test('returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('data is array', is_array($r['data']['data']));

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

// ── Category model – create() ────────────────────────────────────────────────

section('Category model – create()');
$r       = request('POST', "{$base}/categories", ['name' => TEST_PREFIX . 'model_category_' . time()]);
assert_test('create category 201', $r['status'] === 201, dump_on_fail($r));
$catId = $r['data']['data']['id'] ?? null;

// ── Category model – getById() (public) ──────────────────────────────────────

section('Category model – getById()');
if ($catId) {
    $r = request('GET', "{$base}/categories/{$catId}", [], false);
    assert_test('getById 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has products array', isset($r['data']['data']['products']));
    assert_test('name matches', str_starts_with($r['data']['data']['name'], TEST_PREFIX . 'model_category_'), dump_on_fail($r));

    $r = request('GET', "{$base}/categories/999999", [], false);
    assert_test('unknown id → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Category model – update() ────────────────────────────────────────────────

section('Category model – update()');
if ($catId) {
    $r = request('PATCH', "{$base}/categories/{$catId}", ['description' => 'Model desc']);
    assert_test('PATCH category 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/categories/{$catId}", ['name' => 'Model Category Updated']);
    assert_test('PUT category 200', $r['status'] === 200, dump_on_fail($r));
}

// ── Category model – delete() ────────────────────────────────────────────────

section('Category model – delete()');
if ($catId) {
    $r = request('DELETE', "{$base}/categories/{$catId}");
    assert_test('delete category 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/categories/{$catId}", [], false);
    assert_test('deleted category → 404', $r['status'] === 404, dump_on_fail($r));
}

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
