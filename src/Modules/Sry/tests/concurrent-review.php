<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}

require __DIR__ . "/../../../../vendor/autoload.php";
require __DIR__ . "/database.php";
use App\Modules\Sry\{SrySqlRepository, SryService, CloudflareGateway, SryError};
$dsn = getenv("SRY_TEST_DSN");
if (!$dsn || !str_contains($dsn, "dbname=sry_test")) {
    throw new RuntimeException("Disposable test database required");
}
$input = json_decode(
    stream_get_contents(STDIN),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$pdo = new PDO($dsn, "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$service = new SryService(testDatabase($pdo), new CloudflareGateway("", ""));
$delay = max(0, (int) (($input["start"] - microtime(true)) * 1000000));
if ($delay) {
    usleep($delay);
}
try {
    $service->review($input["actor"], $input["id"], [
        "revision" => 2,
        "decision" => "approved",
        "note" => "Concurrent review",
    ]);
    echo "ok";
} catch (SryError $e) {
    echo $e->errorCode;
}
