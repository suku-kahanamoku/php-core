<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Order\OrderApi;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();
$code    = $request->franchiseCode;
$auth    = new Auth($db);


$order = new OrderApi($db, $code, $auth);

$router->get('/', [$order, 'list']);
$router->post('/', [$order, 'create']);
$router->get('/:id', [$order, 'get']);
$router->patch('/:id/status', [$order, 'updateStatus']);
$router->delete('/:id', [$order, 'delete']);

$router->dispatch($request);
