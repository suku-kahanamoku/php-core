<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';

use App\Modules\Database\Database;
use App\Modules\Etymolog\{SyncException, EtymologException};

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
set_exception_handler(static function (Throwable $e): void {
    $reason = $e instanceof SyncException ? $e->reason : ($e instanceof EtymologException ? $e->getMessage() : 'etymolog_command_failed');
    fwrite(STDERR, $reason.PHP_EOL);
    exit(1);
});
$options = getopt('', ['tenant:', 'job:', 'request:', 'queued-only']);
$tenant = $options['tenant'] ?? '';
$allowed = [];
foreach (explode(',', $_ENV['FRANCHISE_CODES'] ?? '') as $entry) {
    $parts = explode(':', trim($entry));
    $allowed[] = trim((string)end($parts));
}
if (!is_string($tenant) || $tenant === '' || strlen($tenant) > 64 || !in_array($tenant, $allowed, true)) {
    fwrite(STDERR, "Supply --tenant=<existing FRANCHISE_CODES alias>.\n");
    exit(2);
}
$job = $options['job'] ?? null;
if ($job !== null && (filter_var($job, FILTER_VALIDATE_INT) === false || (int)$job < 1 || (int)$job > 2147483647)) {
    fwrite(STDERR, "Invalid --job.\n");
    exit(2);
}
$queuedOnly = array_key_exists('queued-only', $options);
if ($queuedOnly && ($job !== null || array_key_exists('request', $options))) {
    fwrite(STDERR, "--queued-only cannot be combined with --job or --request.\n"); exit(2);
}
$db = Database::getInstance();
$background = \App\Modules\Etymolog\EtymologModule::background($db, $tenant);
$requestId = $options['request'] ?? null;
if ($requestId !== null && (!is_string($requestId) || !preg_match('/^[a-f0-9]{32}$/D', $requestId) || $job !== null)) {
    fwrite(STDERR, "Invalid --request or incompatible --job.\n"); exit(2);
}
$result = $job !== null ? \App\Modules\Etymolog\EtymologModule::sync($db, $tenant)->run((int)$job) : ($queuedOnly ? $background->workQueued() : $background->work($requestId));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
