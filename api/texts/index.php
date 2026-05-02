<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Router;
use App\Modules\Text\TextApi;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();


$router->addGlobalMiddleware(new CorsMiddleware());

$text = new TextApi($db, $request->franchiseCode);

$router->get('/', [$text, 'list']);
$router->post('/', [$text, 'create']);
$router->get('/by-key/:key', [$text, 'getByKey']);
$router->get('/:id', [$text, 'get']);
$router->put('/:id', [$text, 'replace']);
$router->patch('/:id', [$text, 'update']);
$router->delete('/:id', [$text, 'delete']);

$router->dispatch($request);
