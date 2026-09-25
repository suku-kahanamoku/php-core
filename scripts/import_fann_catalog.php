#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\Database\Database;
use App\Modules\FannCatalog\FannCatalogHttpClient;
use App\Modules\FannCatalog\FannCatalogImporter;
use App\Modules\FannCatalog\FannCatalogParser;
use App\Modules\FannCatalog\FannCatalogRepository;
use Dotenv\Dotenv;

require_once dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$options = getopt('', ['limit::', 'concurrency::']);
$limit = isset($options['limit']) ? (int) $options['limit'] : 50;
$concurrency = isset($options['concurrency']) ? (int) $options['concurrency'] : 4;

try {
    $importer = new FannCatalogImporter(
        new FannCatalogHttpClient(),
        new FannCatalogParser(),
        new FannCatalogRepository(Database::getInstance(), 'fann'),
    );
    $summary = $importer->import(
        $limit,
        $concurrency,
        static fn(string $message): int => fwrite(STDOUT, '[' . date('H:i:s') . "] {$message}\n"),
    );
    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Import selhal: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
