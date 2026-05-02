<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Franchise;
use App\Middleware\CorsMiddleware;
use App\Modules\Database\Database;
use App\Modules\Enumeration\EnumerationApi;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();
$code    = Franchise::code();

$router->addGlobalMiddleware(new CorsMiddleware());

$enumeration = new EnumerationApi($db, $code);

$router->get('/', [$enumeration, 'list']);
$router->get('/types', [$enumeration, 'types']);
$router->post('/', [$enumeration, 'create']);
$router->get('/:id', [$enumeration, 'get']);
$router->put('/:id', [$enumeration, 'replace']);
$router->patch('/:id', [$enumeration, 'update']);
$router->delete('/:id', [$enumeration, 'delete']);

$router->dispatch($request);
