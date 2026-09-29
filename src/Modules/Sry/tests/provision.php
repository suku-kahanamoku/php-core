<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}

$dsn = getenv("SRY_TEST_DSN");
if (!$dsn || !str_contains($dsn, "dbname=sry_test")) {
    throw new RuntimeException("Explicit disposable sry_test DSN required");
}
$pdo = new PDO(str_replace(";dbname=sry_test", "", $dsn), "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec(
    "CREATE DATABASE sry_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
);
$pdo->exec("USE sry_test");
foreach (['schema', 'sry_schema', 'sry_seed'] as $file) {
    $sql = file_get_contents(__DIR__ . '/../../../../migrations/' . $file . '.sql');
    $pdo->exec($sql);
    $pdo->exec($sql);
}
echo "PASS consolidated schemas and seed applied twice\n";
