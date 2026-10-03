<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Gateway\{JavaTransportApi, JavaTransportException, JavaTransportService};

/** Composition root of the PHP gateway. All transport operations belong to Java. */
final class TransportModule
{
    public static function api(string $tenant, array $env, HttpClient $http): JavaTransportApi
    {
        if (($env['TRANSPORT_JAVA_ENABLED'] ?? '') !== '1'
            || $tenant === '' || ($env['TRANSPORT_JAVA_TENANT'] ?? '') !== $tenant) {
            throw new JavaTransportException('invalid_configuration', 'Java transport is not configured for this tenant.', 503);
        }
        return new JavaTransportApi(new JavaTransportService(
            $http,
            (string)($env['TRANSPORT_JAVA_URL'] ?? ''),
            (string)($env['TRANSPORT_JAVA_TOKEN'] ?? '')
        ));
    }
}
