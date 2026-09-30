<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest, HttpResponse, HttpException};
use App\Modules\Etymolog\SyncException;

/**
 * Sdílené zpracování odpovědí; volající sestavuje adresu a povoluje každý koncový bod.
 *
 * Omezení rychlosti (429) a chyby API se převádějí na `SyncException` s
 * doporučeným zpožděním, takže správce fronty umí opakovat dávku později.
 */
final class ProviderHttp
{
    /**
     * Provede GET s jednotnou hlavičkou a limitem velikosti odpovědi.
     *
     * @param  HttpClient $http     Sdílený HTTP klient.
     * @param  string     $url      Adresa koncového bodu.
     * @param  int        $maxBytes Maximální velikost odpovědi v bajtech.
     * @return HttpResponse          Odpověď poskytovatele.
     * @throws SyncException         'upstream_rate_limited' nebo 'upstream_unavailable'.
     */
    public static function get(HttpClient $http, string $url, int $maxBytes = 3000000): HttpResponse
    {
        $r = $http->send(new HttpRequest($url, headers: ['User-Agent' => 'Etymolog/1.0 (https://etymolog.prasentace.cz; name history research)', 'Accept' => 'application/json,text/csv'], timeoutMs: 30000, connectTimeoutMs: 5000, maxBytes: $maxBytes));
        if (!$r->successful()) {
            throw new SyncException($r->status === 429 ? 'upstream_rate_limited' : 'upstream_unavailable', max(300, min(604800, $r->retryAfter ?? 300)));
        }
        return $r;
    }

    /**
     * Načte a dekóduje JSON odpověď poskytovatele.
     *
     * @param  HttpClient $http Sdílený HTTP klient.
     * @param  string     $url  Adresa koncového bodu.
     * @return array<string, mixed> Dekódovaná odpověď.
     * @throws SyncException       'invalid_upstream_json', 'upstream_rate_limited' (chyba
     *                            `ratelimited`/`maxlag`) nebo 'upstream_api_error'.
     */
    public static function json(HttpClient $http, string $url): array
    {
        $r = self::get($http, $url);
        try { $data = $r->json(); }
        catch (HttpException) { throw new SyncException('invalid_upstream_json'); }
        if (in_array($data['error']['code'] ?? '', ['ratelimited', 'maxlag'], true)) {
            throw new SyncException('upstream_rate_limited', max(300, min(604800, $r->retryAfter ?? 300)));
        }
        if (isset($data['error']) || isset($data['errors'])) {
            throw new SyncException('upstream_api_error', max(300, min(604800, $r->retryAfter ?? 300)));
        }
        return $data;
    }
}
