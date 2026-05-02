<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Modules\Router\Request;

class CorsMiddleware
{
    private array $allowedOrigins;

    public function __construct()
    {
        $origins = $_ENV['ALLOWED_ORIGINS'] ?? '*';
        $this->allowedOrigins = $origins === '*' ? ['*'] : explode(',', $origins);
    }

    public function __invoke(Request $request): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($this->allowedOrigins === ['*']) {
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($origin, $this->allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
    }
}
