<?php

declare(strict_types=1);

header('Cache-Control: private, no-store');
require_once __DIR__ . '/../bootstrap.php';

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Http\HttpModule;
use App\Modules\Router\Response;
use App\Modules\Transport\Admin\LocalPipelineApi;
use App\Modules\Transport\Gateway\JavaTransportException;
use App\Modules\Transport\TransportModule;

try {
    $api = new LocalPipelineApi(
        static function (): void {
            // Authentication storage belongs to Auth; no transport tables or queries.
            (new Auth(Database::getInstance()))->requireRole('admin');
        },
        static fn(string $tenant) => TransportModule::localPipeline($tenant, $_ENV, HttpModule::client())
    );
    Response::success($api->execute($request), status: $request->method === 'POST' ? 202 : 200);
} catch (JavaTransportException $error) {
    Response::error($error->getMessage(), $error->status, ['code' => $error->reason]);
}
