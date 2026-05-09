#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit tests for src/Utils/Projection.php
 *
 * Run standalone: php tests/test_projection.php
 * Or included by the test runner.
 */

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}

if (!class_exists('App\Utils\Projection')) {
    require_once __DIR__ . '/../src/Utils/Projection.php';
}

use App\Utils\Projection;

$SYS = ['id', 'created_at', 'updated_at'];
$OWN = ['name', 'price', 'stock_quantity', 'kind', 'color', 'data'];
$REL = ['categories'];

/* ═══════════════════════════════════════════════════════════
   isAll / isEmpty
═══════════════════════════════════════════════════════════ */

section('Projection – isAll / isEmpty');

$p = new Projection(null);
assert_test('null → isAll=true',  $p->isAll());
assert_test('null → isEmpty=false', !$p->isEmpty());

$p = new Projection([]);
assert_test('[] → isAll=false',  !$p->isAll());
assert_test('[] → isEmpty=true',  $p->isEmpty());

$p = new Projection(['name']);
assert_test('[name] → isAll=false',  !$p->isAll());
assert_test('[name] → isEmpty=false', !$p->isEmpty());

/* ═══════════════════════════════════════════════════════════
   getOwnCols – plain columns
═══════════════════════════════════════════════════════════ */

section('Projection – getOwnCols plain');

$p = new Projection(null);
assert_test('null → all OWN cols', $p->getOwnCols($OWN, $REL) === $OWN);

$p = new Projection([]);
assert_test('[] → no cols', $p->getOwnCols($OWN, $REL) === []);

$p = new Projection(['name', 'price']);
$cols = $p->getOwnCols($OWN, $REL);
assert_test('[name,price] → has name', in_array('name', $cols));
assert_test('[name,price] → has price', in_array('price', $cols));
assert_test('[name,price] → no data', !in_array('data', $cols));

/* ═══════════════════════════════════════════════════════════
   getOwnCols – relation FK
═══════════════════════════════════════════════════════════ */

section('Projection – getOwnCols relation FK');

$OWN_WITH_FK = ['name', 'user_id', 'role_id'];
$RELS = ['user', 'role'];

$p = new Projection(['user']);
$cols = $p->getOwnCols($OWN_WITH_FK, $RELS);
assert_test('relation name → FK included', in_array('user_id', $cols));
assert_test('relation name → col itself not included', !in_array('user', $cols));

$p = new Projection(['user.email']);
$cols = $p->getOwnCols($OWN_WITH_FK, $RELS);
assert_test('dot-rel → FK included', in_array('user_id', $cols));

/* ═══════════════════════════════════════════════════════════
   getOwnCols – JSON column dot-notation
═══════════════════════════════════════════════════════════ */

section('Projection – getOwnCols JSON column');

$p = new Projection(['name', 'data.quality', 'data.volume']);
$cols = $p->getOwnCols($OWN, $REL);
assert_test('data.quality → name included', in_array('name', $cols));
assert_test('data.quality → data column included', in_array('data', $cols));
assert_test('data.quality → no data_id', !in_array('data_id', $cols));

$p = new Projection(['data.year']);
$cols = $p->getOwnCols($OWN, $REL);
assert_test('only data.year → data column included', in_array('data', $cols));

/* ═══════════════════════════════════════════════════════════
   needsJoin
═══════════════════════════════════════════════════════════ */

section('Projection – needsJoin');

$p = new Projection(null);
assert_test('null → needsJoin always true', $p->needsJoin('categories'));
assert_test('null → needsJoin data true', $p->needsJoin('data'));

$p = new Projection([]);
assert_test('[] → needsJoin false', !$p->needsJoin('categories'));

$p = new Projection(['categories']);
assert_test('[categories] → needsJoin=true', $p->needsJoin('categories'));

$p = new Projection(['data.quality']);
assert_test('data.quality → needsJoin(data) without relNames = true', $p->needsJoin('data'));
assert_test('data.quality → needsJoin(data, REL) = false', !$p->needsJoin('data', $REL));
assert_test('data.quality → needsJoin(categories, REL) = false', !$p->needsJoin('categories', $REL));

$p = new Projection(['categories.name']);
assert_test('categories.name → needsJoin(categories, REL) = true', $p->needsJoin('categories', $REL));

/* ═══════════════════════════════════════════════════════════
   getDotSubfields
═══════════════════════════════════════════════════════════ */

section('Projection – getDotSubfields');

$p = new Projection(null);
assert_test('null → getDotSubfields=null', $p->getDotSubfields('data') === null);

$p = new Projection(['name']);
assert_test('no dot-notation → null', $p->getDotSubfields('data') === null);

$p = new Projection(['data.quality', 'data.volume']);
$sub = $p->getDotSubfields('data');
assert_test('two subfields → array with both', $sub === ['quality', 'volume']);

$p = new Projection(['data']);
assert_test('col without dot → null (not dot-notation)', $p->getDotSubfields('data') === null);

/* ═══════════════════════════════════════════════════════════
   apply – plain column filtering
═══════════════════════════════════════════════════════════ */

section('Projection – apply plain');

$row = ['id' => 1, 'created_at' => '2026', 'updated_at' => null, 'name' => 'Test', 'price' => '99', 'stock_quantity' => 5];

$p = new Projection(null);
$r = $p->apply($row, $SYS);
assert_test('null → all cols kept', $r === $row);

$p = new Projection([]);
$r = $p->apply($row, $SYS);
assert_test('[] → only SYS cols', array_keys($r) === ['id', 'created_at', 'updated_at']);

$p = new Projection(['name', 'price']);
$r = $p->apply($row, $SYS);
assert_test('[name,price] → id kept (SYS)', isset($r['id']));
assert_test('[name,price] → name kept', isset($r['name']));
assert_test('[name,price] → price kept', isset($r['price']));
assert_test('[name,price] → stock_quantity excluded', !isset($r['stock_quantity']));

/* ═══════════════════════════════════════════════════════════
   apply – JSON column sub-key filtering
═══════════════════════════════════════════════════════════ */

section('Projection – apply JSON column dot-notation');

$row = [
    'id'         => 1,
    'created_at' => '2026',
    'updated_at' => null,
    'name'       => 'Cabernet',
    'kind'       => 'red',
    'data'       => [
        'quality'      => 'premium',
        'volume'       => 0.75,
        'year'         => 2020,
        'winery'       => 'Chateau',
        'grape'        => 'Merlot',
        'alcohol'      => 13.5,
    ],
];

$p = new Projection(['name', 'kind', 'data.quality', 'data.volume']);
$r = $p->apply($row, $SYS);

assert_test('name included', isset($r['name']));
assert_test('kind included', isset($r['kind']));
assert_test('data included', isset($r['data']));
assert_test('data.quality included', ($r['data']['quality'] ?? null) === 'premium');
assert_test('data.volume included', ($r['data']['volume'] ?? null) === 0.75);
assert_test('data.year excluded',   !isset($r['data']['year']));
assert_test('data.winery excluded', !isset($r['data']['winery']));
assert_test('data.grape excluded',  !isset($r['data']['grape']));

// Only data sub-fields, no plain cols
$p = new Projection(['data.quality', 'data.year']);
$r = $p->apply($row, $SYS);
assert_test('only data subs → data included', isset($r['data']));
assert_test('only data subs → name excluded', !isset($r['name']));
assert_test('data.quality included (only sub request)', ($r['data']['quality'] ?? null) === 'premium');
assert_test('data.year included', ($r['data']['year'] ?? null) === 2020);
assert_test('data.volume excluded', !isset($r['data']['volume']));

// data col directly (no dot) → full data
$p = new Projection(['name', 'data']);
$r = $p->apply($row, $SYS);
assert_test('data without dot → full data object', isset($r['data']['quality']) && isset($r['data']['winery']));

// null data value
$rowNull = array_merge($row, ['data' => null]);
$p = new Projection(['data.quality']);
$r = $p->apply($rowNull, $SYS);
assert_test('null data → data=null, no crash', array_key_exists('data', $r) && $r['data'] === null);

/* ═══════════════════════════════════════════════════════════
   apply – relations still work after JSON fix
═══════════════════════════════════════════════════════════ */

section('Projection – apply relations unaffected');

$rowRel = [
    'id'         => 1,
    'created_at' => '2026',
    'updated_at' => null,
    'user_id'    => 5,
    'first_name' => 'Jan',
    'last_name'  => 'Novak',
    'email'      => 'jan@x.cz',
    'name'       => 'Order 1',
];

$relMap = ['user' => [
    'fk'   => 'user_id',
    'nest' => ['first_name', 'last_name', 'email'],
]];

$p = new Projection(['name', 'user.first_name']);
$r = $p->apply($rowRel, $SYS, $relMap);
assert_test('user.first_name → user nested', isset($r['user']['first_name']));
assert_test('user.last_name excluded from nest', !isset($r['user']['last_name']));
assert_test('user_id kept at root', isset($r['user_id']));
assert_test('name kept', isset($r['name']));

if (!isset($runnerMode)) {
    echo "\n──────────────────────────────\n";
    echo "Výsledky:  {$passed} passed  {$failed} failed  /  " . ($passed + $failed) . " total\n";
    exit($failed > 0 ? 1 : 0);
}
