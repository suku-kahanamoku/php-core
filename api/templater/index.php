<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Templater\TemplaterApi;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();
$auth    = new Auth($db);

$api = new TemplaterApi($request->franchiseCode, $auth);
$api->registerRoutes($router);

$router->dispatch($request);
