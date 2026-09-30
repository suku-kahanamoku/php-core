<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
use App\Modules\Database\Database;
use App\Modules\Router\Router;
use App\Modules\Http\HttpModule;
use App\Modules\Transport\Persistence\TransportRepository;
use App\Modules\Transport\TransportModule;
use App\Utils\RateLimiter;

$db = Database::getInstance();
(new RateLimiter($db, $request->franchiseCode))->hit('transport_read', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 120, 60);
$repository = new TransportRepository($db->getPdo(), $request->franchiseCode);
$api = TransportModule::api($repository, $_ENV, HttpModule::client());
$router = new Router();
$api->registerRoutes($router);
$router->dispatch($request);
