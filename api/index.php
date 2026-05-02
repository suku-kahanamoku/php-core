<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();

$router->addGlobalMiddleware(new CorsMiddleware());

$router->get('/', function (Request $request) {
    $endpoints = [
        'auth' => [
            ['method' => 'POST', 'path' => '/auth/login'],
            ['method' => 'POST', 'path' => '/auth/logout'],
            ['method' => 'GET',  'path' => '/auth/me'],
            ['method' => 'POST', 'path' => '/auth/register'],
            ['method' => 'POST', 'path' => '/auth/change-password'],
        ],
        'roles' => [
            ['method' => 'GET',    'path' => '/roles'],
            ['method' => 'POST',   'path' => '/roles'],
            ['method' => 'GET',    'path' => '/roles/:id'],
            ['method' => 'PUT',    'path' => '/roles/:id'],
            ['method' => 'PATCH',  'path' => '/roles/:id'],
            ['method' => 'DELETE', 'path' => '/roles/:id'],
        ],
        'users' => [
            ['method' => 'GET',    'path' => '/users'],
            ['method' => 'POST',   'path' => '/users'],
            ['method' => 'GET',    'path' => '/users/:id'],
            ['method' => 'PUT',    'path' => '/users/:id'],
            ['method' => 'PATCH',  'path' => '/users/:id'],
            ['method' => 'DELETE', 'path' => '/users/:id'],
            ['method' => 'GET',    'path' => '/users/:userId/address'],
        ],
        'address' => [
            ['method' => 'POST',   'path' => '/address'],
            ['method' => 'GET',    'path' => '/address/:id'],
            ['method' => 'PUT',    'path' => '/address/:id'],
            ['method' => 'PATCH',  'path' => '/address/:id'],
            ['method' => 'DELETE', 'path' => '/address/:id'],
        ],
        'categories' => [
            ['method' => 'GET',    'path' => '/categories'],
            ['method' => 'POST',   'path' => '/categories'],
            ['method' => 'GET',    'path' => '/categories/:id'],
            ['method' => 'PUT',    'path' => '/categories/:id'],
            ['method' => 'PATCH',  'path' => '/categories/:id'],
            ['method' => 'DELETE', 'path' => '/categories/:id'],
        ],
        'products' => [
            ['method' => 'GET',    'path' => '/products'],
            ['method' => 'POST',   'path' => '/products'],
            ['method' => 'GET',    'path' => '/products/:id'],
            ['method' => 'PUT',    'path' => '/products/:id'],
            ['method' => 'PATCH',  'path' => '/products/:id'],
            ['method' => 'DELETE', 'path' => '/products/:id'],
            ['method' => 'PATCH',  'path' => '/products/:id/stock'],
        ],
        'texts' => [
            ['method' => 'GET',    'path' => '/texts'],
            ['method' => 'POST',   'path' => '/texts'],
            ['method' => 'GET',    'path' => '/texts/by-key/:key'],
            ['method' => 'GET',    'path' => '/texts/:id'],
            ['method' => 'PUT',    'path' => '/texts/:id'],
            ['method' => 'PATCH',  'path' => '/texts/:id'],
            ['method' => 'DELETE', 'path' => '/texts/:id'],
        ],
        'enumerations' => [
            ['method' => 'GET',    'path' => '/enumerations'],
            ['method' => 'GET',    'path' => '/enumerations/types'],
            ['method' => 'POST',   'path' => '/enumerations'],
            ['method' => 'GET',    'path' => '/enumerations/:id'],
            ['method' => 'PUT',    'path' => '/enumerations/:id'],
            ['method' => 'PATCH',  'path' => '/enumerations/:id'],
            ['method' => 'DELETE', 'path' => '/enumerations/:id'],
        ],
        'orders' => [
            ['method' => 'GET',    'path' => '/orders'],
            ['method' => 'POST',   'path' => '/orders'],
            ['method' => 'GET',    'path' => '/orders/:id'],
            ['method' => 'PATCH',  'path' => '/orders/:id/status'],
            ['method' => 'DELETE', 'path' => '/orders/:id'],
        ],
        'invoices' => [
            ['method' => 'GET',    'path' => '/invoices'],
            ['method' => 'POST',   'path' => '/invoices'],
            ['method' => 'GET',    'path' => '/invoices/:id'],
            ['method' => 'PATCH',  'path' => '/invoices/:id/status'],
            ['method' => 'DELETE', 'path' => '/invoices/:id'],
        ],
    ];

    Response::success([
        'name'      => 'php-core API',
        'version'   => '1.0.0',
        'endpoints' => $endpoints,
        'total'     => array_sum(array_map('count', $endpoints)),
    ]);
});

$router->dispatch($request);
