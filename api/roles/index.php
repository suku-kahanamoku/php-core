<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Role\RoleApi;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();
$code    = $request->franchiseCode;
$auth    = new Auth($db);


$role = new RoleApi($db, $code, $auth);

$router->get('/', [$role, 'list']);
$router->post('/', [$role, 'create']);
$router->get('/:id', [$role, 'get']);
$router->put('/:id', [$role, 'replace']);
$router->patch('/:id', [$role, 'update']);
$router->delete('/:id', [$role, 'delete']);

$router->dispatch($request);
