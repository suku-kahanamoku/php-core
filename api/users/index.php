<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Modules\Address\AddressApi;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Router;
use App\Modules\User\UserApi;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();


$router->addGlobalMiddleware(new CorsMiddleware());

$user    = new UserApi($db, $request->franchiseCode);
$address = new AddressApi($db, $request->franchiseCode);

$router->get('/', [$user, 'list']);
$router->post('/', [$user, 'create']);
$router->get('/:id', [$user, 'get']);
$router->put('/:id', [$user, 'replace']);
$router->patch('/:id', [$user, 'update']);
$router->delete('/:id', [$user, 'delete']);

// Nested address under users
$router->get('/:userId/address', [$address, 'list']);

$router->dispatch($request);
