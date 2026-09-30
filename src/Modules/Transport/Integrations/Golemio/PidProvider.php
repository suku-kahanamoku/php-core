<?php

declare(strict_types=1);

namespace App\Modules\Transport\Integrations\Golemio;

use App\Modules\Transport\Model\UpstreamResponseMapper;

use App\Modules\Transport\Contracts\OnlineJourneySearchProvider;
use App\Modules\Transport\Contracts\ResourceProvider;
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Model\JourneyQuery;
use App\Modules\Transport\Model\ProviderDefinition;
use App\Modules\Http\{HttpRequest,HttpResponse};
use App\Modules\Transport\Model\ResourceIdCodec;
use App\Modules\Transport\Model\TransportException;
use App\Modules\Transport\Import\ServiceTimeService;

/**
 * Živý poskytovatel dat z PID (Golemio).
 *
 * Token se přidává ke každému požadavku hlavičkou `X-Access-Token`, takže
 * sdílený HTTP klient zůstává bez přihlašovacích údajů. Všechny odpovědi se
 * ověřují přes `UpstreamResponseMapper` a chybějící nebo neočekávaná data
 * končí chybou `invalid_upstream`.
 */
final class PidProvider implements ResourceProvider, OnlineJourneySearchProvider, \App\Modules\Transport\Contracts\ResourcePreparationProvider, \App\Modules\Transport\Contracts\ResourceMappingProvider, \App\Modules\Transport\Contracts\JourneyEnrichmentProvider
{
    /**
     * @param  ProviderDefinition $definition Definice poskytovatele okurku.
     * @param  string             $token      Přístupový token PID z prostředí.
     * @return void
     */
    public function __construct(private readonly ProviderDefinition $definition, private readonly string $token)
    {
    }

    /**
     * @return ProviderDefinition Definice poskytovatele.
     */
    public function definition(): ProviderDefinition
    {
        return $this->definition;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function prepareResource(string $operation, array $input, HttpClient $http): array
    {
        if ($operation !== 'realtime') { return $input; }
        $response = $http->sendAll(['trip'=>$this->resourceRequest('trip', $input)])['trip'];
        $trip = $this->resourceResult('trip', $response, $input);
        if ($trip['frequency_based'] || empty($trip['stops'][0]['scheduled_arrival']) && empty($trip['stops'][0]['scheduled_departure'])) {
            throw new TransportException('instance_unverified', 'Trip service day cannot be verified online.', 503);
        }
        $input['expected_start'] = $trip['stops'][0]['scheduled_arrival'] ?? $trip['stops'][0]['scheduled_departure'];
        return $input;
    }

    public function sourceReference(string $operation, array $reference): ?array { return null; }

    public function fallbackReference(string $operation, array $reference): ?array
    {
        $config = $this->definition->config;
        if ($operation !== 'departures' || !isset($config['schedule_provider'],$config['otp_feed_id'])) { return null; }
        return array_replace($reference, ['provider'=>$config['schedule_provider'], 'external'=>$config['otp_feed_id'].':'.$reference['external']]);
    }

    public function enrichJourneys(array $journeys, \App\Modules\Transport\Core\ProviderRegistry $registry, HttpClient $http): array
    {
        return (new PidJourneyEnrichmentService($this, $registry, $http))->enrich($journeys);
    }

    public function supportsQuery(JourneyQuery $query): bool
    {
        foreach ([$query->from, $query->to] as $place) {
            if (($place['type'] ?? null) !== 'stop' || ($place['provider'] ?? null) !== $this->definition->code) {
                return false;
            }
        }
        return true;
    }

    public function searchOnline(JourneyQuery $query, HttpClient $http, int $budgetMs): array
    {
        return (new PidOnlineJourneyService($this))->search($query, $http, $budgetMs);
    }

    /**
     * @return list<string> Podporované operace: `places`, `stop`, `trip`, `departures`, `realtime`.
     */
    public function capabilities(): array
    {
        return array_values(array_filter(['journeys','places','stop','trip','departures','realtime','enrich_journeys'],
            fn ($operation) => $operation !== 'places' || ($this->definition->config['places_enabled'] ?? true)));
    }
    /**
     * Sestaví požadavek na Golemio pro danou operaci.
     *
     * @param  string              $operation Jedna z podporovaných operací.
     * @param  array<string, mixed> $input     Vstup operace (dotaz, ID, čas, limit).
     * @return HttpRequest                    Požadavek s hlavičkou `X-Access-Token`.
     * @throws TransportException 'unsupported_capability' (422), pokud operace
     *                            PID neposkytuje.
     */
    public function resourceRequest(string $operation, array $input): HttpRequest
    {
        $path = match($operation) {
            'places' => '/v2/gtfs/stops?'.http_build_query(['names' => [$input['query']],'limit' => $input['limit']]),
            'stop' => '/v2/gtfs/stops/'.rawurlencode($input['external']),
            'trip' => '/v2/gtfs/trips/'.rawurlencode($input['external']).'?'.http_build_query(['date' => $input['date'],'includeStopTimes' => 'true','includeStops' => 'true','includeRoute' => 'true']),
            'departures' => '/v2/pid/departureboards?'.http_build_query(['ids' => [$input['external']],'timeFrom' => $input['at'],'limit' => $input['limit'],'mode' => 'departures']),
            'realtime' => '/v2/vehiclepositions/'.rawurlencode($input['external']),
            default => throw new TransportException('unsupported_capability', 'PID does not support this operation.', 422)
        };
        return new HttpRequest(rtrim($this->definition->config['url'], '/').$path, headers:['X-Access-Token: '.$this->token]);
    }
    /**
     * Jeden hromadný dotaz na odjezdy zastávek nalezených ve výsledcích cest.
     *
     * @param  list<string> $stopIds Externí GTFS ID zastávek (nejvýše 8).
     * @param  string       $at      Začátek sledovaného intervalu RFC3339.
     * @return HttpRequest           Autentizovaný požadavek s krátkým deadlinem.
     */
    public function departuresBatchRequest(array $stopIds, string $at): HttpRequest
    {
        if (!$stopIds || count($stopIds) > 8) {
            throw new TransportException('invalid_query', 'Invalid PID departure batch.', 422);
        }
        $path = '/v2/pid/departureboards?'.http_build_query([
            'ids' => array_values($stopIds),'timeFrom' => $at,'minutesBefore' => 5,
            'minutesAfter' => 180,'limit' => 100,'mode' => 'departures',
        ]);
        return new HttpRequest(rtrim($this->definition->config['url'], '/').$path,
            headers:['X-Access-Token: '.$this->token],timeoutMs:1500);
    }

    /**
     * Převede odpověď Golemio na jednotný tvar výsledku.
     *
     * U odjezdů se časy doplňují pouze tehdy, když je PID predikuje, jinak se
     * uvádí plánovaný čas a `realtime` je false. U polohy vozidla se navíc
     * ověřuje, že jízda odpovídá očekávanému dni služby.
     *
     * @param  string               $operation Provedená operace.
     * @param  HttpResponse         $result    Odpověď Golemio.
     * @param  array<string, mixed> $input     Vstup operace; u `realtime` i
     *                                        očekávaný čas první zastávky.
     * @return array<string, mixed>           Výsledek bez obálky podle typu operace.
     * @throws TransportException 'invalid_upstream' (502) při chybějících
     *                            datech, 'instance_unverified' (503), pokud
     *                            polohu nelze přiřadit k danému dni.
     */
    public function resourceResult(string $operation, HttpResponse $result, array $input): array
    {
        $data = UpstreamResponseMapper::json($result);
        if ($operation === 'places') {
            if (!isset($data['features']) || !is_array($data['features'])) {
                throw new TransportException('invalid_upstream', 'Missing stops.', 502);
            }
            return array_map(fn ($s) => $this->stop($s), $data['features']);
        }
        if ($operation === 'stop') {
            return $this->stop($data);
        }
        if ($operation === 'trip') {
            return $this->trip($data, (string)$input['external'], (string)$input['date']);
        }
        if ($operation === 'departures') {
            if (!isset($data['departures']) || !is_array($data['departures'])) {
                throw new TransportException('invalid_upstream', 'Missing departures.', 502);
            }
            $stops = [];
            foreach ($data['stops'] ?? [] as $stop) {
                $stops[$stop['stop_id']] = $stop;
            }
            return array_map(
                /**
                 * Převede jeden odjezd z Golemio na jednotný tvar a doplní zastávku
                 * z mapy zastávek, pokud ji odpověď neobsahuje.
                 *
                 * @param  array<string, mixed> $d Odjezd z pole `departures`.
                 * @return array<string, mixed>    Odjezd s plánovanými i očekávanými časy.
                 */
                function ($d) use ($stops) {
                $scheduled = $d['departure_timestamp']['scheduled'] ?? null;
                $live = (bool)($d['delay']['is_available'] ?? false);
                $s = $stops[$d['stop']['id']] ?? [];
                $stop = ['id' => $this->id('stop', $d['stop']['id']),'name' => $s['stop_name'] ?? null,
                    'lat' => $s['stop_lat'] ?? null,'lon' => $s['stop_lon'] ?? null,
                    'platform' => $d['stop']['platform_code'] ?? $s['platform_code'] ?? null,'timezone' => 'Europe/Prague'];
                // This endpoint does not expose service_date. Do not derive a trip instance from the stop's calendar date.
                return ['trip_id' => null,'external_trip_id' => $d['trip']['id'],'scheduled_departure' => $scheduled,
                    'expected_departure' => $live ? ($d['departure_timestamp']['predicted'] ?? null) : null,
                    'scheduled_arrival' => $d['arrival_timestamp']['scheduled'] ?? null,
                    'expected_arrival' => $live ? ($d['arrival_timestamp']['predicted'] ?? null) : null,'realtime' => $live,
                    'cancelled' => (bool)($d['trip']['is_canceled'] ?? false),'headsign' => $d['trip']['headsign'] ?? null,
                    'line' => ['id' => null,'name' => $d['route']['short_name'] ?? null,'code' => $d['route']['short_name'] ?? null,
                        'mode' => isset($d['route']['type']) ? \App\Modules\Transport\Import\Gtfs\GtfsImportService::mode((string)$d['route']['type']) : null],
                    'stop' => $stop];
                },
                $data['departures']);
        }
        $properties = $data['properties'] ?? null;
        if (!is_array($properties) || !isset($properties['last_position'])) {
            throw new TransportException('invalid_upstream', 'Missing vehicle position.', 502);
        }
        $last = $properties['last_position'];
        if (!is_array($last)) {
            throw new TransportException('invalid_upstream', 'Invalid vehicle position.', 502);
        }
        $observed = $last['origin_timestamp'] ?? null;
        $timestamp = is_string($observed) ? strtotime($observed) : false;
        $maxAge = max(1, min(30, (int)($this->definition->config['realtime_max_age'] ?? 30)));
        $fresh = $timestamp !== false && $timestamp <= time() + 5 && time() - $timestamp <= $maxAge;
        // A GTFS trip ID is a schedule ID. Match the reported vehicle to its online service-day start.
        $start = $properties['trip']['start_timestamp'] ?? null;
        if (empty($input['expected_start']) || !$start || strtotime($start) !== strtotime($input['expected_start'])) {
            throw new TransportException('instance_unverified', 'Vehicle position cannot be matched to this service day.', 503);
        }
        $geometry = $data['geometry'] ?? null;
        $coordinates = is_array($geometry) ? ($geometry['coordinates'] ?? null) : null;
        $validPoint = is_array($geometry) && ($geometry['type'] ?? null) === 'Point'
            && is_array($coordinates) && array_is_list($coordinates) && count($coordinates) === 2
            && is_numeric($coordinates[0]) && is_numeric($coordinates[1])
            && is_finite((float)$coordinates[0]) && is_finite((float)$coordinates[1])
            && abs((float)$coordinates[0]) <= 180 && abs((float)$coordinates[1]) <= 90;
        $live = $fresh && ($last['tracking'] ?? false) === true && $validPoint;
        // Never expose a last-known coordinate or movement telemetry as a usable position.
        return ['position' => $live ? $geometry : null,'observed_at' => $live ? $observed : null,'realtime' => $live,
            'stale' => !$fresh,'cancelled' => $live ? ($last['is_canceled'] ?? null) : null,
            'delay_seconds' => $live ? ($last['delay']['actual'] ?? null) : null,
            'bearing' => $live ? ($last['bearing'] ?? null) : null,'speed_kmh' => $live ? ($last['speed'] ?? null) : null];
    }
    /**
     * Převede online GTFS detail spoje; plánované časy nejsou GPS telemetrie.
     *
     * @param  array<string, mixed> $data     Odpověď Golemio trips/{id}.
     * @param  string               $external GTFS identifikátor spoje.
     * @param  string               $date     Provozní den.
     * @return array<string, mixed>           Detail v jednotném tvaru.
     */
    private function trip(array $data, string $external, string $date): array
    {
        if (($data['trip_id'] ?? null) !== $external || !isset($data['stop_times']) || !is_array($data['stop_times'])) {
            throw new TransportException('invalid_upstream', 'Invalid PID trip detail.', 502);
        }
        $times = $data['stop_times'];
        usort($times, static fn (array $a, array $b): int => ((int)($a['stop_sequence'] ?? 0)) <=> ((int)($b['stop_sequence'] ?? 0)));
        $stopDetails = [];
        foreach ($data['stops'] ?? [] as $detail) {
            $properties = $detail['properties'] ?? $detail;
            if (is_array($properties) && is_string($properties['stop_id'] ?? null)) {
                $stopDetails[$properties['stop_id']] = $detail;
            }
        }
        $stops = [];
        foreach ($times as $call) {
            if (!is_string($call['stop_id'] ?? null)) {
                throw new TransportException('invalid_upstream', 'PID trip stop is missing.', 502);
            }
            $stop = $call['stop'] ?? $stopDetails[$call['stop_id']] ?? [];
            $coordinates = $stop['geometry']['coordinates'] ?? [];
            $properties = $stop['properties'] ?? $stop;
            $clock = static function (mixed $value) use ($date): ?string {
                if (!is_string($value) || $value === '') {
                    return null;
                }
                return ServiceTimeService::instant($date, ServiceTimeService::seconds($value), 'Europe/Prague')->format(DATE_RFC3339);
            };
            $stops[] = ['stop' => ['id' => $this->id('stop', $call['stop_id']),
                'name' => $properties['stop_name'] ?? null,
                'lat' => isset($coordinates[1]) ? (float)$coordinates[1] : (isset($properties['stop_lat']) ? (float)$properties['stop_lat'] : null),
                'lon' => isset($coordinates[0]) ? (float)$coordinates[0] : (isset($properties['stop_lon']) ? (float)$properties['stop_lon'] : null),
                'platform' => $properties['platform_code'] ?? null,'timezone' => 'Europe/Prague'],
                'scheduled_arrival' => $clock($call['arrival_time'] ?? null),
                'scheduled_departure' => $clock($call['departure_time'] ?? null),
                'expected_arrival' => null,'expected_departure' => null,
                'realtime' => false,'cancelled' => null];
        }
        if (!$stops) {
            throw new TransportException('invalid_upstream', 'PID trip has no stops.', 502);
        }
        $route = $data['route'] ?? [];
        $mode = isset($route['route_type']) ? \App\Modules\Transport\Import\Gtfs\GtfsImportService::mode((string)$route['route_type']) : null;
        return ['id' => $this->id('trip', $external, $date),'service_date' => $date,
            'line' => ['id' => isset($data['route_id']) ? $this->id('line', (string)$data['route_id']) : null,
                'name' => $route['route_long_name'] ?? null,'code' => $route['route_short_name'] ?? null,'mode' => $mode],
            'stops' => $stops,'frequency_based' => false,'source_mode' => 'live'];
    }

    /**
     * Převede GeoJSON prvek na zastávku.
     *
     * @param  array<string, mixed> $feature Prvek z pole `features`.
     * @return array<string, mixed>         Zastávka s veřejným ID.
     * @throws TransportException 'invalid_upstream' (502), pokud chybí ID či
     *                            název nebo souřadnice nejsou dvojice.
     */
    private function stop(array $feature): array
    {
        $s = $feature['properties'] ?? [];
        $xy = $feature['geometry']['coordinates'] ?? [];
        if (!isset($s['stop_id'],$s['stop_name']) || count($xy) !== 2) {
            throw new TransportException('invalid_upstream', 'Invalid stop.', 502);
        }
        return ['id' => $this->id('stop', $s['stop_id']),'name' => $s['stop_name'],'lat' => $xy[1],'lon' => $xy[0],'platform' => $s['platform_code'] ?? null,'timezone' => 'Europe/Prague'];
    }
    /**
     * Zakóduje veřejné ID zdroje PID.
     *
     * @param  string      $kind     Druh zdroje (`stop` nebo `trip`).
     * @param  string      $external Externí ID u PID.
     * @param  string|null $date     Datum platnosti instance spoje, nebo null.
     * @return string                ID vhodné pro naše API.
     */
    private function id(string $kind, string $external, ?string $date = null): string
    {
        return ResourceIdCodec::encode($this->definition->tenant, $this->definition->code, $kind, $external, $date);
    }
}
