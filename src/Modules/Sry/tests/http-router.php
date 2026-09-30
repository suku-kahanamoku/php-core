<?php
declare(strict_types=1);

// Built-in PHP server fixture for the disposable sry_test database only.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$dsn = getenv('SRY_TEST_DSN') ?: '';
if (!preg_match('~^mysql:unix_socket=/tmp/sry-db\.[^/;]+/mysql\.sock;dbname=sry_test;charset=utf8mb4$~D', $dsn)) {
    throw new RuntimeException('Only sry_test is permitted');
}
$_ENV['FRANCHISE_CODES'] = 'sry.test:sry,other.test:other,127.0.0.1:sry';
$_ENV['SRY_CLOUDFLARE_URL'] = 'https://media.example';
$_ENV['SRY_CLOUDFLARE_SECRET'] = str_repeat('a', 32);
require_once __DIR__ . '/../../../../bootstrap.php';
require_once __DIR__ . '/database.php';
$pdo = new PDO($dsn, getenv('SRY_TEST_USER') ?: 'root', getenv('SRY_TEST_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$database = new ReflectionClass(\App\Modules\Database\Database::class);
$instance = $database->getProperty('_instance');
$instance->setValue(null, testDatabase($pdo));
$_SERVER['SCRIPT_NAME'] = '/api/sry/index.php';
require __DIR__ . '/../../../../api/sry/index.php';
