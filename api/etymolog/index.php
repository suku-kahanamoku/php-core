<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Etymolog\{EtymologApi, EtymologRepository, EtymologService, EtymologSyncRepository, EtymologStoryRepository, EtymologExternalRepository, ResourceRegistry};
use App\Modules\Router\Router;

$db = Database::getInstance();
$auth = new Auth($db);
$auth->require();
$repositories = [];
foreach (array_keys(ResourceRegistry::all()) as $resource) {
    $repositories[$resource] = new EtymologRepository($db, $request->franchiseCode, $resource);
}
$api = new EtymologApi(new EtymologService($repositories, $auth, new EtymologSyncRepository($db, $request->franchiseCode), new EtymologStoryRepository($db, $request->franchiseCode), new EtymologExternalRepository($db, $request->franchiseCode)));
$router = new Router();
$api->registerRoutes($router);
$router->dispatch($request);
