<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Modules\Mailer\MailerApi;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();

$api = new MailerApi();
$api->registerRoutes($router);

$router->dispatch($request);
