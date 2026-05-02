<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Modules\Category\CategoryApi;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();


$router->addGlobalMiddleware(new CorsMiddleware());

$category = new CategoryApi($db, $request->franchiseCode);

$router->get('/', [$category, 'list']);
$router->post('/', [$category, 'create']);
$router->get('/:id', [$category, 'get']);
$router->put('/:id', [$category, 'replace']);
$router->patch('/:id', [$category, 'update']);
$router->delete('/:id', [$category, 'delete']);

$router->dispatch($request);
