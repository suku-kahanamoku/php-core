<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Modules\Router\Request;

class CorsMiddleware
{
    public function __invoke(?Request $request = null): void
    {
        // Authentication uses explicit tokens, not cross-origin cookies.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $allowedHeaders = 'Content-Type, Authorization, X-Requested-With';
        header("Access-Control-Allow-Headers: {$allowedHeaders}");
    }
}
