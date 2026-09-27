<?php
declare(strict_types=1);
function testDatabase(PDO $pdo): \App\Modules\Database\Database
{
    $reflection = new ReflectionClass(\App\Modules\Database\Database::class);
    $db = $reflection->newInstanceWithoutConstructor();
    $property = $reflection->getProperty("_pdo");
    $property->setAccessible(true);
    $property->setValue($db, $pdo);
    return $db;
}
