#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * php-core API – test runner
 * Usage: php tests/api_test.php [base_url]
 *
 * Runs all test_*.php files and prints aggregated results.
 * Each test file can also be run standalone: php tests/test_auth.php [base_url]
 */

require_once __DIR__ . '/bootstrap.php';

$base        = rtrim($argv[1] ?? 'http://localhost/php/php-core/api', '/');
$runnerMode  = true;

// ── Misc: endpoint listing + 404 ─────────────────────────────────────────────

section('GET /  –  endpoint listing');
$r = request('GET', "{$base}/", [], false);
assert_test('200',                      $r['status'] === 200, dump_on_fail($r));
assert_test('success = true',           $r['data']['success'] === true);
assert_test('data.endpoints exists',    isset($r['data']['data']['endpoints']));
assert_test('lists auth endpoints',     isset($r['data']['data']['endpoints']['auth']));
assert_test('lists products endpoints', isset($r['data']['data']['endpoints']['products']));

section('Non-existent endpoint');
$r = request('GET', "{$base}/nonexistent", [], false);
assert_test('404',                      $r['status'] === 404, dump_on_fail($r));
assert_test('success = false',          $r['data']['success'] === false);

// ── Individual test files ─────────────────────────────────────────────────────

$tests = [
    'test_auth.php',
    'test_roles.php',
    'test_users.php',
    'test_addresses.php',
    'test_categories.php',
    'test_products.php',
    'test_enumerations.php',
    'test_texts.php',
    'test_orders.php',
    'test_invoices.php',
];

foreach ($tests as $file) {
    $token = null;
    require __DIR__ . '/' . $file;
}

// ── Summary ───────────────────────────────────────────────────────────────────

print_results();
exit($failed > 0 ? 1 : 0);
