<?php

/**
 * Migration runner
 * Usage: php bin/migrate.php
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;

$db  = Database::getInstance()->getPdo();
$sql = file_get_contents(__DIR__ . '/../migrations/schema.sql');

echo "Running migrations...\n";

try {
    $db->exec($sql);
    echo "Migration completed successfully.\n";
} catch (\PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
