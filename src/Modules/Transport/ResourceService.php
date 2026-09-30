<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Contracts\{ResourceProvider};
use App\Modules\Transport\Repositories\TransportRepository;

/**
 * Čtení míst a jednotlivých zdrojů napříč poskytovateli.
 *
 * ID zdrojů je neprůhledné a kódované přes `ResourceIdCodec`, takže se vždy
 * ověřuje okrsek i druh zdroje. Živé údaje se doplňují z API poskytovatele,
 * ale při nedostupnosti se použije importovaný jízdní řád; výsledek pak nese
 * `partial` a upozornění na neúplné pokrytí.
 */
final class ResourceService
{
    /**
     * @param  ProviderRegistry    $registry   Poskytovatelé okurku.
     * @param  HttpClient          $http       Sdílený HTTP klient.
     * @param  TransportRepository $repository Importovaná data a stav poskytovatelů.
     * @return void
     */
    public function __construct(private readonly ProviderRegistry $registry, private readonly HttpClient $http, private readonly TransportRepository $repository)
    {
    }

    /**
     * Vyhledá místa u všech poskytovatelů a doplní je importovaným indexem.
     *
     * @param  string      $query   Hledaný název, 2–120 znaků.
     * @param  int         $limit   Požadovaný počet výsledků.
     * @param  string|null $country Kód země ISO 3166-1 alpha-2, nebo null.
     * @return array{places: list<array<string, mixed>>, partial: bool, sources: list<array<string, mixed>>}
     *         Místa, příznak neúplných výsledků a stav jednotlivých zdrojů.
     * @throws TransportException 'invalid_query' či 'invalid_country' (bez
     *                            stavového kódu) při chybném vstupu,
     *                            'sources_unavailable' (503), pokud nelze
     *                            žádný zdroj.
     */
    public function places(string $query, int $limit, ?string $country): array
    {
        if (mb_strlen(trim($query)) < 2 || mb_strlen($query) > 120) {
            throw new TransportException('invalid_query', 'Place query must contain 2 to 120 characters.');
        }
        if ($country !== null && !preg_match('/^[A-Z]{2}$/D', $country)) {
            throw new TransportException('invalid_country', 'Invalid country code.');
        }
        $requests = [];
        $selected = [];
        $sources = [];
        $items = [];
        foreach ($this->registry->all() as $code => $provider) {
            if (!$provider instanceof ResourceProvider || !in_array('places', $provider->capabilities(), true)) {
                continue;
            }
            if ($country !== null && !in_array($country, array_column($provider->definition()->coverage, 'country'), true)) {
                continue;
            }
            if (!$this->repository->acquireProvider($code, (int)($provider->definition()->config['min_interval_ms'] ?? 0))) {
                $sources[] = ['provider' => $code,'status' => 'unavailable'];
                continue;
            }
            $requests[$code] = $provider->resourceRequest('places', ['query' => $query,'limit' => $limit]);
            $selected[$code] = $provider;
        }
        foreach ($this->http->sendAll($requests) as $code => $response) {
            try {
                $data = $selected[$code]->resourceResult('places', $response, ['query' => $query,'limit' => $limit]);
                foreach ($data as $item) {
                    $item['source_mode'] = 'live';
                    $items[$item['id']] = $item;
                }
                $this->repository->providerSuccess($code);
                $sources[] = ['provider' => $code,'status' => 'ok'];
            } catch (\Throwable) {
                $this->repository->providerFailure($code, $response->retryAfter);
                $sources[] = ['provider' => $code,'status' => 'unavailable'];
            }
        }
        // Imported place index is a useful offline lookup; fresh API values win for identical IDs.
        foreach ($this->repository->places($query, $limit, $country) as $item) {
            $items[$item['id']] ??= $item;
        }
        $partial = (bool)array_filter($sources, fn ($s) => $s['status'] !== 'ok');
        if (!$items && $partial && !array_filter($sources, fn ($s) => $s['status'] === 'ok')) {
            throw new TransportException('sources_unavailable', 'Place sources are unavailable.', 503, ['sources' => $sources]);
        }
        return ['places' => array_slice(array_values($items), 0, $limit),'partial' => $partial,'sources' => $sources];
    }
    /**
     * Načte jeden zdroj (zastávku, spoj, odjezdy nebo polohu) podle ID.
     *
     * ID se nejprve ověří a rozloží; u spojů je povinné datum služby. Je-li
     * živý zdroj nedostupný, použije se importovaný jízdní řád a u odjezdů
     * plánovač nastavený jako `schedule_provider`; výsledek je pak označen jako
     * `partial` s režimem `fallback`. Zdroj jiného okurku se tím způsobem
     * otevřít nedá.
     *
     * @param  string               $operation `stop`, `trip`, `departures` nebo `realtime`.
     * @param  string               $id        Neprůhledné ID zdroje z našeho API.
     * @param  array<string, mixed> $input     Doplňující vstup (např. čas odjezdu).
     * @param  int                  $depth     Aktuální hloubka přesměrování mezi poskytovateli.
     * @return array{result: array<string, mixed>, source: array<string, mixed>, partial: bool}
     *         Data, popis zdroje a příznak neúplnosti.
     * @throws TransportException 'invalid_configuration' (500) při cyklickém
     *                            přesměrování, 'missing_service_date',
     *                            'not_found' (404), 'schedule_unavailable' (503),
     *                            'unsupported_capability' (422) nebo
     *                            'source_unavailable' (503), pokud živá data
     *                            nejsou k dispozici.
     */
    public function resource(string $operation, string $id, array $input = [], int $depth = 0): array
    {
        if ($depth > 2) {
            throw new TransportException('invalid_configuration', 'Cyclic provider mapping.', 500);
        }
        $kind = in_array($operation, ['stop','departures'], true) ? 'stop' : 'trip';
        $ref = ResourceIdCodec::decode($id, $this->repository->tenant, $kind);
        $input = array_merge($input, $ref);
        if ($kind === 'trip' && !$ref['date']) {
            throw new TransportException('missing_service_date', 'Trip ID must identify a service day.');
        }
        $provider = $this->registry->get($ref['provider']);
        $config = $provider->definition()->config;
        if ($operation === 'realtime' && isset($config['source_provider'],$config['otp_feed_id'])) {
            $prefix = $config['otp_feed_id'].':';
            if (!str_starts_with($ref['external'], $prefix)) {
                throw new TransportException('not_found', 'Trip is outside the configured feed.', 404);
            }
            $mapped = ResourceIdCodec::encode($this->repository->tenant, $config['source_provider'], 'trip', substr($ref['external'], strlen($prefix)), $ref['date']);
            return $this->resource('realtime', $mapped, [], $depth + 1);
        }
        $local = $kind === 'stop' ? $this->repository->stop($ref['provider'], $ref['external']) : $this->repository->trip($ref['provider'], $ref['external'], $ref['date']);
        if ($operation === 'realtime' && $local && !$local['frequency_based']) {
            $input['expected_start'] = $local['stops'][0]['scheduled_arrival'] ?? null;
        }
        if ($provider instanceof ResourceProvider && in_array($operation, $provider->capabilities(), true) && ($config['graph_ready'] ?? true)) {
            if ($this->repository->acquireProvider($ref['provider'], (int)($provider->definition()->config['min_interval_ms'] ?? 0))) {
                $result = $this->http->sendAll(['resource' => $provider->resourceRequest($operation, $input)])['resource'];
                try {
                    $data = $provider->resourceResult($operation, $result, $input);
                    $this->repository->providerSuccess($ref['provider']);
                    return ['result' => $data,'source' => ['provider' => $ref['provider'],'mode' => $provider->definition()->adapter === 'otp_transmodel' ? 'schedule' : 'live','fetched_at' => gmdate(DATE_RFC3339)],'partial' => false];
                } catch (\Throwable $e) {
                    if ($e instanceof TransportException && $e->status === 404) {
                        throw $e;
                    }
                    $this->repository->providerFailure($ref['provider'], $result->retryAfter);
                }
            }
            if (in_array($operation, ['stop','trip'], true) && $local) {
                return ['result' => $local,'source' => ['provider' => $ref['provider'],'mode' => 'fallback','realtime' => false],'partial' => true];
            }
            if ($operation === 'departures' && isset($config['schedule_provider'],$config['otp_feed_id'])) {
                $fallback = $this->registry->get($config['schedule_provider']);
                if ($fallback->definition()->adapter !== 'otp_transmodel') {
                    throw new TransportException('invalid_configuration', 'Departure fallback must be a timetable planner.', 500);
                }
                $mapped = ResourceIdCodec::encode($this->repository->tenant, $config['schedule_provider'], 'stop', $config['otp_feed_id'].':'.$ref['external']);
                $data = $this->resource('departures', $mapped, $input, $depth + 1);
                $data['partial'] = true;
                $data['source']['mode'] = 'fallback';
                return $data;
            }
            throw new TransportException('source_unavailable', 'Requested live data are unavailable.', 503);
        }
        if (in_array($operation, ['stop','trip'], true) && $local) {
            return ['result' => $local,'source' => ['provider' => $ref['provider'],'mode' => 'schedule','realtime' => false],'partial' => false];
        }
        if (!($config['graph_ready'] ?? true)) {
            throw new TransportException('schedule_unavailable', 'No verified graph is active for this source.', 503);
        }
        throw new TransportException('unsupported_capability', 'This source does not provide the requested operation.', 422);
    }
    /**
     * Převede zvolené místo na souřadnice vhodné pro vyhledávání spojů.
     *
     * @param  array<string, mixed> $place Místo s `type` `coordinates`, nebo ID zastávky.
     * @return array<string, mixed>        Místo doplněné o souřadnice a rozložené ID.
     * @throws TransportException          Při neplatném ID či chybějících souřadnicích.
     */
    public function resolve(array $place): array
    {
        if ($place['type'] === 'coordinates') {
            return $place;
        }
        $ref = ResourceIdCodec::decode($place['id'], $this->repository->tenant, 'stop');
        $stop = $this->resource('stop', $place['id'])['result'];
        if (!is_numeric($stop['lat'] ?? null) || !is_numeric($stop['lon'] ?? null)) {
            throw new TransportException('missing_coordinates', 'Selected stop has no coordinates.');
        }
        return array_merge($place, $ref, ['lat' => (float)$stop['lat'],'lon' => (float)$stop['lon']]);
    }
}
