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

// ── Standalone summary ────────────────────────────────────────────────────────
if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
