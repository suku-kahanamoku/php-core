<?php

declare(strict_types=1);

header('Cache-Control: private, no-store');
require_once __DIR__ . '/../bootstrap.php';

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Http\HttpModule;
use App\Modules\Router\Response;
use App\Modules\Transport\Admin\OnlinePlannerApi;
use App\Modules\Transport\Admin\OnlinePlannerException;
use App\Modules\Transport\TransportModule;

try {
    $api = new OnlinePlannerApi(
        static function (): void {
            // Authentication storage belongs to Auth; no transport tables or queries.
            (new Auth(Database::getInstance()))->requireRole('admin');
        },
        static fn(string $tenant) => TransportModule::onlinePlanners($tenant, $_ENV, HttpModule::client())
    );
    Response::success($api->execute($request), status: 200);
} catch (OnlinePlannerException $error) {
    Response::error($error->getMessage(), $error->status, ['code' => $error->reason]);
}
