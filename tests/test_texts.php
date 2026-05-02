#!/usr/bin/env php
<?php

declare(strict_types=1);

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/bootstrap.php';
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

section('Texts – public list');
$r = request('GET', "{$base}/texts", [], false);
assert_test('GET /texts 200',               $r['status'] === 200, dump_on_fail($r));
assert_test('data is array',                is_array($r['data']['data']));

// ── Admin login ───────────────────────────────────────────────────────────────

section('Texts – admin login');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200',              $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

// ── Create ────────────────────────────────────────────────────────────────────

section('Texts – CRUD');
$textKey = 'test_key_' . time();
$r = request('POST', "{$base}/texts", [
    'key' => $textKey, 'title' => 'Test Title', 'content' => 'Test content', 'language' => 'cs',
]);
assert_test('POST /texts 201',              $r['status'] === 201, dump_on_fail($r));
$textId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/texts", ['key' => $textKey, 'title' => 'Dup', 'language' => 'cs']);
assert_test('POST /texts 409 duplicate key+lang', $r['status'] === 409, dump_on_fail($r));

if ($textId) {
    $r = request('GET', "{$base}/texts/{$textId}");
    assert_test('GET /texts/:id 200',       $r['status'] === 200, dump_on_fail($r));
    assert_test('has content field',        isset($r['data']['data']['content']));

    $r = request('GET', "{$base}/texts/by-key/{$textKey}?language=cs");
    assert_test('GET /texts/by-key/:key 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('title matches',            $r['data']['data']['title'] === 'Test Title');

    $r = request('GET', "{$base}/texts/by-key/nonexistent_key_xyz_abc");
    assert_test('GET /texts/by-key 404 unknown key', $r['status'] === 404, dump_on_fail($r));

    $r = request('PATCH', "{$base}/texts/{$textId}", ['title' => 'Patched Title']);
    assert_test('PATCH /texts/:id 200',     $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/texts/{$textId}", ['key' => $textKey, 'title' => 'Replaced Title']);
    assert_test('PUT /texts/:id 200',       $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/texts/{$textId}", ['key' => '', 'title' => 'x']);
    assert_test('PUT /texts/:id 422 empty key', $r['status'] === 422, dump_on_fail($r));

    $r = request('DELETE', "{$base}/texts/{$textId}");
    assert_test('DELETE /texts/:id 200',    $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/texts/{$textId}");
    assert_test('GET /texts/:id 404 after delete', $r['status'] === 404, dump_on_fail($r));
}

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
