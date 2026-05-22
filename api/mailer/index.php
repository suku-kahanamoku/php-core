<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Modules\Mailer\MailerApi;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$code    = $request->franchiseCode;

$api = new MailerApi($code);
$api->registerRoutes($router);

$router->dispatch($request);
