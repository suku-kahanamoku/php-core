#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Comprehensive tests for SQL_FILTER (src/Utils/filter.functions.php).
 *
 * Covers:
 *   - Unit tests: SQL fragment + param correctness for every operator
 *   - Edge cases: nulls, empty values, type coercion, special chars in values
 *   - Security: SQL injection attempts via column names and values
 *   - Multi-column / partial-validity behaviour
 *   - Prefix aliasing
 *   - Integration: actual HTTP requests using ?filter= param on all 9 endpoints
 */

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}
if (!isset($base)) {
    $base = rtrim($argv[1] ?? 'http://localhost/php/php-core/api', '/');
}

if (!function_exists('SQL_FILTER')) {
    require_once __DIR__ . '/../src/Utils/filter.functions.php';
}

/* ═══════════════════════════════════════════════════════════
   HELPERS
═══════════════════════════════════════════════════════════ */

function f(string $json, string $prefix = ''): array
{
    return SQL_FILTER($json, $prefix);
}

/* ═══════════════════════════════════════════════════════════
   UNIT – Empty / degenerate inputs
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – empty / invalid input');
assert_test('empty string', f('')['sql'] === '' && f('')['params'] === []);
assert_test('whitespace only', f('   ')['sql'] === '');
assert_test('invalid json', f('{bad}')['sql'] === '');
assert_test('empty json object', f('{}')['sql'] === '');
assert_test('json array (not obj)', f('[{"name":"jan"}]')['sql'] === '');
assert_test('json scalar string', f('"hello"')['sql'] === '');
assert_test('json scalar int', f('42')['sql'] === '');
assert_test('json null', f('null')['sql'] === '');
assert_test('incomplete json', f('{"name":')['sql'] === '');

/* ═══════════════════════════════════════════════════════════
   UNIT – eq operator (default)
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – eq (default & explicit)');
$r = f('{"name":{"value":"jan"}}');
assert_test('eq default: sql', $r['sql'] === 'name = ?');
assert_test('eq default: param', $r['params'] === ['jan']);

$r = f('{"name":{"value":"jan","operator":"eq"}}');
assert_test('eq explicit: sql', $r['sql'] === 'name = ?');

$r = f('{"count":{"value":0}}');
assert_test('eq zero int: param', $r['params'] === [0]);

$r = f('{"flag":{"value":false}}');
assert_test('eq false bool: param', $r['params'] === [false]);

$r = f('{"name":{"value":null}}');
assert_test('eq null value → empty', $r['sql'] === '');

$r = f('{"price":{"value":9.99}}');
assert_test('eq float: param', $r['params'] === [9.99]);

/* ═══════════════════════════════════════════════════════════
   UNIT – neq operator
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – neq');
$r = f('{"status":{"value":"draft","operator":"neq"}}');
assert_test('neq: sql', $r['sql'] === 'status != ?');
assert_test('neq: param', $r['params'] === ['draft']);

$r = f('{"id":{"value":0,"operator":"neq"}}');
assert_test('neq zero int: sql', $r['sql'] === 'id != ?');

$r = f('{"status":{"value":null,"operator":"neq"}}');
assert_test('neq null value → empty', $r['sql'] === '');

/* ═══════════════════════════════════════════════════════════
   UNIT – lt / lte / gt / gte
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – lt');
$r = f('{"price":{"value":100,"operator":"lt"}}');
assert_test('lt: sql', $r['sql'] === 'price < ?');
assert_test('lt: param', $r['params'] === [100]);

$r = f('{"price":{"value":null,"operator":"lt"}}');
assert_test('lt null value → empty', $r['sql'] === '');

section('SQL_FILTER unit – lte');
$r = f('{"price":{"value":50.5,"operator":"lte"}}');
assert_test('lte: sql', $r['sql'] === 'price <= ?');
assert_test('lte: param', $r['params'] === [50.5]);

section('SQL_FILTER unit – gt');
$r = f('{"stock":{"value":0,"operator":"gt"}}');
assert_test('gt zero: sql', $r['sql'] === 'stock > ?');
assert_test('gt zero: param', $r['params'] === [0]);

section('SQL_FILTER unit – gte');
$r = f('{"price":{"value":9.99,"operator":"gte"}}');
assert_test('gte float: sql', $r['sql'] === 'price >= ?');
assert_test('gte float: param', $r['params'] === [9.99]);

$r = f('{"created_at":{"value":"2024-01-01","operator":"gte"}}');
assert_test('gte date string: sql', $r['sql'] === 'created_at >= ?');
assert_test('gte date string: param', $r['params'] === ['2024-01-01']);

$r = f('{"created_at":{"value":"2024-01-01 00:00:00","operator":"gte"}}');
assert_test('gte datetime: param', $r['params'] === ['2024-01-01 00:00:00']);

/* ═══════════════════════════════════════════════════════════
   UNIT – range
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – range');
$r = f('{"price":{"value":[10,100],"operator":"range"}}');
assert_test('range numeric: sql', $r['sql'] === 'price BETWEEN ? AND ?');
assert_test('range numeric: params', $r['params'] === [10, 100]);

$r = f('{"created_at":{"value":["2024-01-01","2024-12-31"],"operator":"range"}}');
assert_test('range date: sql', $r['sql'] === 'created_at BETWEEN ? AND ?');
assert_test('range date: params', $r['params'] === ['2024-01-01', '2024-12-31']);

$r = f('{"price":{"value":[0,0],"operator":"range"}}');
assert_test('range both zero: sql', $r['sql'] === 'price BETWEEN ? AND ?');

// invalid range inputs
$r = f('{"price":{"value":[10],"operator":"range"}}');
assert_test('range 1 element → empty', $r['sql'] === '');

$r = f('{"price":{"value":[10,20,30],"operator":"range"}}');
assert_test('range 3 elements → empty', $r['sql'] === '');

$r = f('{"price":{"value":"10-100","operator":"range"}}');
assert_test('range string value → empty', $r['sql'] === '');

$r = f('{"price":{"value":null,"operator":"range"}}');
assert_test('range null value → empty', $r['sql'] === '');

$r = f('{"price":{"value":[null,100],"operator":"range"}}');
assert_test('range with null element → empty', $r['sql'] === '');

/* ═══════════════════════════════════════════════════════════
   UNIT – regex / start / end (LIKE operators)
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – regex (contains)');
$r = f('{"name":{"value":"test","operator":"regex"}}');
assert_test('regex: sql', $r['sql'] === 'name LIKE ?');
assert_test('regex: param', $r['params'] === ['%test%']);

$r = f('{"name":{"value":"a%b","operator":"regex"}}');
assert_test('regex value with %: param', $r['params'] === ['%a%b%']);

$r = f('{"name":{"value":"","operator":"regex"}}');
assert_test('regex empty string: param', $r['params'] === ['%%']);

$r = f('{"name":{"value":null,"operator":"regex"}}');
assert_test('regex null → empty', $r['sql'] === '');

section('SQL_FILTER unit – start');
$r = f('{"name":{"value":"jan","operator":"start"}}');
assert_test('start: sql', $r['sql'] === 'name LIKE ?');
assert_test('start: param', $r['params'] === ['jan%']);

$r = f('{"name":{"value":null,"operator":"start"}}');
assert_test('start null → empty', $r['sql'] === '');

section('SQL_FILTER unit – end');
$r = f('{"name":{"value":"ovic","operator":"end"}}');
assert_test('end: sql', $r['sql'] === 'name LIKE ?');
assert_test('end: param', $r['params'] === ['%ovic']);

$r = f('{"name":{"value":null,"operator":"end"}}');
assert_test('end null → empty', $r['sql'] === '');

/* ═══════════════════════════════════════════════════════════
   UNIT – in
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – in');
$r = f('{"id":{"value":[1,2,3],"operator":"in"}}');
assert_test('in 3 items: sql', $r['sql'] === 'id IN (?, ?, ?)');
assert_test('in 3 items: params', $r['params'] === [1, 2, 3]);

$r = f('{"id":{"value":[42],"operator":"in"}}');
assert_test('in 1 item: sql', $r['sql'] === 'id IN (?)');
assert_test('in 1 item: params', $r['params'] === [42]);

$r = f('{"status":{"value":["active","pending"],"operator":"in"}}');
assert_test('in strings: sql', $r['sql'] === 'status IN (?, ?)');
assert_test('in strings: params', $r['params'] === ['active', 'pending']);

$r = f('{"id":{"value":[],"operator":"in"}}');
assert_test('in empty array → empty', $r['sql'] === '');

$r = f('{"id":{"value":"1,2,3","operator":"in"}}');
assert_test('in string (not array) → empty', $r['sql'] === '');

$r = f('{"id":{"value":null,"operator":"in"}}');
assert_test('in null → empty', $r['sql'] === '');

/* ═══════════════════════════════════════════════════════════
   UNIT – null / notnull
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – null / notnull');
$r = f('{"deleted_at":{"operator":"null"}}');
assert_test('null: sql', $r['sql'] === 'deleted_at IS NULL');
assert_test('null: params', $r['params'] === []);

$r = f('{"deleted_at":{"operator":"notnull"}}');
assert_test('notnull: sql', $r['sql'] === 'deleted_at IS NOT NULL');
assert_test('notnull: params', $r['params'] === []);

// value is ignored for null/notnull
$r = f('{"deleted_at":{"value":"2024-01-01","operator":"null"}}');
assert_test('null ignores value: sql', $r['sql'] === 'deleted_at IS NULL');
assert_test('null ignores value: params', $r['params'] === []);

/* ═══════════════════════════════════════════════════════════
   UNIT – prefix aliasing
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – prefix aliasing');
$r = f('{"created_at":{"value":"2024-01-01","operator":"gte"}}', 'u');
assert_test('prefix u: sql', $r['sql'] === 'u.created_at >= ?');
assert_test('prefix u: param', $r['params'] === ['2024-01-01']);

$r = f('{"name":{"value":"test","operator":"regex"}}', 'p');
assert_test('prefix p regex: sql', $r['sql'] === 'p.name LIKE ?');
assert_test('prefix p regex: param', $r['params'] === ['%test%']);

$r = f('{"id":{"value":[1,2],"operator":"in"}}', 'o');
assert_test('prefix o in: sql', $r['sql'] === 'o.id IN (?, ?)');

$r = f('{"deleted_at":{"operator":"null"}}', 'u');
assert_test('prefix u null: sql', $r['sql'] === 'u.deleted_at IS NULL');

/* ═══════════════════════════════════════════════════════════
   UNIT – multi-column filters
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – multi-column');
$r = f('{"name":{"value":"jan"},"price":{"value":100,"operator":"gte"}}');
assert_test('2 cols: sql', $r['sql'] === 'name = ? AND price >= ?');
assert_test('2 cols: params', $r['params'] === ['jan', 100]);

$r = f('{"status":{"value":"active"},"price":{"value":[10,100],"operator":"range"},"name":{"value":"test","operator":"regex"}}');
assert_test('3 cols: sql', $r['sql'] === 'status = ? AND price BETWEEN ? AND ? AND name LIKE ?');
assert_test('3 cols: params', $r['params'] === ['active', 10, 100, '%test%']);

// one invalid column skipped, valid ones still processed
$r = f('{"name":{"value":"jan"},"col;bad":{"value":"x"},"price":{"value":10,"operator":"gt"}}');
assert_test('invalid col skipped: sql', $r['sql'] === 'name = ? AND price > ?');
assert_test('invalid col skipped: params', $r['params'] === ['jan', 10]);

// all invalid → empty
$r = f('{"col;bad":{"value":"x"},"another bad":{"value":"y"}}');
assert_test('all invalid cols → empty', $r['sql'] === '');

/* ═══════════════════════════════════════════════════════════
   UNIT – operator case insensitivity
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – operator case insensitivity');
$r = f('{"price":{"value":10,"operator":"GTE"}}');
assert_test('GTE uppercase: sql', $r['sql'] === 'price >= ?');

$r = f('{"name":{"value":"test","operator":"REGEX"}}');
assert_test('REGEX uppercase: sql', $r['sql'] === 'name LIKE ?');

$r = f('{"status":{"value":"draft","operator":"NEQ"}}');
assert_test('NEQ uppercase: sql', $r['sql'] === 'status != ?');

/* ═══════════════════════════════════════════════════════════
   UNIT – unknown operator
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – unknown operator');
assert_test('unknown op → empty', f('{"col":{"value":"x","operator":"INJECTED"}}')['sql'] === '');
assert_test('sql-keyword op → empty', f('{"col":{"value":"x","operator":"OR"}}')['sql'] === '');
// Empty string is NOT a valid operator; only the missing-key case defaults to 'eq'
assert_test('empty operator string → empty', f('{"col":{"value":"x","operator":""}}')['sql'] === '');

/* ═══════════════════════════════════════════════════════════
   UNIT – security: column name injection
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – column name security');
assert_test('semicolon in col → empty', f('{"col;DROP TABLE x":{"value":"y"}}')['sql'] === '');
assert_test('space in col → empty', f('{"col name":{"value":"y"}}')['sql'] === '');
assert_test('dot in col → empty', f('{"a.b":{"value":"y"}}')['sql'] === '');
assert_test('empty col → empty', f('{"":{"value":"y"}}')['sql'] === '');
assert_test('dash in col → empty', f('{"col-name":{"value":"y"}}')['sql'] === '');
assert_test('star in col → empty', f('{"col*":{"value":"y"}}')['sql'] === '');
assert_test('slash in col → empty', f('{"col/name":{"value":"y"}}')['sql'] === '');
assert_test('quotes in col → empty', f('{"\"col\"":{"value":"y"}}')['sql'] === '');
assert_test('paren in col → empty', f('{"col()":{"value":"y"}}')['sql'] === '');
assert_test('valid underscored col: sql', f('{"first_name":{"value":"jan"}}')['sql'] === 'first_name = ?');
assert_test('valid alphanum col: sql', f('{"col123":{"value":"x"}}')['sql'] === 'col123 = ?');
assert_test('leading underscore col: sql', f('{"_col":{"value":"x"}}')['sql'] === '_col = ?');
assert_test('leading digit in col → empty', f('{"1col":{"value":"x"}}')['sql'] === '');

/* ═══════════════════════════════════════════════════════════
   UNIT – value passthrough (no escaping in params)
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – value passthrough');
$r = f('{"name":{"value":"O\'Reilly"}}');
assert_test('single quote in value passed raw', $r['params'] === ["O'Reilly"]);

$json = '{"name":{"value":' . json_encode('a"b') . '}}';
$r    = f($json);
assert_test('escaped quote in value passed raw', $r['params'][0] === 'a"b');

$r = f('{"name":{"value":"<script>alert(1)</script>"}}');
assert_test('xss-like value passed raw (PDO handles it)', $r['params'][0] === '<script>alert(1)</script>');

$r = f('{"name":{"value":"% wildcard"}}');
assert_test('percent in eq value not modified', $r['params'] === ['% wildcard']);

$r = f('{"name":{"value":"% wildcard","operator":"regex"}}');
assert_test('percent in regex value wrapped: param', $r['params'] === ['%% wildcard%']);

/* ═══════════════════════════════════════════════════════════
   UNIT – spec is not array
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER unit – non-array spec values');
$r = f('{"name":"jan"}');
assert_test('string spec treated as no-op: empty', $r['sql'] === '');

$r = f('{"name":42}');
assert_test('int spec treated as no-op: empty', $r['sql'] === '');

$r = f('{"name":null}');
assert_test('null spec treated as no-op: empty', $r['sql'] === '');

/* ═══════════════════════════════════════════════════════════
   INTEGRATION – actual HTTP API with ?filter= param
   Auth token obtained once, reused across all requests.
═══════════════════════════════════════════════════════════ */

section('SQL_FILTER integration – auth setup');
$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => '12345678'], false);
$token = $r['data']['data']['token'] ?? null;
assert_test('admin login for filter tests', $token !== null, dump_on_fail($r));

if ($token !== null) {

    // ── Roles ─────────────────────────────────────────────────────────────────
    section('SQL_FILTER integration – GET /roles');
    $r = request('GET', $base . '/roles?limit=100&filter=' . urlencode('{"name":{"value":"admin"}}'));
    assert_test('roles eq filter: 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('roles eq filter: items array', is_array($r['data']['data']['items']));
    assert_test(
        'roles eq filter: only admin',
        count(array_filter($r['data']['data']['items'], fn ($x) => $x['name'] !== 'admin')) === 0
        && count($r['data']['data']['items']) >= 1,
    );

    $r = request('GET', $base . '/roles?limit=100&filter=' . urlencode('{"name":{"value":"admin","operator":"neq"}}'));
    assert_test(
        'roles neq filter: no admin in result',
        count(array_filter($r['data']['data']['items'], fn ($x) => $x['name'] === 'admin')) === 0,
    );

    $r = request('GET', $base . '/roles?limit=100&filter=' . urlencode('{"name":{"value":"adm","operator":"start"}}'));
    assert_test(
        'roles start filter: admin present',
        count(array_filter($r['data']['data']['items'], fn ($x) => $x['name'] === 'admin')) >= 1,
    );

    $r = request('GET', $base . '/roles?limit=100&filter=' . urlencode('{"position":{"value":0,"operator":"gte"}}'));
    assert_test('roles gte filter: 200', $r['status'] === 200);
    assert_test('roles gte filter: has items', count($r['data']['data']['items']) >= 1);

    // ── Users ─────────────────────────────────────────────────────────────────
    section('SQL_FILTER integration – GET /users');
    $r = request('GET', $base . '/users?limit=100&filter=' . urlencode('{"email":{"value":"admin@example.com"}}'));
    assert_test('users eq email filter: 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('users eq email filter: 1 result', count($r['data']['data']['items']) === 1);
    assert_test(
        'users eq email filter: correct email',
        ($r['data']['data']['items'][0]['email'] ?? null) === 'admin@example.com',
    );

    $r = request('GET', $base . '/users?limit=100&filter=' . urlencode('{"email":{"value":"@example.com","operator":"end"}}'));
    assert_test('users end email filter: has results', count($r['data']['data']['items']) >= 1);
    assert_test(
        'users end email filter: all match',
        count(array_filter($r['data']['data']['items'], fn ($u) => !str_ends_with($u['email'], '@example.com'))) === 0,
    );

    $r = request('GET', $base . '/users?limit=100&filter=' . urlencode('{"email":{"value":"nonexistent_xyz_99999@x.com"}}'));
    assert_test('users eq filter: no results for unknown', count($r['data']['data']['items']) === 0);

    // ── Categories ────────────────────────────────────────────────────────────
    section('SQL_FILTER integration – GET /categories');
    // First get a real category name
    $rAll    = request('GET', "{$base}/categories?limit=1", [], false);
    $catName = $rAll['data']['data']['items'][0]['name'] ?? null;
    if ($catName !== null) {
        $r = request('GET', $base . '/categories?limit=100&filter=' . urlencode('{"name":{"value":"' . $catName . '"}}'), [], false);
        assert_test('categories eq filter: 200', $r['status'] === 200, dump_on_fail($r));
        assert_test('categories eq filter: found', count($r['data']['data']['items']) >= 1);
        assert_test(
            'categories eq filter: name match',
            ($r['data']['data']['items'][0]['name'] ?? null) === $catName,
        );
    } else {
        assert_test('categories eq filter: skipped (no data)', true);
        assert_test('categories eq filter: skipped (no data)', true);
        assert_test('categories eq filter: skipped (no data)', true);
    }

    $r = request('GET', $base . '/categories?limit=100&filter=' . urlencode('{"name":{"value":"zz_never_exists_xyz_999"}}'), [], false);
    assert_test('categories eq filter: 0 for unknown', ($r['data']['data']['total'] ?? -1) === 0);

    $r = request('GET', $base . '/categories?limit=100&filter=' . urlencode('{"position":{"value":0,"operator":"gte"}}'), [], false);
    assert_test('categories gte filter: 200', $r['status'] === 200);

    // ── Products ──────────────────────────────────────────────────────────────
    section('SQL_FILTER integration – GET /products');
    $rAll     = request('GET', "{$base}/products?limit=1", [], false);
    $prodName = $rAll['data']['data']['items'][0]['name'] ?? null;
    if ($prodName !== null) {
        $r = request('GET', $base . '/products?limit=100&filter=' . urlencode('{"name":{"value":"' . $prodName . '"}}'), [], false);
        assert_test('products eq filter: 200', $r['status'] === 200, dump_on_fail($r));
        assert_test('products eq filter: found', count($r['data']['data']['items']) >= 1);
    } else {
        assert_test('products eq filter: skipped (no data)', true);
        assert_test('products eq filter: skipped (no data)', true);
    }

    $r = request('GET', $base . '/products?limit=100&filter=' . urlencode('{"price":{"value":0,"operator":"gte"}}'), [], false);
    assert_test('products gte price filter: 200', $r['status'] === 200);

    $r = request('GET', $base . '/products?limit=100&filter=' . urlencode('{"stock_quantity":{"value":0,"operator":"gt"}}'), [], false);
    assert_test('products gt stock filter: 200', $r['status'] === 200);
    assert_test(
        'products gt stock filter: all stock > 0',
        count(array_filter($r['data']['data']['items'] ?? [], fn ($p) => (int)$p['stock_quantity'] <= 0)) === 0,
    );

    // ── Texts ─────────────────────────────────────────────────────────────────
    section('SQL_FILTER integration – GET /texts');
    $r = request('GET', $base . '/texts?limit=100&filter=' . urlencode('{"is_active":{"value":1}}'));
    assert_test('texts eq is_active=1 filter: 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('texts eq is_active=1 filter: has items', count($r['data']['data']['items']) >= 1);
    assert_test(
        'texts eq is_active=1 filter: all active',
        count(array_filter($r['data']['data']['items'], fn ($t) => (int)$t['is_active'] !== 1)) === 0,
    );

    $r = request('GET', $base . '/texts?limit=100&filter=' . urlencode('{"syscode":{"value":"xyz_never_exists_zzz"}}'));
    assert_test('texts eq syscode filter: 0 for unknown', ($r['data']['data']['total'] ?? -1) === 0);

    // ── Enumerations ──────────────────────────────────────────────────────────
    section('SQL_FILTER integration – GET /enumerations');
    $r = request('GET', $base . '/enumerations?limit=100&filter=' . urlencode('{"type":{"value":"order_status"}}'), [], false);
    assert_test('enumerations eq type filter: 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('enumerations eq type filter: has items', count($r['data']['data']['items']) >= 1);
    assert_test(
        'enumerations eq type filter: all order_status',
        count(array_filter($r['data']['data']['items'], fn ($e) => $e['type'] !== 'order_status')) === 0,
    );

    $r = request('GET', $base . '/enumerations?limit=100&filter=' . urlencode('{"is_active":{"value":1}}'), [], false);
    assert_test('enumerations eq is_active filter: 200', $r['status'] === 200);
    assert_test(
        'enumerations eq is_active filter: all active',
        count(array_filter($r['data']['data']['items'], fn ($e) => (int)$e['is_active'] !== 1)) === 0,
    );

    $r = request('GET', $base . '/enumerations?limit=100&filter=' . urlencode('{"label":{"value":"a","operator":"regex"}}'), [], false);
    assert_test('enumerations regex label filter: 200', $r['status'] === 200);
    assert_test(
        'enumerations regex label filter: all contain a',
        count(array_filter($r['data']['data']['items'], fn ($e) => stripos($e['label'], 'a') === false)) === 0,
    );

    // ── Orders ────────────────────────────────────────────────────────────────
    section('SQL_FILTER integration – GET /orders');
    $rAll        = request('GET', "{$base}/orders?limit=1");
    $orderStatus = $rAll['data']['data']['items'][0]['status'] ?? null;
    if ($orderStatus !== null) {
        $r = request('GET', $base . '/orders?limit=100&filter=' . urlencode('{"status":{"value":"' . $orderStatus . '"}}'));
        assert_test('orders eq status filter: 200', $r['status'] === 200, dump_on_fail($r));
        assert_test('orders eq status filter: has items', count($r['data']['data']['items']) >= 1);
        assert_test(
            'orders eq status filter: all match status',
            count(array_filter($r['data']['data']['items'], fn ($o) => $o['status'] !== $orderStatus)) === 0,
        );
    } else {
        assert_test('orders eq filter: skipped (no data)', true);
        assert_test('orders eq filter: skipped (no data)', true);
        assert_test('orders eq filter: skipped (no data)', true);
    }

    $r = request('GET', $base . '/orders?limit=100&filter=' . urlencode('{"total_amount":{"value":0,"operator":"gte"}}'));
    assert_test('orders gte total_amount filter: 200', $r['status'] === 200);

    // ── Invoices ──────────────────────────────────────────────────────────────
    section('SQL_FILTER integration – GET /invoices');
    $rAll      = request('GET', "{$base}/invoices?limit=1");
    $invStatus = $rAll['data']['data']['items'][0]['status'] ?? null;
    if ($invStatus !== null) {
        $r = request('GET', $base . '/invoices?limit=100&filter=' . urlencode('{"status":{"value":"' . $invStatus . '"}}'));
        assert_test('invoices eq status filter: 200', $r['status'] === 200, dump_on_fail($r));
        assert_test('invoices eq status filter: has items', count($r['data']['data']['items']) >= 1);
        assert_test(
            'invoices eq status filter: all match',
            count(array_filter($r['data']['data']['items'], fn ($i) => $i['status'] !== $invStatus)) === 0,
        );
    } else {
        assert_test('invoices eq filter: skipped (no data)', true);
        assert_test('invoices eq filter: skipped (no data)', true);
        assert_test('invoices eq filter: skipped (no data)', true);
    }

    $r = request('GET', $base . '/invoices?limit=100&filter=' . urlencode('{"total_amount":{"value":0,"operator":"gte"}}'));
    assert_test('invoices gte total_amount filter: 200', $r['status'] === 200);

    // ── Multi-column filter integration ───────────────────────────────────────
    section('SQL_FILTER integration – multi-column filter');
    $r = request('GET', $base . '/roles?limit=100&filter=' . urlencode('{"name":{"value":"adm","operator":"start"},"position":{"value":0,"operator":"gte"}}'));
    assert_test('roles multi-col: 200', $r['status'] === 200);
    assert_test(
        'roles multi-col: all names start with adm',
        count(array_filter($r['data']['data']['items'], fn ($x) => !str_starts_with($x['name'], 'adm'))) === 0,
    );

    // ── Invalid / garbage filter param is silently ignored ────────────────────
    section('SQL_FILTER integration – invalid filter param ignored');
    $r = request('GET', $base . '/roles?filter=bad_json', [], false);
    assert_test('garbage filter → 200 (ignored)', $r['status'] === 200);
    assert_test('garbage filter → returns items', is_array($r['data']['data']['items']));

    $r = request('GET', $base . '/roles?filter={}', [], false);
    assert_test('empty filter obj → 200', $r['status'] === 200);
}

// ── Standalone summary ────────────────────────────────────────────────────────
if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
