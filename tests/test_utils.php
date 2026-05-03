#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit tests for src/Utils/sort.functions.php (SQL_SORT)
 * and src/Utils/validator.functions.php (VALIDATOR).
 *
 * Run standalone: php tests/test_utils.php
 * Or included by the test runner.
 */

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}

// Load utils (normally loaded via composer files autoload)
if (!function_exists('SQL_SORT')) {
    require_once __DIR__ . '/../src/Utils/sort.functions.php';
}
if (!function_exists('VALIDATOR')) {
    require_once __DIR__ . '/../src/Utils/validator.functions.php';
}

/* ═══════════════════════════════════════════════════════════
   SQL_SORT
═══════════════════════════════════════════════════════════ */

section('SQL_SORT – empty / default');
assert_test('empty string returns default', SQL_SORT('', 'created_at DESC') === 'created_at DESC');
assert_test('whitespace string returns default', SQL_SORT('   ', 'position ASC') === 'position ASC');

section('SQL_SORT – JSON array format');
assert_test('ASC  (1)', SQL_SORT('[{"name":1}]', 'created_at DESC') === 'name ASC');
assert_test('DESC (-1)', SQL_SORT('[{"name":-1}]', 'created_at DESC') === 'name DESC');
assert_test('multi-sort', SQL_SORT('[{"created_at":-1},{"name":1}]', 'id ASC') === 'created_at DESC, name ASC');
assert_test('with prefix', SQL_SORT('[{"created_at":-1}]', 'u.created_at DESC', 'u') === 'u.created_at DESC');
assert_test('with prefix multi', SQL_SORT('[{"price":-1},{"name":1}]', 'p.created_at DESC', 'p') === 'p.price DESC, p.name ASC');

section('SQL_SORT – JSON security');
assert_test('rejects semicolon in col name', SQL_SORT('[{"name;DROP TABLE x":1}]', 'id ASC') === 'id ASC');
assert_test('rejects space in col name', SQL_SORT('[{"col name":1}]', 'id ASC') === 'id ASC');
assert_test('rejects dot in col name', SQL_SORT('[{"a.b":1}]', 'id ASC') === 'id ASC');
assert_test('rejects empty col name', SQL_SORT('[{"":1}]', 'id ASC') === 'id ASC');
assert_test('invalid json returns default', SQL_SORT('[not valid json', 'id ASC') === 'id ASC');
assert_test('empty json array returns default', SQL_SORT('[]', 'id ASC') === 'id ASC');

section('SQL_SORT – legacy format');
assert_test('col only → ASC', SQL_SORT('name', 'id ASC') === 'name ASC');
assert_test('col ASC', SQL_SORT('name ASC', 'id ASC') === 'name ASC');
assert_test('col DESC', SQL_SORT('name DESC', 'id ASC') === 'name DESC');
assert_test('col desc lowercase → DESC', SQL_SORT('name desc', 'id ASC') === 'name DESC');
assert_test('legacy with prefix', SQL_SORT('price DESC', 'id ASC', 'p') === 'p.price DESC');
assert_test('unsafe legacy col → default', SQL_SORT('col;bad DESC', 'id ASC') === 'id ASC');

/* ═══════════════════════════════════════════════════════════
   VALIDATOR
═══════════════════════════════════════════════════════════ */

section('VALIDATOR – required');
$v = VALIDATOR(['name' => 'Jan', 'email' => '']);
assert_test('no error when field present', !$v->fails());

$v = VALIDATOR(['name' => '', 'email' => 'x@x.com']);
$v->required('name');
assert_test('fails when field empty', $v->fails());
assert_test('correct error key', isset($v->errors()['name']));

$v = VALIDATOR(['a' => 'ok', 'b' => '']);
$v->required(['a', 'b']);
assert_test('multi-field: only empty fails', array_keys($v->errors()) === ['b']);

section('VALIDATOR – email');
$v = VALIDATOR(['email' => 'invalid-email']);
$v->email('email');
assert_test('invalid email fails', $v->fails());

$v = VALIDATOR(['email' => 'user@example.com']);
$v->email('email');
assert_test('valid email passes', !$v->fails());

$v = VALIDATOR(['email' => '']);
$v->email('email');
assert_test('empty email skipped', !$v->fails());

section('VALIDATOR – minLength');
$v = VALIDATOR(['pass' => 'ab']);
$v->minLength('pass', 6);
assert_test('too short fails', $v->fails());

$v = VALIDATOR(['pass' => 'abcdef']);
$v->minLength('pass', 6);
assert_test('exact length passes', !$v->fails());

section('VALIDATOR – numeric');
$v = VALIDATOR(['price' => 'abc']);
$v->numeric('price');
assert_test('non-numeric fails', $v->fails());

$v = VALIDATOR(['price' => '-5']);
$v->numeric('price', 0);
assert_test('below min fails', $v->fails());

$v = VALIDATOR(['price' => '9.99']);
$v->numeric('price', 0);
assert_test('valid numeric passes', !$v->fails());

section('VALIDATOR – pattern');
$v = VALIDATOR(['code' => 'ABC123']);
$v->pattern('code', '/^[A-Z]+$/', 'Only uppercase');
assert_test('pattern mismatch fails', $v->fails());

$v = VALIDATOR(['code' => 'ABC']);
$v->pattern('code', '/^[A-Z]+$/', 'Only uppercase');
assert_test('pattern match passes', !$v->fails());

section('VALIDATOR – chaining / error skip');
$v = VALIDATOR(['email' => '']);
$v->required('email')->email('email');
assert_test('required error skips email check', count($v->errors()) === 1 && isset($v->errors()['email']));

section('VALIDATOR – fails / errors');
$v = VALIDATOR(['x' => 'ok']);
assert_test('fails() false when no errors', !$v->fails());
assert_test('errors() empty array', $v->errors() === []);

/* ═══════════════════════════════════════════════════════════
   SQL_FILTER
═══════════════════════════════════════════════════════════ */

// Load filter functions if running standalone
if (!function_exists('SQL_FILTER')) {
    require_once __DIR__ . '/../src/Utils/filter.functions.php';
}

section('SQL_FILTER – empty / invalid');
assert_test('empty string → empty sql', SQL_FILTER('')['sql'] === '');
assert_test('whitespace → empty sql', SQL_FILTER('  ')['sql'] === '');
assert_test('invalid json → empty sql', SQL_FILTER('{bad json')['sql'] === '');
assert_test('empty object → empty sql', SQL_FILTER('{}')['sql'] === '');
assert_test('non-object → empty sql', SQL_FILTER('[1,2]')['sql'] === '');

section('SQL_FILTER – eq (default operator)');
$r = SQL_FILTER('{"name":{"value":"jan"}}');
assert_test('eq sql', $r['sql'] === 'name = ?');
assert_test('eq params', $r['params'] === ['jan']);

$r = SQL_FILTER('{"name":{"value":"jan","operator":"eq"}}');
assert_test('explicit eq sql', $r['sql'] === 'name = ?');

section('SQL_FILTER – neq');
$r = SQL_FILTER('{"status":{"value":"draft","operator":"neq"}}');
assert_test('neq sql', $r['sql'] === 'status != ?');
assert_test('neq params', $r['params'] === ['draft']);

section('SQL_FILTER – lt / lte / gt / gte');
$r = SQL_FILTER('{"price":{"value":100,"operator":"lt"}}');
assert_test('lt sql', $r['sql'] === 'price < ?');
assert_test('lt params', $r['params'] === [100]);

$r = SQL_FILTER('{"price":{"value":100,"operator":"lte"}}');
assert_test('lte sql', $r['sql'] === 'price <= ?');

$r = SQL_FILTER('{"price":{"value":0,"operator":"gt"}}');
assert_test('gt sql', $r['sql'] === 'price > ?');

$r = SQL_FILTER('{"price":{"value":9.99,"operator":"gte"}}');
assert_test('gte sql', $r['sql'] === 'price >= ?');
assert_test('gte params', $r['params'] === [9.99]);

// date strings allowed
$r = SQL_FILTER('{"created_at":{"value":"2024-01-01","operator":"gte"}}');
assert_test('gte date string', $r['sql'] === 'created_at >= ?');

section('SQL_FILTER – range');
$r = SQL_FILTER('{"price":{"value":[10,100],"operator":"range"}}');
assert_test('range sql', $r['sql'] === 'price BETWEEN ? AND ?');
assert_test('range params', $r['params'] === [10, 100]);

$r = SQL_FILTER('{"created_at":{"value":["2024-01-01","2024-12-31"],"operator":"range"}}');
assert_test('range date sql', $r['sql'] === 'created_at BETWEEN ? AND ?');
assert_test('range date params', $r['params'] === ['2024-01-01', '2024-12-31']);

// invalid range
$r = SQL_FILTER('{"price":{"value":[10],"operator":"range"}}');
assert_test('range single element → empty', $r['sql'] === '');

section('SQL_FILTER – regex / start / end');
$r = SQL_FILTER('{"name":{"value":"test","operator":"regex"}}');
assert_test('regex sql', $r['sql'] === 'name LIKE ?');
assert_test('regex params', $r['params'] === ['%test%']);

$r = SQL_FILTER('{"name":{"value":"test","operator":"start"}}');
assert_test('start params', $r['params'] === ['test%']);

$r = SQL_FILTER('{"name":{"value":"test","operator":"end"}}');
assert_test('end params', $r['params'] === ['%test']);

section('SQL_FILTER – in');
$r = SQL_FILTER('{"id":{"value":[1,2,3],"operator":"in"}}');
assert_test('in sql', $r['sql'] === 'id IN (?, ?, ?)');
assert_test('in params', $r['params'] === [1, 2, 3]);

$r = SQL_FILTER('{"id":{"value":[],"operator":"in"}}');
assert_test('in empty array → empty', $r['sql'] === '');

section('SQL_FILTER – null / notnull');
$r = SQL_FILTER('{"deleted_at":{"operator":"null"}}');
assert_test('null sql', $r['sql'] === 'deleted_at IS NULL');
assert_test('null params', $r['params'] === []);

$r = SQL_FILTER('{"deleted_at":{"operator":"notnull"}}');
assert_test('notnull sql', $r['sql'] === 'deleted_at IS NOT NULL');

section('SQL_FILTER – prefix');
$r = SQL_FILTER('{"created_at":{"value":"2024-01-01","operator":"gte"}}', 'u');
assert_test('prefix applied', $r['sql'] === 'u.created_at >= ?');

section('SQL_FILTER – multi-column');
$r = SQL_FILTER('{"name":{"value":"jan"},"price":{"value":100,"operator":"gte"}}');
assert_test('multi sql', $r['sql'] === 'name = ? AND price >= ?');
assert_test('multi params', $r['params'] === ['jan', 100]);

section('SQL_FILTER – security');
assert_test('rejects semicolon in col', SQL_FILTER('{"col;drop":{"value":"x"}}')['sql'] === '');
assert_test('rejects space in col', SQL_FILTER('{"col name":{"value":"x"}}')['sql'] === '');
assert_test('rejects dot in col', SQL_FILTER('{"a.b":{"value":"x"}}')['sql'] === '');
assert_test('rejects empty col', SQL_FILTER('{"":{"value":"x"}}')['sql'] === '');
assert_test('unknown operator → empty', SQL_FILTER('{"col":{"value":"x","operator":"INJECTED"}}')['sql'] === '');

// ── Standalone summary ────────────────────────────────────────────────────────
if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
