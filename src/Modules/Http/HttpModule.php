<?php

declare(strict_types=1);

namespace App\Modules\Http;

use App\Modules\Http\Contracts\HttpClient;

/** Composition root for outbound transports. Domain services accept injectable contracts. */
final class HttpModule
{
    private static ?HttpClient $http = null;

    public static function client(): HttpClient
    {
        return self::$http ??= new HttpService();
    }

    public static function smtp(string $franchiseCode = ''): SmtpService
    {
        return new SmtpService($franchiseCode);
    }
}
