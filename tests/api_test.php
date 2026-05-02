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
$token  = null;

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

$token = null;

// ═════════════════════════════════════════════════════════════════════════════
// SETUP  –  register test user + admin creates base fixtures
// ═════════════════════════════════════════════════════════════════════════════

section('SETUP – register test user');
$testEmail    = 'testuser_' . time() . '@example.com';
$testPassword = 'TestPass123';
$r = request('POST', "{$base}/auth/register", [
    'first_name' => 'Test', 'last_name' => 'User',
    'email' => $testEmail, 'password' => $testPassword,
], false);
assert_test('POST /auth/register 201',   $r['status'] === 201, dump_on_fail($r));
assert_test('returns new user id',       isset($r['data']['data']['id']), dump_on_fail($r));
$testUserId = $r['data']['data']['id'] ?? null;

section('SETUP – admin creates fixtures (category + product)');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login for setup',    $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$setupSlug = 'setup-cat-' . time();
$r = request('POST', "{$base}/categories", ['name' => 'Setup Category', 'slug' => $setupSlug]);
assert_test('setup: create category',   $r['status'] === 201, dump_on_fail($r));
$setupCatId = $r['data']['data']['id'] ?? null;

$setupSku = 'SETUP-' . time();
$r = request('POST', "{$base}/products", [
    'name' => 'Setup Product', 'sku' => $setupSku,
    'price' => 199.00, 'category_id' => $setupCatId, 'stock_quantity' => 10,
]);
assert_test('setup: create product',    $r['status'] === 201, dump_on_fail($r));
$setupProductId = $r['data']['data']['id'] ?? null;

$token = null;

// ═════════════════════════════════════════════════════════════════════════════
// AUTH – validation & error cases
// ═════════════════════════════════════════════════════════════════════════════

section('POST /auth/login – validation errors');
$r = request('POST', "{$base}/auth/login", [], false);
assert_test('422 on empty body',         $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/auth/login", ['email' => 'notanemail', 'password' => '123'], false);
assert_test('422 on invalid email',      $r['status'] === 422, dump_on_fail($r));

section('POST /auth/login – wrong credentials');
$r = request('POST', "{$base}/auth/login", ['email' => 'nobody@example.com', 'password' => 'wrong'], false);
assert_test('401 for unknown user',      $r['status'] === 401, dump_on_fail($r));

section('POST /auth/register – duplicate email');
$r = request('POST', "{$base}/auth/register", [
    'first_name' => 'Test', 'last_name' => 'User',
    'email' => $testEmail, 'password' => $testPassword,
], false);
assert_test('409 on duplicate email',    $r['status'] === 409, dump_on_fail($r));

section('POST /auth/login – valid (test user)');
$r = request('POST', "{$base}/auth/login", ['email' => $testEmail, 'password' => $testPassword], false);
assert_test('200 on valid login',        $r['status'] === 200, dump_on_fail($r));
assert_test('returns token',             isset($r['data']['data']['token']), dump_on_fail($r));
assert_test('returns email',             $r['data']['data']['email'] === $testEmail);
assert_test('returns role',              isset($r['data']['data']['role']));
$token = $r['data']['data']['token'] ?? null;

section('GET /auth/me');
$r = request('GET', "{$base}/auth/me");
assert_test('200 with valid token',      $r['status'] === 200, dump_on_fail($r));
assert_test('returns correct email',     $r['data']['data']['email'] === $testEmail);

$r = request('GET', "{$base}/auth/me", [], false);
assert_test('401 without token',         $r['status'] === 401, dump_on_fail($r));

// ═════════════════════════════════════════════════════════════════════════════
// PUBLIC ROUTES
// ═════════════════════════════════════════════════════════════════════════════

section('GET /  –  endpoint listing');
$r = request('GET', "{$base}/", [], false);
assert_test('200',                       $r['status'] === 200, dump_on_fail($r));
assert_test('success = true',            $r['data']['success'] === true);
assert_test('data.endpoints exists',     isset($r['data']['data']['endpoints']));
assert_test('lists auth endpoints',      isset($r['data']['data']['endpoints']['auth']));
assert_test('lists products endpoints',  isset($r['data']['data']['endpoints']['products']));

section('GET /products – public');
$r = request('GET', "{$base}/products", [], false);
assert_test('200 without token',         $r['status'] === 200, dump_on_fail($r));
assert_test('has items array',           isset($r['data']['data']['items']));
assert_test('has total',                 isset($r['data']['data']['total']));
assert_test('has page/limit/totalPages', isset($r['data']['data']['page'], $r['data']['data']['limit'], $r['data']['data']['totalPages']));

section('GET /products/:id – public');
if ($setupProductId) {
    $r = request('GET', "{$base}/products/{$setupProductId}", [], false);
    assert_test('200 without token',     $r['status'] === 200, dump_on_fail($r));
    assert_test('name matches',          $r['data']['data']['name'] === 'Setup Product');
    assert_test('has category_name',     isset($r['data']['data']['category_name']));

    $r = request('GET', "{$base}/products/999999", [], false);
    assert_test('404 for unknown id',    $r['status'] === 404, dump_on_fail($r));
}

section('GET /categories – public');
$r = request('GET', "{$base}/categories", [], false);
assert_test('200',                       $r['status'] === 200, dump_on_fail($r));
assert_test('data is array',             is_array($r['data']['data']));

section('GET /categories/:id – public');
if ($setupCatId) {
    $r = request('GET', "{$base}/categories/{$setupCatId}", [], false);
    assert_test('200',                   $r['status'] === 200, dump_on_fail($r));
    assert_test('has products array',    isset($r['data']['data']['products']));
}

section('GET /enumerations – public');
$r = request('GET', "{$base}/enumerations", [], false);
assert_test('200',                       $r['status'] === 200, dump_on_fail($r));
assert_test('has order_status group',    isset($r['data']['data']['order_status']));
assert_test('has invoice_status group',  isset($r['data']['data']['invoice_status']));

$firstEnumId = null;
if (!empty($r['data']['data'])) {
    $firstGroup  = reset($r['data']['data']);
    $firstEnumId = $firstGroup[0]['id'] ?? null;
}

section('GET /enumerations/types – public');
$r = request('GET', "{$base}/enumerations/types", [], false);
assert_test('200',                       $r['status'] === 200, dump_on_fail($r));
assert_test('is array of strings',       is_array($r['data']['data']));

section('GET /enumerations/:id – public');
if ($firstEnumId) {
    $r = request('GET', "{$base}/enumerations/{$firstEnumId}", [], false);
    assert_test('200',                   $r['status'] === 200, dump_on_fail($r));
    assert_test('has type + code',       isset($r['data']['data']['type'], $r['data']['data']['code']));
}

section('GET /texts – public');
$r = request('GET', "{$base}/texts", [], false);
assert_test('200',                       $r['status'] === 200, dump_on_fail($r));
assert_test('data is array',             is_array($r['data']['data']));

section('Non-existent endpoint');
$r = request('GET', "{$base}/nonexistent", [], false);
assert_test('404',                       $r['status'] === 404, dump_on_fail($r));
assert_test('success = false',           $r['data']['success'] === false);

// ═════════════════════════════════════════════════════════════════════════════
// NON-ADMIN PROTECTION
// ═════════════════════════════════════════════════════════════════════════════

section('Non-admin protection – 403 on admin-only routes');
$r = request('POST', "{$base}/products", ['name' => 'x', 'sku' => 'x', 'price' => 1]);
assert_test('POST /products → 403',      $r['status'] === 403, dump_on_fail($r));

$r = request('POST', "{$base}/categories", ['name' => 'x']);
assert_test('POST /categories → 403',    $r['status'] === 403, dump_on_fail($r));

$r = request('GET', "{$base}/users");
assert_test('GET /users → 403',          $r['status'] === 403, dump_on_fail($r));

if ($setupProductId) {
    $r = request('DELETE', "{$base}/products/{$setupProductId}");
    assert_test('DELETE /products/:id → 403', $r['status'] === 403, dump_on_fail($r));
}

$r = request('POST', "{$base}/invoices", ['order_id' => 1]);
assert_test('POST /invoices → 403',      $r['status'] === 403, dump_on_fail($r));

// ═════════════════════════════════════════════════════════════════════════════
// CHANGE PASSWORD
// ═════════════════════════════════════════════════════════════════════════════

section('POST /auth/change-password');
$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => 'WrongPass999', 'new_password' => 'NewPass123!',
]);
assert_test('401 on wrong current password', $r['status'] === 401, dump_on_fail($r));

$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => $testPassword, 'new_password' => 'short',
]);
assert_test('422 on short new password',     $r['status'] === 422, dump_on_fail($r));

$newPassword = 'NewPass123!';
$r = request('POST', "{$base}/auth/change-password", [
    'current_password' => $testPassword, 'new_password' => $newPassword,
]);
assert_test('200 on valid change',           $r['status'] === 200, dump_on_fail($r));

// ═════════════════════════════════════════════════════════════════════════════
// LOGOUT
// ═════════════════════════════════════════════════════════════════════════════

section('POST /auth/logout');
$r = request('POST', "{$base}/auth/logout");
assert_test('200',                       $r['status'] === 200, dump_on_fail($r));
$token = null;

$r = request('GET', "{$base}/auth/me");
assert_test('401 after logout',          $r['status'] === 401, dump_on_fail($r));

// ═════════════════════════════════════════════════════════════════════════════
// ADMIN – full CRUD
// ═════════════════════════════════════════════════════════════════════════════

section('Admin login');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('admin login 200',           $r['status'] === 200, dump_on_fail($r));
assert_test('role = admin',              $r['data']['data']['role'] === 'admin', dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

// ─── Users ────────────────────────────────────────────────────────────────────

section('Users CRUD');
$r = request('GET', "{$base}/users");
assert_test('GET /users 200',            $r['status'] === 200, dump_on_fail($r));
assert_test('has items + total',         isset($r['data']['data']['items'], $r['data']['data']['total']));

if ($testUserId) {
    $r = request('GET', "{$base}/users/{$testUserId}");
    assert_test('GET /users/:id 200',    $r['status'] === 200, dump_on_fail($r));
    assert_test('email matches',         $r['data']['data']['email'] === $testEmail);

    $r = request('PATCH', "{$base}/users/{$testUserId}", ['phone' => '+420123456789']);
    assert_test('PATCH /users/:id 200',  $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/users/{$testUserId}", ['first_name' => 'Updated', 'last_name' => 'Name']);
    assert_test('PUT /users/:id 200',    $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/users/{$testUserId}", ['first_name' => '', 'last_name' => 'Name']);
    assert_test('PUT /users/:id 422 empty first_name', $r['status'] === 422, dump_on_fail($r));
}

$newUserEmail = 'created_' . time() . '@example.com';
$r = request('POST', "{$base}/users", [
    'first_name' => 'Created', 'last_name' => 'ByAdmin',
    'email' => $newUserEmail, 'password' => 'Password123',
]);
assert_test('POST /users 201',           $r['status'] === 201, dump_on_fail($r));
$createdUserId = $r['data']['data']['id'] ?? null;

if ($createdUserId) {
    $r = request('DELETE', "{$base}/users/{$createdUserId}");
    assert_test('DELETE /users/:id 200', $r['status'] === 200, dump_on_fail($r));
}

// ─── Addresses ───────────────────────────────────────────────────────────────

section('Addresses CRUD');
$r = request('POST', "{$base}/addresses", [
    'user_id' => $testUserId, 'type' => 'billing',
    'street' => 'Testovací 1', 'city' => 'Praha', 'zip' => '11000', 'country' => 'CZ',
]);
assert_test('POST /addresses 201',       $r['status'] === 201, dump_on_fail($r));
$addrId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/addresses", ['type' => 'billing']);
assert_test('POST /addresses 422 missing fields', $r['status'] === 422, dump_on_fail($r));

if ($testUserId) {
    $r = request('GET', "{$base}/users/{$testUserId}/addresses");
    assert_test('GET /users/:userId/addresses 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('returns array',         is_array($r['data']['data']));
}

if ($addrId) {
    $r = request('GET', "{$base}/addresses/{$addrId}");
    assert_test('GET /addresses/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('street matches',        $r['data']['data']['street'] === 'Testovací 1');

    $r = request('PATCH', "{$base}/addresses/{$addrId}", ['city' => 'Brno']);
    assert_test('PATCH /addresses/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/addresses/{$addrId}", [
        'type' => 'shipping', 'street' => 'Nová 5',
        'city' => 'Brno', 'zip' => '60200', 'country' => 'CZ',
    ]);
    assert_test('PUT /addresses/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/addresses/{$addrId}", ['street' => 'x', 'city' => '', 'zip' => '1', 'country' => 'CZ']);
    assert_test('PUT /addresses/:id 422 missing city', $r['status'] === 422, dump_on_fail($r));

    $r = request('DELETE', "{$base}/addresses/{$addrId}");
    assert_test('DELETE /addresses/:id 200', $r['status'] === 200, dump_on_fail($r));
}

// ─── Categories ──────────────────────────────────────────────────────────────

section('Categories CRUD');
$emptySlug = 'empty-cat-' . time();
$r = request('POST', "{$base}/categories", ['name' => 'Empty Category', 'slug' => $emptySlug]);
assert_test('POST /categories 201',      $r['status'] === 201, dump_on_fail($r));
$emptyCatId = $r['data']['data']['id'] ?? null;

$r = request('GET', "{$base}/categories");
assert_test('GET /categories 200',       $r['status'] === 200, dump_on_fail($r));

if ($setupCatId) {
    $r = request('PATCH', "{$base}/categories/{$setupCatId}", ['description' => 'Updated desc']);
    assert_test('PATCH /categories/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/categories/{$setupCatId}", ['name' => 'Setup Category Updated']);
    assert_test('PUT /categories/:id 200',   $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/categories/{$setupCatId}", ['name' => '']);
    assert_test('PUT /categories/:id 422 empty name', $r['status'] === 422, dump_on_fail($r));

    $r = request('DELETE', "{$base}/categories/{$setupCatId}");
    assert_test('DELETE /categories/:id 409 (has product)', $r['status'] === 409, dump_on_fail($r));
}

if ($emptyCatId) {
    $r = request('DELETE', "{$base}/categories/{$emptyCatId}");
    assert_test('DELETE /categories/:id 200 (empty)', $r['status'] === 200, dump_on_fail($r));
}

// ─── Products ────────────────────────────────────────────────────────────────

section('Products CRUD');
if ($setupProductId) {
    $r = request('PATCH', "{$base}/products/{$setupProductId}", ['description' => 'Patched desc']);
    assert_test('PATCH /products/:id 200',   $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/products/{$setupProductId}", [
        'name' => 'Setup Product Updated', 'sku' => $setupSku, 'price' => 249.00, 'stock_quantity' => 10,
    ]);
    assert_test('PUT /products/:id 200',     $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/products/{$setupProductId}", ['name' => 'x']);
    assert_test('PUT /products/:id 422 missing sku+price', $r['status'] === 422, dump_on_fail($r));

    $r = request('PATCH', "{$base}/products/{$setupProductId}/stock", ['quantity' => 5]);
    assert_test('PATCH /products/:id/stock +5 → 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('stock_quantity = 15',       $r['data']['data']['stock_quantity'] === 15, dump_on_fail($r));

    $r = request('PATCH', "{$base}/products/{$setupProductId}/stock", ['quantity' => -9999]);
    assert_test('PATCH /products/:id/stock insufficient → 422', $r['status'] === 422, dump_on_fail($r));
}

// ─── Enumerations ────────────────────────────────────────────────────────────

section('Enumerations CRUD');
$enumType = 'test_type_' . time();
$r = request('POST', "{$base}/enumerations", [
    'type' => $enumType, 'code' => 'code_a', 'label' => 'Code A',
]);
assert_test('POST /enumerations 201',    $r['status'] === 201, dump_on_fail($r));
$enumId = $r['data']['data']['id'] ?? null;

if ($enumId) {
    $r = request('GET', "{$base}/enumerations/{$enumId}");
    assert_test('GET /enumerations/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('code matches',          $r['data']['data']['code'] === 'code_a');

    $r = request('PATCH', "{$base}/enumerations/{$enumId}", ['label' => 'Code A Patched']);
    assert_test('PATCH /enumerations/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/enumerations/{$enumId}", [
        'type' => $enumType, 'code' => 'code_a', 'label' => 'Code A Updated',
    ]);
    assert_test('PUT /enumerations/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/enumerations/{$enumId}", ['type' => $enumType]);
    assert_test('PUT /enumerations/:id 422 missing code+label', $r['status'] === 422, dump_on_fail($r));

    $r = request('DELETE', "{$base}/enumerations/{$enumId}");
    assert_test('DELETE /enumerations/:id 200', $r['status'] === 200, dump_on_fail($r));
}

// ─── Texts ───────────────────────────────────────────────────────────────────

section('Texts CRUD');
$textKey = 'test_key_' . time();
$r = request('POST', "{$base}/texts", [
    'key' => $textKey, 'title' => 'Test Title', 'content' => 'Test content', 'language' => 'cs',
]);
assert_test('POST /texts 201',           $r['status'] === 201, dump_on_fail($r));
$textId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/texts", ['key' => $textKey, 'title' => 'Dup', 'language' => 'cs']);
assert_test('POST /texts 409 duplicate key+lang', $r['status'] === 409, dump_on_fail($r));

if ($textId) {
    $r = request('GET', "{$base}/texts/{$textId}");
    assert_test('GET /texts/:id 200',    $r['status'] === 200, dump_on_fail($r));
    assert_test('has content field',     isset($r['data']['data']['content']));

    $r = request('GET', "{$base}/texts/by-key/{$textKey}?language=cs");
    assert_test('GET /texts/by-key/:key 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('title matches',         $r['data']['data']['title'] === 'Test Title');

    $r = request('GET', "{$base}/texts/by-key/nonexistent_key_xyz_abc");
    assert_test('GET /texts/by-key 404 unknown key', $r['status'] === 404, dump_on_fail($r));

    $r = request('PATCH', "{$base}/texts/{$textId}", ['title' => 'Patched Title']);
    assert_test('PATCH /texts/:id 200',  $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/texts/{$textId}", ['key' => $textKey, 'title' => 'Replaced Title']);
    assert_test('PUT /texts/:id 200',    $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/texts/{$textId}", ['key' => '', 'title' => 'x']);
    assert_test('PUT /texts/:id 422 empty key', $r['status'] === 422, dump_on_fail($r));

    $r = request('DELETE', "{$base}/texts/{$textId}");
    assert_test('DELETE /texts/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/texts/{$textId}");
    assert_test('GET /texts/:id 404 after delete', $r['status'] === 404, dump_on_fail($r));
}

// ═════════════════════════════════════════════════════════════════════════════
// ORDERS
// ═════════════════════════════════════════════════════════════════════════════

section('Orders – test user creates order');
$r = request('POST', "{$base}/auth/login", ['email' => $testEmail, 'password' => $newPassword], false);
assert_test('test user login with new password', $r['status'] === 200, dump_on_fail($r));
$token = $r['data']['data']['token'] ?? null;

$r = request('POST', "{$base}/orders", [
    'items' => [['product_id' => $setupProductId, 'quantity' => 1]],
    'currency' => 'CZK', 'payment_method' => 'card',
]);
assert_test('POST /orders 201',          $r['status'] === 201, dump_on_fail($r));
$orderId = $r['data']['data']['id'] ?? null;

$r = request('POST', "{$base}/orders", ['items' => []]);
assert_test('POST /orders 422 empty items', $r['status'] === 422, dump_on_fail($r));

$r = request('POST', "{$base}/orders", [
    'items' => [['product_id' => $setupProductId, 'quantity' => 99999]],
]);
assert_test('POST /orders 422 insufficient stock', $r['status'] === 422, dump_on_fail($r));

$r = request('GET', "{$base}/orders");
assert_test('GET /orders 200',           $r['status'] === 200, dump_on_fail($r));
assert_test('has items array',           isset($r['data']['data']['items']));

if ($orderId) {
    $r = request('GET', "{$base}/orders/{$orderId}");
    assert_test('GET /orders/:id 200',   $r['status'] === 200, dump_on_fail($r));
    assert_test('has items array',       isset($r['data']['data']['items']));
    assert_test('items not empty',       count($r['data']['data']['items']) > 0);
}

section('Orders – admin manages status');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

if ($orderId) {
    $r = request('PATCH', "{$base}/orders/{$orderId}/status", ['status' => 'confirmed']);
    assert_test('PATCH /orders/:id/status → confirmed 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PATCH', "{$base}/orders/{$orderId}/status", []);
    assert_test('PATCH /orders/:id/status 422 missing status', $r['status'] === 422, dump_on_fail($r));
}

// ═════════════════════════════════════════════════════════════════════════════
// INVOICES
// ═════════════════════════════════════════════════════════════════════════════

section('Invoices CRUD');
$r = request('GET', "{$base}/invoices");
assert_test('GET /invoices 200',         $r['status'] === 200, dump_on_fail($r));
assert_test('has items array',           isset($r['data']['data']['items']));

$invoiceId = null;
if ($orderId) {
    $r = request('POST', "{$base}/invoices", ['order_id' => $orderId]);
    assert_test('POST /invoices 201',    $r['status'] === 201, dump_on_fail($r));
    $invoiceId = $r['data']['data']['id'] ?? null;

    $r = request('POST', "{$base}/invoices", ['order_id' => $orderId]);
    assert_test('POST /invoices 409 duplicate', $r['status'] === 409, dump_on_fail($r));
}

if ($invoiceId) {
    $r = request('GET', "{$base}/invoices/{$invoiceId}");
    assert_test('GET /invoices/:id 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has items array',       isset($r['data']['data']['items']));
    assert_test('invoice_number set',    !empty($r['data']['data']['invoice_number']));

    $r = request('PATCH', "{$base}/invoices/{$invoiceId}/status", ['status' => 'paid']);
    assert_test('PATCH /invoices/:id/status paid 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('DELETE', "{$base}/invoices/{$invoiceId}");
    assert_test('DELETE /invoices/:id 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('GET', "{$base}/invoices/{$invoiceId}");
    assert_test('GET /invoices/:id 404 after delete', $r['status'] === 404, dump_on_fail($r));
}

// ═════════════════════════════════════════════════════════════════════════════
// CLEANUP
// ═════════════════════════════════════════════════════════════════════════════

section('Cleanup');
if ($orderId) {
    $r = request('DELETE', "{$base}/orders/{$orderId}");
    assert_test('DELETE /orders/:id 200',    $r['status'] === 200, dump_on_fail($r));
}

if ($setupProductId) {
    $r = request('DELETE', "{$base}/products/{$setupProductId}");
    assert_test('DELETE /products/:id 200',  $r['status'] === 200, dump_on_fail($r));
}

if ($setupCatId) {
    $r = request('DELETE', "{$base}/categories/{$setupCatId}");
    assert_test('DELETE /categories/:id 200 (after soft-delete product)', $r['status'] === 200, dump_on_fail($r));
}

// ─────────────────────────────────────────────────────────────────────────────

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
