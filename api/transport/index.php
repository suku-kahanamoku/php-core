<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
use App\Modules\Router\Router;
use App\Modules\Http\HttpModule;
use App\Modules\Transport\Gateway\JavaTransportException;
use App\Modules\Router\Response;
use App\Modules\Transport\TransportModule;

try {
    $api = TransportModule::api($request->franchiseCode, $_ENV, HttpModule::client());
} catch (JavaTransportException $error) {
    header('Cache-Control: no-store');
    Response::error($error->getMessage(), $error->status, ['code' => $error->reason]);
}
$router = new Router();
$api->registerRoutes($router);
$router->dispatch($request);
