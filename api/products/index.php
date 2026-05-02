<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Product\ProductApi;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();
$code    = $request->franchiseCode;
$auth    = new Auth($db);

$router->addGlobalMiddleware(new CorsMiddleware());

$product = new ProductApi($db, $code, $auth);

$router->get('/', [$product, 'list']);
$router->post('/', [$product, 'create']);
$router->get('/:id', [$product, 'get']);
$router->put('/:id', [$product, 'replace']);
$router->patch('/:id', [$product, 'update']);
$router->delete('/:id', [$product, 'delete']);
$router->patch('/:id/stock', [$product, 'adjustStock']);

$router->dispatch($request);
