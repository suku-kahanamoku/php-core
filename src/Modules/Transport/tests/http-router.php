<?php

declare(strict_types=1);
$dsn = getenv('TRANSPORT_TEST_DSN');
if (!$dsn || !str_contains($dsn, 'dbname=transport_test;')) {
    http_response_code(500);
    exit;
}
if (str_starts_with($_SERVER['REQUEST_URI'], '/fixture/')) {
    if ($_SERVER['REQUEST_URI'] === '/fixture/slow') {
        usleep(500000);
    }
    if ($_SERVER['REQUEST_URI'] === '/fixture/large') {
        echo str_repeat('x', 10000);
        exit;
    }
    if ($_SERVER['REQUEST_URI'] === '/fixture/rate') {
        http_response_code(429);
        header('Retry-After: 120');
        echo '{}';
        exit;
    }
    if ($_SERVER['REQUEST_URI'] === '/fixture/redirect') {
        header('Location: /fixture/json', true, 302);
        exit;
    }
    header('Content-Type: application/json');
    echo '{"ok":true}';
    exit;
}
require dirname(__DIR__, 4).'/vendor/autoload.php';
$_ENV['FRANCHISE_CODES'] = 'tram.test:tram,other.test:other';
$_ENV['INTERNAL_API_KEY'] = 'transport-test-internal-key';
$_ENV['APP_ENV'] = 'production';
// Inject only the isolated test PDO; never connect to the application DB configured in .env.
$ref = new ReflectionClass(App\Modules\Database\Database::class);
$db = $ref->newInstanceWithoutConstructor();
$ref->getProperty('_pdo')->setValue($db, new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES => false]));
$ref->getProperty('_instance')->setValue(null, $db);
$_SERVER['SCRIPT_NAME'] = '/api/transport/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 4).'/api/transport/index.php';
require $_SERVER['SCRIPT_FILENAME'];
