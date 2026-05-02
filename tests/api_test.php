#!/usr/bin/env php
<?php

/**
 * php-core API – test runner
 * Usage: php tests/api_test.php [base_url]
 *
 * Default base URL: http://localhost/php/php-core/api
 */

$base = rtrim($argv[1] ?? 'http://localhost/php/php-core/api', '/');

// ── Helpers ──────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;
$token  = null; // holds the current Bearer token

function request(string $method, string $url, array $body = [], bool $withAuth = true): array
{
    global $token;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($withAuth && $token !== null) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['status' => 0, 'data' => [], 'raw' => $error];
    }

    $data = json_decode($raw, true) ?? [];
    return ['status' => $status, 'data' => $data, 'raw' => $raw];
}

function assert_test(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo "\033[32m  ✓\033[0m {$name}\n";
        $passed++;
    } else {
        echo "\033[31m  ✗\033[0m {$name}" . ($detail ? "  → {$detail}" : '') . "\n";
        $failed++;
    }
}

function section(string $title): void
{
    echo "\n\033[1;34m══ {$title} \033[0m\n";
}

function dump_on_fail(array $res): string
{
    return "HTTP {$res['status']} | " . substr($res['raw'], 0, 200);
}

// Reset token before run
$token = null;

// ─────────────────────────────────────────────────────────────────────────────

section('GET /  –  endpoint listing');
$r = request('GET', "{$base}/");
assert_test('returns HTTP 200',          $r['status'] === 200,          dump_on_fail($r));
assert_test('success = true',            $r['data']['success'] === true, dump_on_fail($r));
assert_test('data.endpoints exists',     isset($r['data']['data']['endpoints']));
assert_test('lists auth endpoints',      isset($r['data']['data']['endpoints']['auth']));
assert_test('lists products endpoints',  isset($r['data']['data']['endpoints']['products']));

// ─────────────────────────────────────────────────────────────────────────────

section('POST /auth/login  –  validation errors');
$r = request('POST', "{$base}/auth/login", []);
assert_test('returns 422 on empty body',  $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/auth/login", ['email' => 'notanemail', 'password' => '123']);
assert_test('returns 422 on bad email',   $r['status'] === 422, dump_on_fail($r));

// ─────────────────────────────────────────────────────────────────────────────

section('POST /auth/login  –  wrong credentials');
$r = request('POST', "{$base}/auth/login", ['email' => 'nobody@example.com', 'password' => 'wrongpass']);
assert_test('returns 401 for unknown user', $r['status'] === 401, dump_on_fail($r));

// ─────────────────────────────────────────────────────────────────────────────

section('POST /auth/register  –  create test user');
$testEmail = 'testuser_' . time() . '@example.com';
$r = request('POST', "{$base}/auth/register", [
    'first_name' => 'Test',
    'last_name'  => 'User',
    'email'      => $testEmail,
    'password'   => 'TestPass123',
]);
assert_test('returns 201',           $r['status'] === 201,          dump_on_fail($r));
assert_test('success = true',        $r['data']['success'] === true, dump_on_fail($r));
assert_test('returns new user id',   isset($r['data']['data']['id']));

$testUserId = $r['data']['data']['id'] ?? null;

$r2 = request('POST', "{$base}/auth/register", [
    'first_name' => 'Test',
    'last_name'  => 'User',
    'email'      => $testEmail,
    'password'   => 'TestPass123',
]);
assert_test('409 on duplicate email',  $r2['status'] === 409, dump_on_fail($r2));

// ─────────────────────────────────────────────────────────────────────────────

section('POST /auth/login  –  valid login');
$r = request('POST', "{$base}/auth/login", ['email' => $testEmail, 'password' => 'TestPass123']);
assert_test('returns 200',           $r['status'] === 200,          dump_on_fail($r));
assert_test('success = true',        $r['data']['success'] === true, dump_on_fail($r));
assert_test('returns email',         $r['data']['data']['email'] === $testEmail);
assert_test('returns role',          isset($r['data']['data']['role']));
assert_test('returns token',         isset($r['data']['data']['token']), dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

// ─────────────────────────────────────────────────────────────────────────────

section('GET /auth/me  –  bearer token check');
$r = request('GET', "{$base}/auth/me");
assert_test('returns 200 when logged in',  $r['status'] === 200, dump_on_fail($r));
assert_test('returns user email',          $r['data']['data']['email'] === $testEmail);

// ─────────────────────────────────────────────────────────────────────────────

section('GET /products  –  public');
$r = request('GET', "{$base}/products", [], false);  // no token
assert_test('returns 200 without token', $r['status'] === 200, dump_on_fail($r));

$r = request('GET', "{$base}/products");  // with Bearer token
assert_test('returns 200 with token',    $r['status'] === 200, dump_on_fail($r));
assert_test('has items array',             isset($r['data']['data']['items']));
assert_test('has total',                   isset($r['data']['data']['total']));

// ─────────────────────────────────────────────────────────────────────────────

section('GET /categories');
$r = request('GET', "{$base}/categories");
assert_test('returns 200',      $r['status'] === 200, dump_on_fail($r));
assert_test('data is array',    is_array($r['data']['data']));

// ─────────────────────────────────────────────────────────────────────────────

section('GET /enumerations');
$r = request('GET', "{$base}/enumerations");
assert_test('returns 200',              $r['status'] === 200, dump_on_fail($r));
assert_test('has order_status group',   isset($r['data']['data']['order_status']));
assert_test('has invoice_status group', isset($r['data']['data']['invoice_status']));

$r = request('GET', "{$base}/enumerations/types");
assert_test('GET /enumerations/types returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('is array of strings',                 is_array($r['data']['data']));

// ─────────────────────────────────────────────────────────────────────────────

section('GET /orders  –  non-admin sees only own orders');
$r = request('GET', "{$base}/orders");
assert_test('returns 200',      $r['status'] === 200, dump_on_fail($r));
assert_test('has items array',  isset($r['data']['data']['items']));

// ─────────────────────────────────────────────────────────────────────────────

section('GET /invoices');
$r = request('GET', "{$base}/invoices");
assert_test('returns 200',      $r['status'] === 200, dump_on_fail($r));
assert_test('has items array',  isset($r['data']['data']['items']));

// ─────────────────────────────────────────────────────────────────────────────

section('GET /texts');
$r = request('GET', "{$base}/texts");
assert_test('returns 200',    $r['status'] === 200, dump_on_fail($r));
assert_test('data is array',  is_array($r['data']['data']));

// ─────────────────────────────────────────────────────────────────────────────

section('POST /auth/change-password');
$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => 'WrongPass999',
    'new_password'     => 'NewPass123',
]);
assert_test('401 on wrong current password',  $r['status'] === 401, dump_on_fail($r));

$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => 'TestPass123',
    'new_password'     => 'short',
]);
assert_test('422 on short new password',      $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => 'TestPass123',
    'new_password'     => 'NewPass123!',
]);
assert_test('200 on valid change',            $r['status'] === 200, dump_on_fail($r));

// ─────────────────────────────────────────────────────────────────────────────

section('Non-existent endpoint');
$r = request('GET', "{$base}/nonexistent");
assert_test('returns 404',    $r['status'] === 404, dump_on_fail($r));
assert_test('success = false', $r['data']['success'] === false);

// ─────────────────────────────────────────────────────────────────────────────

section('POST /auth/logout');
$r = request('POST', "{$base}/auth/logout");
assert_test('returns 200',     $r['status'] === 200, dump_on_fail($r));
$token = null;

$r = request('GET', "{$base}/auth/me");
assert_test('401 after logout', $r['status'] === 401, dump_on_fail($r));

// ─────────────────────────────────────────────────────────────────────────────

section('Admin login & protected routes');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password']);
assert_test('admin login 200',    $r['status'] === 200, dump_on_fail($r));
assert_test('role = admin',       $r['data']['data']['role'] === 'admin', dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$r = request('GET', "{$base}/users");
assert_test('GET /users 200 as admin',   $r['status'] === 200, dump_on_fail($r));
assert_test('has items',                 isset($r['data']['data']['items']));

// Create a category as admin
$r = request('POST', "{$base}/categories", ['name' => 'Test Category', 'slug' => 'test-category-' . time()]);
assert_test('POST /categories 201',      $r['status'] === 201, dump_on_fail($r));
$catId = $r['data']['data']['id'] ?? null;

// Create a product as admin
if ($catId) {
    $r = request('POST', "{$base}/products", [
        'name'        => 'Test Product',
        'sku'         => 'TEST-' . time(),
        'price'       => 299.90,
        'category_id' => $catId,
        'stock_quantity' => 10,
    ]);
    assert_test('POST /products 201',       $r['status'] === 201, dump_on_fail($r));
    $productId = $r['data']['data']['id'] ?? null;

    if ($productId) {
        $r = request('GET', "{$base}/products/{$productId}");
        assert_test("GET /products/{$productId} 200",  $r['status'] === 200, dump_on_fail($r));
        assert_test('product name matches',            $r['data']['data']['name'] === 'Test Product');

        $r = request('PATCH', "{$base}/products/{$productId}/stock", ['quantity' => 5]);
        assert_test('PATCH /products/:id/stock 200',   $r['status'] === 200, dump_on_fail($r));
        assert_test('stock_quantity = 15',             $r['data']['data']['stock_quantity'] === 15, dump_on_fail($r));
    }
}

// Create enumeration
$r = request('POST', "{$base}/enumerations", [
    'type'  => 'test_type',
    'code'  => 'code_a',
    'label' => 'Code A',
]);
assert_test('POST /enumerations 201',    $r['status'] === 201, dump_on_fail($r));
$enumId = $r['data']['data']['id'] ?? null;

if ($enumId) {
    $r = request('PUT', "{$base}/enumerations/{$enumId}", ['label' => 'Code A Updated']);
    assert_test('PUT /enumerations/:id 200',   $r['status'] === 200, dump_on_fail($r));

    $r = request('DELETE', "{$base}/enumerations/{$enumId}");
    assert_test('DELETE /enumerations/:id 200', $r['status'] === 200, dump_on_fail($r));
}

// Create text block
$r = request('POST', "{$base}/texts", [
    'key'      => 'test_key_' . time(),
    'title'    => 'Test Title',
    'content'  => 'Test content',
    'language' => 'cs',
]);
assert_test('POST /texts 201',    $r['status'] === 201, dump_on_fail($r));

// ─────────────────────────────────────────────────────────────────────────────

// Summary
$total = $passed + $failed;
echo "\n\033[1m──────────────────────────────\033[0m\n";
echo "\033[1mVýsledky:\033[0m  ";
echo "\033[32m{$passed} passed\033[0m  ";
if ($failed > 0) {
    echo "\033[31m{$failed} failed\033[0m";
} else {
    echo "\033[32m0 failed\033[0m";
}
echo "  /  {$total} total\n\n";

exit($failed > 0 ? 1 : 0);
