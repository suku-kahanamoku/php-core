#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Utils\RequestContext;

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}
require_once __DIR__ . '/../vendor/autoload.php';

section('Request context');
$requestId = RequestContext::begin();
assert_test('generates a 32-character request ID', preg_match('/^[a-f0-9]{32}$/', $requestId) === 1);
assert_test('returns the active request ID', RequestContext::id() === $requestId);

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
