#!/usr/bin/env php
<?php

declare(strict_types=1);

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

section('Customer profiles – setup');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'admin123'], false);
assert_test('admin login 200', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$syscode = TEST_PREFIX . 'public_profile_' . time();
$r = request('POST', "{$base}/customer-profiles", [
    'profile_number' => 32000,
    'syscode' => $syscode,
    'name' => 'Public profile test',
    'published' => 1,
    'questions' => ['Test question'],
]);
assert_test('create published profile 201', $r['status'] === 201, dump_on_fail($r));
$profileId = $r['data']['data']['id'] ?? null;

section('Customer profiles – public detail');
$savedToken = $token;
$token = null;
$r = request('GET', "{$base}/customer-profiles", [], false);
assert_test('GET /customer-profiles stays protected', $r['status'] === 401, dump_on_fail($r));
if ($profileId) {
    $r = request('GET', "{$base}/customer-profiles/{$profileId}", [], false);
    assert_test('published detail is 200 without token', $r['status'] === 200, dump_on_fail($r));
    assert_test('public detail contains questions', ($r['data']['data']['questions'][0] ?? null) === 'Test question', dump_on_fail($r));
    assert_test('public detail hides franchise_code', !array_key_exists('franchise_code', $r['data']['data'] ?? []), dump_on_fail($r));
}

section('Customer profiles – unpublished detail');
$token = $savedToken;
if ($profileId) {
    $r = request('PATCH', "{$base}/customer-profiles/{$profileId}", ['published' => 0]);
    assert_test('admin can unpublish profile', $r['status'] === 200, dump_on_fail($r));

    $token = null;
    $r = request('GET', "{$base}/customer-profiles/{$profileId}", [], false);
    assert_test('unpublished detail is 404 without token', $r['status'] === 404, dump_on_fail($r));

    $token = $savedToken;
    $r = request('GET', "{$base}/customer-profiles/{$profileId}");
    assert_test('unpublished detail is 200 for admin', $r['status'] === 200, dump_on_fail($r));
    request('DELETE', "{$base}/customer-profiles/{$profileId}");
}
$token = null;

if (!isset($runnerMode)) {
    cleanup_test_data();
    print_results();
    exit($failed > 0 ? 1 : 0);
}
