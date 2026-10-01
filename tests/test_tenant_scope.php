#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\BaseRepository;

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}
require_once __DIR__ . '/../vendor/autoload.php';

section('Tenant repository scope');
$repository = new class extends BaseRepository {
    public function __construct() {}

    /** @return array{where: string, params: list<string>} */
    public function scope(string $tenant, string $alias = ''): array
    {
        $this->_code = $tenant;
        return [
            'where'  => $this->_tenantWhere($alias),
            'params' => $this->_tenantParams(),
        ];
    }
};

$unaliased = $repository->scope('fann');
assert_test('builds an unaliased tenant predicate', $unaliased['where'] === 'franchise_code = ?');
assert_test('binds only the repository tenant', $unaliased['params'] === ['fann']);

$aliased = $repository->scope('zoo', 'p.');
assert_test('builds an aliased tenant predicate', $aliased['where'] === 'p.franchise_code = ?');
assert_test('normalizes alias without a trailing dot', $repository->scope('zoo', 'p')['where'] === 'p.franchise_code = ?');

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
