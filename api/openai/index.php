<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Modules\Database\Database;
use App\Modules\OpenAi\OpenAiApi;
use App\Modules\Router\Router;

$router = new Router();
$api = new OpenAiApi(Database::getInstance(), $request->franchiseCode);
$api->registerRoutes($router);
$router->dispatch($request);
