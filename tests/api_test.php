#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * php-core API – test runner
 * Usage: php tests/api_test.php [base_url]
 *
 * Runs all module tests (Model, Service, Api) and prints aggregated results.
 * Each test file can also be run standalone: php src/Modules/Auth/tests/AuthApiTest.php [base_url]
 */

require_once __DIR__ . '/bootstrap.php';

$base       = rtrim($argv[1] ?? 'http://localhost/php/php-core/api', '/');
$runnerMode = true;

// ── Pre-test cleanup: remove any leftover test data ──────────────────────────
echo "\033[1;33m[setup] Cleaning test data...\033[0m\n";
cleanup_test_data();
echo "\033[1;33m[setup] Done.\033[0m\n";

// ── Misc: 404 ────────────────────────────────────────────────────────────────

section('Non-existent endpoint');
$r = request('GET', "{$base}/nonexistent", [], false);
assert_test('404', $r['status'] === 404, dump_on_fail($r));
assert_test('success = false', $r['data']['success'] === false);

// ── Module tests ──────────────────────────────────────────────────────────────
// Each module has three test files: <Module>Test (model), <Module>ServiceTest, <Module>ApiTest

$modulesDir = __DIR__ . '/../src/Modules';

$tests = [
    // Utils
    __DIR__ . '/test_utils.php',
    // Filter
    __DIR__ . '/test_filter.php',
    // Projection
    __DIR__ . '/test_projection.php',
    // Auth
    "{$modulesDir}/Auth/tests/AuthTest.php",
    "{$modulesDir}/Auth/tests/AuthServiceTest.php",
    "{$modulesDir}/Auth/tests/AuthApiTest.php",
    // Role
    "{$modulesDir}/Role/tests/RoleTest.php",
    "{$modulesDir}/Role/tests/RoleServiceTest.php",
    "{$modulesDir}/Role/tests/RoleApiTest.php",
    // User
    "{$modulesDir}/User/tests/UserTest.php",
    "{$modulesDir}/User/tests/UserServiceTest.php",
    "{$modulesDir}/User/tests/UserApiTest.php",
    // Address
    "{$modulesDir}/Address/tests/AddressTest.php",
    "{$modulesDir}/Address/tests/AddressServiceTest.php",
    "{$modulesDir}/Address/tests/AddressApiTest.php",
    // Category
    "{$modulesDir}/Category/tests/CategoryTest.php",
    "{$modulesDir}/Category/tests/CategoryServiceTest.php",
    "{$modulesDir}/Category/tests/CategoryApiTest.php",
    // Product
    "{$modulesDir}/Product/tests/ProductTest.php",
    "{$modulesDir}/Product/tests/ProductServiceTest.php",
    "{$modulesDir}/Product/tests/ProductApiTest.php",
    // Enumeration
    "{$modulesDir}/Enumeration/tests/EnumerationTest.php",
    "{$modulesDir}/Enumeration/tests/EnumerationServiceTest.php",
    "{$modulesDir}/Enumeration/tests/EnumerationApiTest.php",
    // Text
    "{$modulesDir}/Text/tests/TextTest.php",
    "{$modulesDir}/Text/tests/TextServiceTest.php",
    "{$modulesDir}/Text/tests/TextApiTest.php",
    // Order
    "{$modulesDir}/Order/tests/OrderTest.php",
    "{$modulesDir}/Order/tests/OrderServiceTest.php",
    "{$modulesDir}/Order/tests/OrderApiTest.php",
    // Invoice
    "{$modulesDir}/Invoice/tests/InvoiceTest.php",
    "{$modulesDir}/Invoice/tests/InvoiceServiceTest.php",
    "{$modulesDir}/Invoice/tests/InvoiceApiTest.php",
];

foreach ($tests as $file) {
    $token = null;
    require $file;
}

// ── Summary ───────────────────────────────────────────────────────────────────

// Post-test cleanup: remove all test data created during this run
echo "\n\033[1;33m[teardown] Cleaning test data...\033[0m\n";
cleanup_test_data();
echo "\033[1;33m[teardown] Done.\033[0m\n";

print_results();
exit($failed > 0 ? 1 : 0);
