<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Modules\Auth\AuthApi;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();


$router->addGlobalMiddleware(new CorsMiddleware());

$auth = new AuthApi($db, $request->franchiseCode);

$router->post('/login', [$auth, 'login']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/me', [$auth, 'me']);
$router->post('/register', [$auth, 'register']);
$router->post('/change-password', [$auth, 'changePassword']);

$router->dispatch($request);
