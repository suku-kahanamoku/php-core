#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit/integration tests for App\Modules\Text\Text
 *
 * Tests: getAll(), getById(), getByKey(), create(), update(), delete()
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

// ── Text model – getAll() (public) ────────────────────────────────────────────

section('Text model – getAll()');
$r = request('GET', "{$base}/texts", [], false);
assert_test('returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('data is array', is_array($r['data']['data']));

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

// ── Text model – create() ────────────────────────────────────────────────────

section('Text model – create()');
$textKey = 'model_key_' . time();
$r       = request('POST', "{$base}/texts", [
    'key' => $textKey, 'title' => 'Model Title', 'content' => 'Model content', 'language' => 'cs',
]);
assert_test('create text 201', $r['status'] === 201, dump_on_fail($r));
$textId = $r['data']['data']['id'] ?? null;

// ── Text model – getById() ────────────────────────────────────────────────────

section('Text model – getById()');
if ($textId) {
    $r = request('GET', "{$base}/texts/{$textId}");
    assert_test('getById 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has content field', isset($r['data']['data']['content']));
    assert_test('title matches', $r['data']['data']['title'] === 'Model Title');

    $r = request('GET', "{$base}/texts/999999");
    assert_test('unknown id → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Text model – getByKey() ───────────────────────────────────────────────────

section('Text model – getByKey()');
if ($textId) {
    $r = request('GET', "{$base}/texts/by-key/{$textKey}?language=cs");
    assert_test('getByKey 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('title matches', $r['data']['data']['title'] === 'Model Title');

    $r = request('GET', "{$base}/texts/by-key/nonexistent_key_xyz_abc");
    assert_test('unknown key → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Text model – update() ────────────────────────────────────────────────────

section('Text model – update()');
if ($textId) {
    $r = request('PATCH', "{$base}/texts/{$textId}", ['title' => 'Model Title Patched']);
    assert_test('PATCH text 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/texts/{$textId}", ['key' => $textKey, 'title' => 'Model Title Updated']);
    assert_test('PUT text 200', $r['status'] === 200, dump_on_fail($r));
}

// ── Text model – delete() ────────────────────────────────────────────────────

section('Text model – delete()');
if ($textId) {
    $r = request('DELETE', "{$base}/texts/{$textId}");
    assert_test('delete text 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/texts/{$textId}");
    assert_test('deleted text → 404', $r['status'] === 404, dump_on_fail($r));
}

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
