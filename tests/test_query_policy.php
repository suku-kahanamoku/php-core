#!/usr/bin/env php
<?php

declare(strict_types=1);

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}

require_once __DIR__ . '/../src/Utils/QueryPolicy.php';

use App\Utils\QueryPolicy;

section('QueryPolicy - public filters');
$filter = QueryPolicy::filter(
    '{"name":{"value":"wine"},"deleted":1,"password":{"operator":"notnull"}}',
    ['name'],
    ['deleted' => 0, 'published' => ['value' => 1]],
);
$decoded = json_decode($filter, true);
assert_test('keeps an allowed filter', ($decoded['name']['value'] ?? null) === 'wine');
assert_test('overrides visibility filters', ($decoded['deleted'] ?? null) === 0 && ($decoded['published']['value'] ?? null) === 1);
assert_test('drops disallowed filters', !array_key_exists('password', $decoded));

section('QueryPolicy - public sort');
assert_test('keeps allowed legacy sort', QueryPolicy::sort('name DESC', ['name']) === 'name DESC');
assert_test('drops disallowed legacy sort', QueryPolicy::sort('password DESC', ['name']) === '');
assert_test(
    'keeps only allowed JSON sorts',
    QueryPolicy::sort('[{"name":1},{"password":-1}]', ['name']) === '[{"name":1}]',
);

section('QueryPolicy - projection and output');
$projection = QueryPolicy::projection(['id', 'name', 'password'], ['id', 'name']);
assert_test('projection allowlist', $projection === ['id', 'name']);
$item = QueryPolicy::fields(['id' => 1, 'name' => 'Wine', 'password' => 'secret'], ['id', 'name']);
assert_test('output allowlist', $item === ['id' => 1, 'name' => 'Wine']);

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
