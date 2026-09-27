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
$schema = file_get_contents(__DIR__ . "/../../../../migrations/schema.sql");
foreach (
    ["role", "user", "category", "enumeration", "api_rate_limit"]
    as $table
) {
    if (
        !preg_match(
            "/CREATE TABLE `" . $table . "` \(.*?ENGINE=InnoDB[^;]+;/s",
            $schema,
            $match,
        )
    ) {
        throw new RuntimeException("Missing table");
    }
    $pdo->exec($match[0]);
}
$migration = file_get_contents(
    __DIR__ . "/../../../../migrations/2026-09-27-sry-tasks.sql",
);
$pdo->exec($migration);
$pdo->exec($migration);
echo "PASS migration applied twice\n";
