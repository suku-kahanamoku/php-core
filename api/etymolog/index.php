<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Etymolog\{EtymologApi, EtymologRepository, EtymologService, EtymologSyncRepository, EtymologStoryRepository, EtymologExternalRepository, ResourceRegistry};
use App\Modules\Router\Router;

$db = Database::getInstance();
if ($request->method === 'POST' && $request->uri === '/sync/worker') {
    (new \App\Modules\Etymolog\EtymologWorkerApi(\App\Modules\Etymolog\EtymologModule::httpWorker($db, $request->franchiseCode)))->handle($request);
}
// Only these GET routes omit user authentication; bootstrap still requires the internal key and a known tenant.
if ($request->method === 'GET' && preg_match('~^/public/names(?:/[1-9][0-9]*)?$~D', $request->uri)) {
    $public = new \App\Modules\Etymolog\EtymologPublicApi(new \App\Modules\Etymolog\EtymologPublicService(new \App\Modules\Etymolog\EtymologPublicRepository($db, $request->franchiseCode)));
    $router = new Router();
    $public->registerRoutes($router);
    $router->dispatch($request);
}
$auth = new Auth($db);
$auth->require();
$repositories = [];
foreach (array_keys(ResourceRegistry::all()) as $resource) {
    $repositories[$resource] = new EtymologRepository($db, $request->franchiseCode, $resource);
}
$api = new EtymologApi(new EtymologService($repositories, $auth, new EtymologSyncRepository($db, $request->franchiseCode), new EtymologStoryRepository($db, $request->franchiseCode), new EtymologExternalRepository($db, $request->franchiseCode), \App\Modules\Etymolog\EtymologModule::background($db, $request->franchiseCode)));
$router = new Router();
$api->registerRoutes($router);
$router->dispatch($request);
