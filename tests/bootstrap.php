<?php

declare(strict_types=1);

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

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($withAuth && $token !== null) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['status' => 0, 'data' => [], 'raw' => $error];
    }

    $data = json_decode($raw, true) ?? [];
    return ['status' => $status, 'data' => $data, 'raw' => $raw];
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
