<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Modules\Database\Database;
use App\Modules\Order\OrderApi;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();


$router->addGlobalMiddleware(new CorsMiddleware());

$order = new OrderApi($db, $request->franchiseCode);

$router->get('/', [$order, 'list']);
$router->post('/', [$order, 'create']);
$router->get('/:id', [$order, 'get']);
$router->patch('/:id/status', [$order, 'updateStatus']);
$router->delete('/:id', [$order, 'delete']);

$router->dispatch($request);
