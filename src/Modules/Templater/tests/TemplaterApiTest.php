#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API endpoint tests for App\Modules\Templater\TemplaterApi
 *
 * Tests all /templater/* routes
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

// ── Templater – missing template param ───────────────────────────────────────

section('Templater – validation');
$r = request('GET', "{$base}/templater", [], false);
assert_test('GET /templater without template → 422', $r['status'] === 422, dump_on_fail($r));

// ── Templater – unknown template ─────────────────────────────────────────────

section('Templater – unknown template');
$r = request('GET', "{$base}/templater?template=nonexistent/template", [], false);
assert_test('GET /templater with unknown template → 500', $r['status'] === 500, dump_on_fail($r));

// ── Templater – path traversal protection ────────────────────────────────────

section('Templater – path traversal protection');
$r = request('GET', "{$base}/templater?template=../../bootstrap", [], false);
assert_test('path traversal blocked → 500', $r['status'] === 500, dump_on_fail($r));

// ── Templater – valid template ────────────────────────────────────────────────

section('Templater – render valid template');
$r = request('GET', "{$base}/templater?template=mail/test", [], false);
assert_test('GET /templater?template=mail/test → 200', $r['status'] === 200, dump_on_fail($r));
assert_test('response is HTML', str_contains($r['raw'] ?? '', '<'), dump_on_fail($r));
