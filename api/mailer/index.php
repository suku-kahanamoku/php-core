<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Mailer\MailerApi;
use App\Modules\Router\Router;

$router  = new Router();
$code    = $request->franchiseCode;
$db      = Database::getInstance();
$auth    = new Auth($db);

$api = new MailerApi($db, $code, $auth);
$api->registerRoutes($router);

$router->dispatch($request);
