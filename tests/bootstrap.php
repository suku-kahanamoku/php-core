<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

/**
 * Shared test helpers.
 * Included by every test_*.php and by the api_test.php runner.
 */

// ── Test prefix – all dynamic test data identifiers use this prefix ──────────
const TEST_PREFIX = 'test_';

// ── Load .env so cleanup_test_data() can connect to the DB ──────────────────
if (!isset($_ENV['DB_HOST'])) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v]    = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

/**
 * Delete all rows whose identifying column starts with TEST_PREFIX.
 * Safe to call before and after the test suite.
 */
function cleanup_test_data(): void
{
    $host    = $_ENV['DB_HOST']    ?? 'localhost';
    $port    = $_ENV['DB_PORT']    ?? '3306';
    $dbName  = $_ENV['DB_NAME']    ?? 'php_core';
    $user    = $_ENV['DB_USER']    ?? 'admin';
    $pass    = $_ENV['DB_PASSWORD'] ?? 'admin';
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $prefix = TEST_PREFIX . '%';

        // Order of deletion matters (FK constraints)
        $pdo->prepare('DELETE FROM user        WHERE email   LIKE ?')->execute([$prefix]);
        $pdo->prepare('DELETE FROM role        WHERE name    LIKE ?')->execute([$prefix]);
        $pdo->prepare('DELETE FROM product     WHERE sku     LIKE ?')->execute([$prefix]);
        $pdo->prepare('DELETE FROM customer_profile WHERE syscode LIKE ?')->execute([$prefix]);
        $pdo->prepare('DELETE FROM category    WHERE name    LIKE ? OR syscode LIKE ?')->execute([$prefix, $prefix]);
        $pdo->prepare('DELETE FROM enumeration WHERE type    LIKE ?')->execute([$prefix]);
        $pdo->prepare('DELETE FROM text        WHERE syscode LIKE ?')->execute([$prefix]);
        $pdo->prepare('DELETE FROM file        WHERE name    LIKE ?')->execute([$prefix]);
    } catch (\PDOException $e) {
        echo "  [cleanup] DB error: {$e->getMessage()}\n";
    }
}

$passed = 0;
$failed = 0;
$token  = null;

function request(string $method, string $url, array $body = [], bool $withAuth = true): array
{
    global $token;

    $headers = test_http_headers($withAuth);
    $response = \App\Modules\Http\HttpModule::client()->send(new \App\Modules\Http\HttpRequest(
        $url, $method, $headers, $body === [] ? null : $body, timeoutMs: 10000,
    ));
    return test_http_result($response);
}

function test_http_headers(bool $withAuth = true): array
{
    global $token;
    $headers = ['Accept' => 'application/json'];
    $internalKey = trim((string)($_ENV['INTERNAL_API_KEY'] ?? ''));
    if ($internalKey !== '') {
        $headers['X-Internal-Key'] = $internalKey;
    }
    if ($withAuth && $token !== null) {
        $headers['Authorization'] = 'Bearer '.$token;
    }
    return $headers;
}

function test_http_result(\App\Modules\Http\HttpResponse $response): array
{
    if ($response->error !== null) {
        return ['status' => 0, 'data' => [], 'raw' => $response->error];
    }
    return ['status' => $response->status, 'data' => json_decode($response->body, true) ?? [], 'raw' => $response->body];
}

function test_upload_file(string $base, string $path, string $mime, string $filename, bool $withAuth = true): array
{
    $file = fopen($path, 'rb');
    try {
        return test_http_result(\App\Modules\Http\HttpModule::client()->send(new \App\Modules\Http\HttpRequest(
            $base.'/files/upload', 'POST', test_http_headers($withAuth), timeoutMs: 10000,
            multipart: [['name' => 'file', 'contents' => $file, 'filename' => $filename, 'headers' => ['Content-Type' => $mime]]],
        )));
    } finally {
        if (is_resource($file)) {
            fclose($file);
        }
    }
}

function assert_test(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ {$name}\n";
        $passed++;
    } else {
        echo "  ✗ {$name}" . ($detail ? "  → {$detail}" : '') . "\n";
        $failed++;
    }
}

function section(string $title): void
{
    echo "\n══ {$title}\n";
}

function dump_on_fail(array $res): string
{
    return "HTTP {$res['status']} | " . substr($res['raw'], 0, 200);
}

function print_results(): void
{
    global $passed, $failed;
    $total = $passed + $failed;
    echo "\n──────────────────────────────\n";
    echo "Výsledky:  ";
    echo "{$passed} passed  ";
    if ($failed > 0) {
        echo "{$failed} failed";
    } else {
        echo "0 failed";
    }
    echo "  /  {$total} total\n\n";
}
