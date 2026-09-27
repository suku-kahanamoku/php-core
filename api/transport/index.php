<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
use App\Modules\Database\Database;
use App\Modules\Router\Router;
use App\Modules\Transport\{TransportApi,ProviderRegistry,JourneyService,ResourceService};
use App\Modules\Http\HttpModule;
use App\Modules\Transport\Repositories\TransportRepository;
use App\Utils\RateLimiter;

$db = Database::getInstance();
(new RateLimiter($db, $request->franchiseCode))->hit('transport_read', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 120, 60);
$repository = new TransportRepository($db->getPdo(), $request->franchiseCode);
$registry = ProviderRegistry::build($repository->providers(), $request->franchiseCode, $_ENV);
$http = HttpModule::client();
$resources = new ResourceService($registry, $http, $repository);
$api = new TransportApi(new JourneyService($registry, $http, $repository, $resources), $resources, $registry, $repository);
$router = new Router();
$api->registerRoutes($router);
$router->dispatch($request);
