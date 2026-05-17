#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unit/integration tests for App\Modules\Category\Category
 *
 * Tests: getAll(), getById(), create(), update(), delete()
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
$token = null;

// ── Category model – getAll() (public) ───────────────────────────────────────

section('Category model – getAll()');
$r = request('GET', "{$base}/categories", [], false);
assert_test('returns 200', $r['status'] === 200, dump_on_fail($r));
assert_test('data is array', is_array($r['data']['data']));

$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

// ── Category model – create() ────────────────────────────────────────────────

section('Category model – create()');
$r       = request('POST', "{$base}/categories", ['name' => TEST_PREFIX . 'model_category_' . time()]);
assert_test('create category 201', $r['status'] === 201, dump_on_fail($r));
$catId = $r['data']['data']['id'] ?? null;

// ── Category model – getById() (public) ──────────────────────────────────────

section('Category model – getById()');
if ($catId) {
    $r = request('GET', "{$base}/categories/{$catId}", [], false);
    assert_test('getById 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('has products array', isset($r['data']['data']['products']));
    assert_test('name matches', str_starts_with($r['data']['data']['name'], TEST_PREFIX . 'model_category_'), dump_on_fail($r));

    $r = request('GET', "{$base}/categories/999999", [], false);
    assert_test('unknown id → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── Category model – update() ────────────────────────────────────────────────

section('Category model – update()');
if ($catId) {
    $r = request('PATCH', "{$base}/categories/{$catId}", ['description' => 'Model desc']);
    assert_test('PATCH category 200', $r['status'] === 200, dump_on_fail($r));

    $r = request('PUT', "{$base}/categories/{$catId}", ['name' => 'Model Category Updated']);
    assert_test('PUT category 200', $r['status'] === 200, dump_on_fail($r));
}

// ── Category model – delete() ────────────────────────────────────────────────

section('Category model – delete()');
if ($catId) {
    // Verify 'deleted' field is 0 before deletion.
    $r = request('GET', "{$base}/categories/{$catId}", [], false);
    assert_test('deleted field is 0 before delete', ($r['data']['data']['deleted'] ?? -1) === 0, dump_on_fail($r));

    $r = request('DELETE', "{$base}/categories/{$catId}");
    assert_test('delete category 200', $r['status'] === 200, dump_on_fail($r));

    // Soft delete: GET by ID returns 404.
    $r = request('GET', "{$base}/categories/{$catId}", [], false);
    assert_test('deleted category → 404', $r['status'] === 404, dump_on_fail($r));

    // Soft delete: visible with deleted=1 filter.
    $r = request('GET', "{$base}/categories?q=" . urlencode(json_encode(['deleted' => 1])), [], false);
    assert_test('deleted categories visible with deleted:1', ($r['data']['meta']['total'] ?? 0) >= 1, dump_on_fail($r));
}

$token = null;

// ── Junction helpers – findByJunctionItem & findByJunctionList ────────────────

section('Category junction – setup: create categories + product');
$r     = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
$token = $r['data']['data']['token'] ?? null;

$r    = request('POST', "{$base}/categories", ['name' => TEST_PREFIX . 'junc_cat_a_' . time(), 'syscode' => TEST_PREFIX . 'junc_a', 'position' => 10]);
assert_test('create category A 201', $r['status'] === 201, dump_on_fail($r));
$juncCatAId = $r['data']['data']['id'] ?? null;

$r    = request('POST', "{$base}/categories", ['name' => TEST_PREFIX . 'junc_cat_b_' . time(), 'syscode' => TEST_PREFIX . 'junc_b', 'position' => 20]);
assert_test('create category B 201', $r['status'] === 201, dump_on_fail($r));
$juncCatBId = $r['data']['data']['id'] ?? null;

$r    = request('POST', "{$base}/categories", ['name' => TEST_PREFIX . 'junc_cat_c_' . time(), 'syscode' => TEST_PREFIX . 'junc_c', 'position' => 30]);
assert_test('create category C 201', $r['status'] === 201, dump_on_fail($r));
$juncCatCId = $r['data']['data']['id'] ?? null;

// Produkt prirazeny ke kategoriim A a B
$juncSku = TEST_PREFIX . 'junc_prod_' . time();
$r = request('POST', "{$base}/products", [
    'name' => TEST_PREFIX . 'junc_product', 'sku' => $juncSku, 'price' => 1.0,
    'category_ids' => array_filter([$juncCatAId, $juncCatBId]),
]);
assert_test('create product with 2 categories 201', $r['status'] === 201, dump_on_fail($r));
$juncProdId = $r['data']['data']['id'] ?? null;

// Druhy produkt prirazeny ke kategorii C (pro batch test)
$juncSku2 = TEST_PREFIX . 'junc_prod2_' . time();
$r2 = request('POST', "{$base}/products", [
    'name' => TEST_PREFIX . 'junc_product2', 'sku' => $juncSku2, 'price' => 2.0,
    'category_ids' => array_filter([$juncCatCId]),
]);
assert_test('create product2 with 1 category 201', $r2['status'] === 201, dump_on_fail($r2));
$juncProdId2 = $r2['data']['data']['id'] ?? null;

// ── findByJunctionItem – nacte plne objekty kategorii pro jeden produkt ───────

section('Category junction – findByJunctionItem via GET /products/:id?projection=categories');
if ($juncProdId) {
    $r = request('GET', "{$base}/products/{$juncProdId}?projection=id,name,categories", [], false);
    assert_test('GET product with categories projection 200', $r['status'] === 200, dump_on_fail($r));

    $cats = $r['data']['data']['categories'] ?? null;
    assert_test('categories field is array', is_array($cats), dump_on_fail($r));
    assert_test('categories count = 2', count($cats ?? []) === 2, dump_on_fail($r));

    $catIds = array_column($cats ?? [], 'id');
    assert_test('category A id present', in_array($juncCatAId, $catIds, true), dump_on_fail($r));
    assert_test('category B id present', in_array($juncCatBId, $catIds, true), dump_on_fail($r));

    $catSyscodes = array_column($cats ?? [], 'syscode');
    assert_test('category A syscode present', in_array(TEST_PREFIX . 'junc_a', $catSyscodes, true), dump_on_fail($r));
    assert_test('category B syscode present', in_array(TEST_PREFIX . 'junc_b', $catSyscodes, true), dump_on_fail($r));

    // Kazdy objekt ma ocekavane klice
    $first = $cats[0] ?? [];
    assert_test('category object has id', isset($first['id']), dump_on_fail($r));
    assert_test('category object has syscode', isset($first['syscode']), dump_on_fail($r));
    assert_test('category object has name', isset($first['name']), dump_on_fail($r));
    assert_test('category object has position', isset($first['position']), dump_on_fail($r));
    assert_test('category object has parent_id key', array_key_exists('parent_id', $first), dump_on_fail($r));
}

section('Category junction – findByJunctionItem: product with no categories');
$noSku = TEST_PREFIX . 'junc_nocat_' . time();
$r = request('POST', "{$base}/products", ['name' => TEST_PREFIX . 'nocat_prod', 'sku' => $noSku, 'price' => 1.0]);
assert_test('create product without categories 201', $r['status'] === 201, dump_on_fail($r));
$noCatProdId = $r['data']['data']['id'] ?? null;

if ($noCatProdId) {
    $r = request('GET', "{$base}/products/{$noCatProdId}?projection=id,categories", [], false);
    assert_test('GET product without categories 200', $r['status'] === 200, dump_on_fail($r));
    $cats = $r['data']['data']['categories'] ?? 'missing';
    assert_test('categories is empty array', $cats === [], dump_on_fail($r));

    $catIds = $r['data']['data']['category_ids'] ?? 'missing';
    assert_test('category_ids is empty array', $catIds === [], dump_on_fail($r));

    request('DELETE', "{$base}/products/{$noCatProdId}?force=true");
}

// ── findByJunctionList – batch load pro vice produktu najednou (pres findAll) ─

section('Category junction – findByJunctionList via GET /products?projection=categories');
$r = request('GET', "{$base}/products?projection=id,name,categories", [], false);
assert_test('GET products list with categories projection 200', $r['status'] === 200, dump_on_fail($r));

$items = $r['data']['data'] ?? [];
assert_test('items is array', is_array($items), dump_on_fail($r));

// Najdi nase testovaci produkty ve vysledku
$foundProd1 = null;
$foundProd2 = null;
foreach ($items as $item) {
    if ($item['id'] === $juncProdId)  $foundProd1 = $item;
    if ($item['id'] === $juncProdId2) $foundProd2 = $item;
}

if ($juncProdId && $foundProd1) {
    assert_test('batch: product1 has categories array', is_array($foundProd1['categories'] ?? null), json_encode($foundProd1));
    assert_test('batch: product1 categories count = 2', count($foundProd1['categories'] ?? []) === 2, json_encode($foundProd1));
    $ids1 = array_column($foundProd1['categories'], 'id');
    assert_test('batch: product1 has cat A', in_array($juncCatAId, $ids1, true), json_encode($foundProd1));
    assert_test('batch: product1 has cat B', in_array($juncCatBId, $ids1, true), json_encode($foundProd1));
    $catIds1 = $foundProd1['category_ids'] ?? [];
    sort($catIds1); sort($ids1);
    assert_test('batch: product1 category_ids matches categories', $catIds1 === $ids1, json_encode($foundProd1));
}

if ($juncProdId2 && $foundProd2) {
    assert_test('batch: product2 has categories array', is_array($foundProd2['categories'] ?? null), json_encode($foundProd2));
    assert_test('batch: product2 categories count = 1', count($foundProd2['categories'] ?? []) === 1, json_encode($foundProd2));
    $ids2 = array_column($foundProd2['categories'], 'id');
    assert_test('batch: product2 has cat C', in_array($juncCatCId, $ids2, true), json_encode($foundProd2));
}

section('Category junction – findByJunctionList: sorted by position');
if ($juncProdId && $foundProd1) {
    $positions = array_column($foundProd1['categories'] ?? [], 'position');
    $sorted    = $positions;
    sort($sorted);
    assert_test('batch: categories sorted by position ASC', $positions === $sorted, json_encode($positions));
}

// ── Cleanup junction testu ────────────────────────────────────────────────────

section('Category junction – cleanup');
if ($juncProdId)  request('DELETE', "{$base}/products/{$juncProdId}?force=true");
if ($juncProdId2) request('DELETE', "{$base}/products/{$juncProdId2}?force=true");
if ($juncCatAId)  request('DELETE', "{$base}/categories/{$juncCatAId}?force=true");
if ($juncCatBId)  request('DELETE', "{$base}/categories/{$juncCatBId}?force=true");
if ($juncCatCId)  request('DELETE', "{$base}/categories/{$juncCatCId}?force=true");
assert_test('junction cleanup done', true);

$token = null;

// ─────────────────────────────────────────────────────────────────────────────

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
