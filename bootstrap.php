<?php

declare(strict_types=1);

use App\Middleware\CorsMiddleware;
use Dotenv\Dotenv;

// ── Autoload ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

// ── Environment ─────────────────────────────────────────────────────────────
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// ── CORS ─────────────────────────────────────────────────────────────────────
(new CorsMiddleware())();

// ── Error handling ──────────────────────────────────────────────────────────
$appEnv = $_ENV['APP_ENV'] ?? 'production';

if ($appEnv === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

set_exception_handler(function (Throwable $e) use ($appEnv) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => 'Internal Server Error'];
    if ($appEnv === 'development') {
        $response['debug'] = [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTrace(),
        ];
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
});
