#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration tests for App\Modules\Text\TextService
 *
 * Tests business logic: validation, duplicate key+language prevention
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

// ── TextService – validation ──────────────────────────────────────────────────

section('TextService – create() validation');
$r = request('POST', "{$base}/texts", ['title' => 'x', 'language' => 'cs']);
assert_test('missing syscode → 422', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/texts", ['key' => '', 'title' => 'x', 'language' => 'cs']);
assert_test('empty syscode → 422', $r['status'] === 422, dump_on_fail($r));

// ── TextService – duplicate key+language prevention ───────────────────────────

section('TextService – duplicate key+language prevention');
$svcKey = 'svc_syscode_' . time();
$r      = request('POST', "{$base}/texts", ['syscode' => $svcKey, 'title' => 'Svc Title', 'language' => 'cs']);
assert_test('create text 201', $r['status'] === 201, dump_on_fail($r));
$svcTextId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/texts", ['syscode' => $svcKey, 'title' => 'Dup', 'language' => 'cs']);
assert_test('duplicate key+lang → 409', $r['status'] === 409, dump_on_fail($r));

// Different language should succeed
$r = request('POST', "{$base}/texts", ['syscode' => $svcKey, 'title' => 'EN Title', 'language' => 'en']);
assert_test('same key different language → 201', $r['status'] === 201, dump_on_fail($r));
$svcTextEnId = $r['data']['data']['id'] ?? null;

// ── TextService – update() validation ────────────────────────────────────────

section('TextService – update() validation');
if ($svcTextId) {
    $r = request('PUT', "{$base}/texts/{$svcTextId}", ['syscode' => '', 'title' => 'x']);
    assert_test('PUT empty syscode → 422', $r['status'] === 422, dump_on_fail($r));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if ($svcTextId) {
    request('DELETE', "{$base}/texts/{$svcTextId}");
}
if ($svcTextEnId) {
    request('DELETE', "{$base}/texts/{$svcTextEnId}");
}
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
