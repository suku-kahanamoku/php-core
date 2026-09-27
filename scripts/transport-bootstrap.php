<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
set_exception_handler(static function (Throwable $e): void {
    $code = $e instanceof App\Modules\Transport\TransportException ? $e->reason : 'transport_command_failed';
    fwrite(STDERR, $code.': '.($e instanceof App\Modules\Transport\TransportException ? $e->getMessage() : 'Check configuration and database schema.')."\n");
    exit(1);
});
$options = getopt('', ['tenant:','config:','feed:','file:','version:','output:','manifest:','graph-url:','command:']);
$tenant = $options['tenant'] ?? '';
if (!is_string($tenant) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $tenant)) {
    fwrite(STDERR, "Supply --tenant=<code>.\n");
    exit(2);
}
$db = App\Modules\Database\Database::getInstance()->getPdo();
$db->exec("SET time_zone = '+00:00'");
$repository = new App\Modules\Transport\Repositories\TransportRepository($db, $tenant);
