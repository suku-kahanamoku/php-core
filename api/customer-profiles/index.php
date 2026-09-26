<?php

declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

use App\Modules\Auth\Auth;
use App\Modules\CustomerProfile\CustomerProfileApi;
use App\Modules\Database\Database;
use App\Modules\Router\Router;

$router = new Router();
$db = Database::getInstance();
$api = new CustomerProfileApi($db, $request->franchiseCode, new Auth($db));
$api->registerRoutes($router);
$router->dispatch($request);
