#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API endpoint tests for App\Modules\Mailer\MailerApi
 *
 * Tests all /mailer/* routes
 * NOTE: Actual email delivery is NOT tested (requires live SMTP).
 *       Tests cover validation, routing and error responses only.
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

// ── Mailer – GET / validation ─────────────────────────────────────────────────

section('Mailer – validation: missing fields');
$r = request('GET', "{$base}/mailer", [], false);
assert_test('GET /mailer without params → 422', $r['status'] === 422, dump_on_fail($r));
assert_test('errors object present', isset($r['data']['errors']), dump_on_fail($r));

section('Mailer – validation: invalid email');
$r = request('GET', "{$base}/mailer?to=not-an-email&subject=Test&template=mail/test&fromEmail=also-invalid&fromName=Test&fromPhone=123", [], false);
assert_test('invalid to email → 422', $r['status'] === 422, dump_on_fail($r));

section('Mailer – validation: valid params but SMTP likely unavailable');
$r = request('GET', "{$base}/mailer?to=test@example.com&subject=Test&template=mail/test&fromEmail=sender@example.com&fromName=Test&fromPhone=123456789", [], false);
// Buď 200 (SMTP dostupné) nebo 500 (SMTP nedostupné) — obojí je validní chování
assert_test('GET /mailer with valid params → not 422', $r['status'] !== 422, dump_on_fail($r));

// ── Mailer – GET /test validation ─────────────────────────────────────────────

section('Mailer – /test: missing email param');
$r = request('GET', "{$base}/mailer/test", [], false);
assert_test('GET /mailer/test without email → 400', $r['status'] === 400, dump_on_fail($r));

section('Mailer – /test: invalid email param');
$r = request('GET', "{$base}/mailer/test?email=not-an-email", [], false);
assert_test('GET /mailer/test with invalid email → 400', $r['status'] === 400, dump_on_fail($r));

section('Mailer – /test: valid email param');
$r = request('GET', "{$base}/mailer/test?email=test@example.com", [], false);
// Buď 200 (SMTP dostupné) nebo 500 (SMTP nedostupné) — obojí je validní chování
assert_test('GET /mailer/test with valid email → not 400/422', !in_array($r['status'], [400, 422]), dump_on_fail($r));
