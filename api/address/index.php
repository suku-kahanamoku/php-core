<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Modules\Address\AddressApi;
use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();
$code    = $request->franchiseCode;
$auth    = new Auth($db);


$address = new AddressApi($db, $code, $auth);

$router->post('/', [$address, 'create']);
$router->get('/:id', [$address, 'get']);
$router->put('/:id', [$address, 'replace']);
$router->patch('/:id', [$address, 'update']);
$router->delete('/:id', [$address, 'delete']);

$router->dispatch($request);
