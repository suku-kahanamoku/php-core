#!/usr/bin/env php
<?php

declare(strict_types=1);

/** Offline checks: synthetic request credentials, no database or external services. */
$root = dirname(__DIR__);
$failures = 0;
$count = 0;
$run = static function (string $label, array $options, int $expectedStatus, ?bool $expectedInternal = null) use ($root, &$failures, &$count): void {
    $module = $options['module'] ?? 'products';
    $entry = $module === '' ? '/api/index.php' : '/api/' . $module . '/index.php';
    $prefix = $options['prefix'] ?? '';
    $server = [
        'REQUEST_METHOD' => $options['method'] ?? 'GET',
        'REQUEST_URI' => $prefix . ($options['path'] ?? '/api/products'),
        'SCRIPT_NAME' => $prefix . $entry,
        'SCRIPT_FILENAME' => $root . $entry,
        'HTTP_HOST' => 'backend.test',
        'HTTP_X_FORWARDED_HOST' => $options['host'] ?? 'tenant.test',
    ];
    if (isset($options['internal'])) $server['HTTP_X_INTERNAL_KEY'] = $options['internal'];
    if (isset($options['rokid'])) $server['HTTP_X_ROKID_KEY'] = $options['rokid'];
    if (isset($options['bearer'])) $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $options['bearer'];
    $env = [
        'FRANCHISE_CODES' => 'tenant.test:tenant,second.test:second',
        'INTERNAL_API_KEY' => $options['configuredInternal'] ?? 'internal-test-key',
        'ROKID_AI_CLIENT_KEY' => $options['configuredRokid'] ?? 'rokid-test-key',
        'APP_ENV' => 'production',
        // If an unauthenticated entrypoint ever reaches PDO, fail locally.
        'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'DB_USER' => 'unused',
        'DB_PASSWORD' => 'unused', 'DB_NAME' => 'unused',
    ];
    $code = '$_SERVER = ' . var_export($server, true) . '; $_ENV = ' . var_export($env, true) . '; $_GET = $_POST = $_FILES = [];'
        . 'register_shutdown_function(static function () { echo "\nSTATUS:" . (http_response_code() ?: 200); });';
    if ($options['entrypoint'] ?? false) {
        $code .= 'require ' . var_export($root . $entry, true) . ';';
    } else {
        $code .= 'require ' . var_export($root . '/vendor/autoload.php', true) . ';'
            . '$request = new App\\Modules\\Router\\Request();'
            . '(new App\\Middleware\\InternalAuthMiddleware())($request);'
            . 'echo json_encode(["internal" => $request->internalAuthenticated, "tenant" => $request->franchiseCode]);';
    }
    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start test PHP process');
    $output = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    preg_match('/\nSTATUS:(\d+)$/', $output, $matches);
    $status = (int) ($matches[1] ?? 0);
    $body = json_decode(preg_replace('/\nSTATUS:\d+$/', '', $output), true);
    $ok = $exit === 0 && $status === $expectedStatus
        && ($expectedInternal === null || ($body['internal'] ?? null) === $expectedInternal);
    $count++;
    if (!$ok) $failures++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . ($ok ? '' : " (status=$status, exit=$exit) $output $stderr") . PHP_EOL;
};

$run('internal key required', [], 401);
$run('wrong internal key', ['internal' => 'wrong'], 401);
$run('empty configured key fails closed', ['internal' => 'internal-test-key', 'configuredInternal' => ''], 401);
$run('valid internal key', ['internal' => 'internal-test-key'], 200, true);
$run('Bearer cannot replace application key', ['bearer' => 'admin-token'], 401);
$run('Rokid key cannot access products', ['rokid' => 'rokid-test-key'], 401);
$run('unknown tenant rejected', ['internal' => 'internal-test-key', 'host' => 'unknown.test'], 403);
$run('preflight does not enter API', ['method' => 'OPTIONS'], 204);
$run('preflight still resolves tenant', ['method' => 'OPTIONS', 'host' => 'unknown.test'], 403);
foreach (['realtime-session', 'tool'] as $route) {
    $base = ['module' => 'openai', 'method' => 'POST', 'path' => '/api/openai/' . $route];
    $run("$route accepts Rokid key", $base + ['rokid' => 'rokid-test-key'], 200, false);
    $run("$route rejects missing Rokid key", $base, 401);
    $run("$route rejects wrong Rokid key", $base + ['rokid' => 'wrong'], 401);
    $run("$route does not accept internal key instead", $base + ['internal' => 'internal-test-key'], 401);
    $run("$route rejects empty configured Rokid key", $base + ['rokid' => 'rokid-test-key', 'configuredRokid' => ''], 401);
    $run("$route trailing slash/query", array_replace($base, ['path' => $base['path'] . '/?x=1', 'rokid' => 'rokid-test-key']), 200, false);
    $run("$route subdirectory installation", $base + ['prefix' => '/nested', 'rokid' => 'rokid-test-key'], 200, false);
    $run("$route exception is POST-only", array_replace($base, ['method' => 'GET', 'rokid' => 'rokid-test-key']), 401);
    $run("$route exception is module-specific", ['method' => 'POST', 'path' => '/api/products/' . $route, 'rokid' => 'rokid-test-key'], 401);
    $run("$route exception has exact path", array_replace($base, ['path' => $base['path'] . '/extra', 'rokid' => 'rokid-test-key']), 401);
}
foreach (glob($root . '/api/*/index.php') as $entry) {
    $module = basename(dirname($entry));
    $run("$module entrypoint rejects before PDO", ['module' => $module, 'path' => '/api/' . $module, 'entrypoint' => true], 401);
    $run("$module entrypoint preflight before PDO", ['module' => $module, 'path' => '/api/' . $module, 'entrypoint' => true, 'method' => 'OPTIONS'], 204);
}
$run('root entrypoint rejects missing key', ['module' => '', 'path' => '/api', 'entrypoint' => true], 401);
echo "$count checks, $failures failures\n";
if ($failures > 0) exit(1);
