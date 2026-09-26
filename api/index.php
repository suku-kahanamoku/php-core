<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Modules\Router\Router;

$router  = new Router();

$router->dispatch($request);
