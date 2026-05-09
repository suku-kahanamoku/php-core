<?php

declare(strict_types=1);

namespace App\Modules\Router;

class Response
{
    /**
     * Odesle JSON odpoved a ukonci pozadavek.
     *
     * @param  mixed $data
     * @param  int   $status  HTTP stavovy kod (vychozi 200)
     * @return never
     */
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Odesle uspesnou JSON odpoved.
     *
     * @param  mixed  $data     Data obsazena v odpovedi
     * @param  string $message  Zprava (vychozi 'OK')
     * @param  int    $status   HTTP stavovy kod (vychozi 200)
     * @return never
     */
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

    /**
     * Odesle odpoved 201 Created.
     *
     * @param  mixed  $data
     * @param  string $message
     * @return never
     */
    public static function created(mixed $data = null, string $message = 'Created'): never
    {
        self::success($data, $message, 201);
    }

    /**
     * Odesle chybovou JSON odpoved.
     *
     * @param  string $message  Popis chyby
     * @param  int    $status   HTTP stavovy kod (vychozi 400)
     * @param  mixed  $errors   Volitelne detailni informace o chybach
     * @return never
     */
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

    /**
     * Odesle odpoved 401 Unauthorized.
     *
     * @param  string $message
     * @return never
     */
    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error($message, 401);
    }

    /**
     * Odesle odpoved 403 Forbidden.
     *
     * @param  string $message
     * @return never
     */
    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error($message, 403);
    }

    /**
     * Odesle odpoved 404 Not Found.
     *
     * @param  string $message
     * @return never
     */
    public static function notFound(string $message = 'Not Found'): never
    {
        self::error($message, 404);
    }

    /**
     * Odesle odpoved 422 Unprocessable Entity (validacni chyba).
     *
     * @param  array  $errors   Pole validacnich chyb (pole => chyba)
     * @param  string $message
     * @return never
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed',
    ): never {
        self::error($message, 422, $errors);
    }
}
