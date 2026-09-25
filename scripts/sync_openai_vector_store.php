#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\Database\Database;
use App\Modules\OpenAi\OpenAiCatalogRepository;
use App\Modules\OpenAi\OpenAiProductDocumentBuilder;
use App\Modules\OpenAi\OpenAiVectorStoreClient;
use App\Modules\OpenAi\OpenAiVectorStoreRepository;
use App\Modules\OpenAi\OpenAiVectorStoreSyncService;
use Dotenv\Dotenv;

require_once dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$options = getopt('', ['tenant::']);
$tenant = trim((string) ($options['tenant'] ?? 'fann'));
if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $tenant) !== 1) {
    fwrite(STDERR, "Neplatný tenant.\n");
    exit(2);
}

try {
    $database = Database::getInstance();
    $sync = new OpenAiVectorStoreSyncService(
        new OpenAiCatalogRepository($database, $tenant),
        new OpenAiVectorStoreRepository($database, $tenant),
        new OpenAiVectorStoreClient(),
        new OpenAiProductDocumentBuilder(),
        $tenant,
    );
    $summary = $sync->sync(
        static fn(string $message): int => fwrite(STDOUT, '[' . date('H:i:s') . "] {$message}\n"),
    );
    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Vector Store sync selhal: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
