<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Modules\Auth\Auth;
use App\Modules\Auth\AuthApi;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();
$code    = $request->franchiseCode;
$auth    = new Auth($db);

$router->addGlobalMiddleware(new CorsMiddleware());

$authApi = new AuthApi($db, $code, $auth);

$router->post('/login', [$authApi, 'login']);
$router->post('/logout', [$authApi, 'logout']);
$router->get('/me', [$authApi, 'me']);
$router->post('/register', [$authApi, 'register']);
$router->post('/change-password', [$authApi, 'changePassword']);

$router->dispatch($request);
