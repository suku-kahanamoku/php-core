<?php

declare(strict_types=1);
use App\Modules\Database\Database;

function testDatabase(): Database
{
    $dsn = getenv('ETYMOLOG_TEST_DSN');
    if (!$dsn || !preg_match('~^mysql:unix_socket=/tmp/etymolog-test\.[^/]+/mysql\.sock;dbname=etymolog_test;charset=utf8mb4$~D', $dsn)) {
        throw new RuntimeException('Disposable etymolog_test database required');
    }
    $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->exec("SET time_zone='+00:00'");
    // Inject a disposable connection without changing the production Database contract.
    $class = new ReflectionClass(Database::class);
    $db = $class->newInstanceWithoutConstructor();
    $class->getProperty('_pdo')->setValue($db, $pdo);
    $class->getProperty('_instance')->setValue(null, $db);
    return $db;
}
