#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Modules\Database\Database;
use App\Modules\Database\Maintenance\TramCleanupRepository;

$options = getopt('', ['apply', 'expect-database:', 'backup-dir:']);
try {
    $cleanup = new TramCleanupRepository(Database::getInstance()->getPdo());
    $plan = $cleanup->plan();
    if (isset($options['apply'])) {
        if (($options['expect-database'] ?? '') !== $plan['database'] || empty($options['backup-dir'])) {
            throw new RuntimeException('Apply requires --expect-database=<current DB> and --backup-dir=<private path outside www>.');
        }
        $plan = $cleanup->apply($options['backup-dir']);
    }
    echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'TRAM cleanup failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
