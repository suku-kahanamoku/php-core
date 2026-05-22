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

ini_set('display_errors', '0');
error_reporting(0);

/**
 * Prevede PHP chyby na ErrorException, ktera je pak zachycena set_exception_handler.
 * V production rezimu se toto nenastavuje (chyby jsou zamlceny).
 */
if ($appEnv === 'development') {
    error_reporting(E_ALL);
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
}

/**
 * Zachycuje fatalni chyby (E_ERROR, E_PARSE, …), ktere nelze zachytit set_error_handler.
 * V production rezimu nevypise nic navic.
 */
register_shutdown_function(static function () use ($appEnv): void {
    $error = error_get_last();
    if (
        $error === null
        || !in_array(
            $error['type'],
            [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR],
            true,
        )
    ) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => 'Internal Server Error'];
    if ($appEnv === 'development') {
        $response['debug'] = [
            'type'    => $error['type'],
            'message' => $error['message'],
            'file'    => $error['file'],
            'line'    => $error['line'],
        ];
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
});

set_exception_handler(static function (\Throwable $e) use ($appEnv): void {
    if (headers_sent()) {
        return;
    }

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
