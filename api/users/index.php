<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Modules\Address\AddressApi;
use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Router;
use App\Modules\User\UserApi;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();
$code    = $request->franchiseCode;
$auth    = new Auth($db);

$address = new AddressApi($db, $code, $auth);
$api = new UserApi($db, $code, $auth);
$api->registerRoutes($router, $address);

$router->dispatch($request);
