<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Modules\Router\Request;

class CorsMiddleware
{
    private array $_allowedOrigins;

    public function __construct()
    {
        $origins              = $_ENV['ALLOWED_ORIGINS'] ?? '*';
        $this->_allowedOrigins = $origins === '*' ? ['*'] : explode(',', $origins);
    }

    public function __invoke(?Request $request = null): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($this->_allowedOrigins === ['*']) {
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($origin, $this->_allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $allowedHeaders = 'Content-Type, Authorization, X-Requested-With';
        header("Access-Control-Allow-Headers: {$allowedHeaders}");
        header('Access-Control-Allow-Credentials: true');
    }
}
