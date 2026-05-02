<?php

declare(strict_types=1);

/**
 * Shared test helpers.
 * Included by every test_*.php and by the api_test.php runner.
 */

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
        echo "\033[32m  ✓\033[0m {$name}\n";
        $passed++;
    } else {
        echo "\033[31m  ✗\033[0m {$name}" . ($detail ? "  → {$detail}" : '') . "\n";
        $failed++;
    }
}

function section(string $title): void
{
    echo "\n\033[1;34m══ {$title} \033[0m\n";
}

function dump_on_fail(array $res): string
{
    return "HTTP {$res['status']} | " . substr($res['raw'], 0, 200);
}

function print_results(): void
{
    global $passed, $failed;
    $total = $passed + $failed;
    echo "\n\033[1m──────────────────────────────\033[0m\n";
    echo "\033[1mVýsledky:\033[0m  ";
    echo "\033[32m{$passed} passed\033[0m  ";
    if ($failed > 0) {
        echo "\033[31m{$failed} failed\033[0m";
    } else {
        echo "\033[32m0 failed\033[0m";
    }
    echo "  /  {$total} total\n\n";
}
