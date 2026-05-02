<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Franchise;
use App\Middleware\CorsMiddleware;
use App\Modules\Address\AddressApi;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();
$code    = Franchise::code();

$router->addGlobalMiddleware(new CorsMiddleware());

$address = new AddressApi($db, $code);

$router->post('/', [$address, 'create']);
$router->get('/:id', [$address, 'get']);
$router->put('/:id', [$address, 'replace']);
$router->patch('/:id', [$address, 'update']);
$router->delete('/:id', [$address, 'delete']);

$router->dispatch($request);
