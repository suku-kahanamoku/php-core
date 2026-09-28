<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest, HttpResponse, HttpException};
use App\Modules\Etymolog\SyncException;

/** Shared response handling; callers construct and allowlist every endpoint. */
final class ProviderHttp
{
    public static function get(HttpClient $http, string $url, int $maxBytes = 3000000): HttpResponse
    {
        $r = $http->send(new HttpRequest($url, headers: ['User-Agent' => 'Etymolog/1.0 (php-core; onomastic research catalog)', 'Accept' => 'application/json,text/csv'], timeoutMs: 30000, connectTimeoutMs: 5000, maxBytes: $maxBytes));
        if (!$r->successful()) {
            throw new SyncException($r->status === 429 ? 'upstream_rate_limited' : 'upstream_unavailable', max(300, min(604800, $r->retryAfter ?? 300)));
        }
        return $r;
    }

    public static function json(HttpClient $http, string $url): array
    {
        $r = self::get($http, $url);
        try { $data = $r->json(); }
        catch (HttpException) { throw new SyncException('invalid_upstream_json'); }
        if (isset($data['error']) || isset($data['errors'])) {
            throw new SyncException('upstream_api_error', max(300, min(604800, $r->retryAfter ?? 300)));
        }
        return $data;
    }
}
