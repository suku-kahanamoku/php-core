<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Admin\OnlinePlannerException;

/** Composition root of the administrator authorization bridge. Transport goes directly to Java. */
final class TransportModule
{
    /** Only call after the application's administrator authorization; not a public gateway route. */
    public static function onlinePlanners(string $tenant, array $env, HttpClient $http): Admin\OnlinePlannerService
    {
        if (($env['TRANSPORT_ONLINE_CONTROL_ENABLED'] ?? '') !== '1'
            || $tenant === '' || ($env['TRANSPORT_JAVA_TENANT'] ?? '') !== $tenant) {
            throw new OnlinePlannerException('invalid_configuration', 'Online planner control is not configured for this tenant.', 503);
        }
        return new Admin\OnlinePlannerService($http,
            (string)($env['TRANSPORT_ONLINE_CONTROL_URL'] ?? ''),
            (string)($env['TRANSPORT_ONLINE_CONTROL_TOKEN'] ?? ''));
    }
}
