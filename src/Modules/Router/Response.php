<?php

declare(strict_types=1);

namespace App\Modules\Router;

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
    ): never {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function created(mixed $data = null, string $message = 'Created'): never
    {
        self::success($data, $message, 201);
    }

    public static function error(
        string $message,
        int $status = 400,
        mixed $errors = null,
    ): never {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Not Found'): never
    {
        self::error($message, 404);
    }

    public static function validationError(
        array $errors,
        string $message = 'Validation failed',
    ): never {
        self::error($message, 422, $errors);
    }

}
