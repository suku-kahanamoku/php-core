<?php

declare(strict_types=1);

namespace App\Modules\Transport\Providers;

use App\Modules\Transport\Contracts\ResourceProvider;
use App\Modules\Transport\DTO\ProviderDefinition;
use App\Modules\Http\{HttpRequest,HttpResponse};
use App\Modules\Transport\{ResourceIdCodec,TransportException};

/**
 * Živý poskytovatel dat z PID (Golemio).
 *
 * Token se přidává ke každému požadavku hlavičkou `X-Access-Token`, takže
 * sdílený HTTP klient zůstává bez přihlašovacích údajů. Všechny odpovědi se
 * ověřují přes `UpstreamResponseMapper` a chybějící nebo neočekávaná data
 * končí chybou `invalid_upstream`.
 */
final class PidProvider implements ResourceProvider
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

    /**
     * @return list<string> Podporované operace: `places`, `stop`, `departures`, `realtime`.
     */
    public function capabilities(): array
    {
        return ['places','stop','departures','realtime'];
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
            'departures' => '/v2/pid/departureboards?'.http_build_query(['ids' => [$input['external']],'timeFrom' => $input['at'],'limit' => $input['limit'],'mode' => 'departures']),
            'realtime' => '/v2/vehiclepositions/'.rawurlencode($input['external']),
            default => throw new TransportException('unsupported_capability', 'PID does not support this operation.', 422)
        };
        return new HttpRequest(rtrim($this->definition->config['url'], '/').$path, headers:['X-Access-Token: '.$this->token]);
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
                        'mode' => isset($d['route']['type']) ? \App\Modules\Transport\Import\GtfsImportService::mode((string)$d['route']['type']) : null],
                    'stop' => $stop];
                },
                $data['departures']);
        }
        $properties = $data['properties'] ?? null;
        if (!is_array($properties) || !isset($properties['last_position'])) {
            throw new TransportException('invalid_upstream', 'Missing vehicle position.', 502);
        }
        $last = $properties['last_position'];
        $observed = $last['origin_timestamp'] ?? null;
        $timestamp = is_string($observed) ? strtotime($observed) : false;
        $fresh = $timestamp !== false && $timestamp <= time() + 30 && time() - $timestamp <= ($this->definition->config['realtime_max_age'] ?? 90);
        // Golemio trip IDs identify a schedule. Check the operating date using the imported first-stop time supplied by the service.
        $start = $properties['trip']['start_timestamp'] ?? null;
        if (empty($input['expected_start']) || !$start || strtotime($start) !== strtotime($input['expected_start'])) {
            throw new TransportException('instance_unverified', 'Vehicle position cannot be matched to this service day.', 503);
        }
        return ['position' => $data['geometry'] ?? null,'observed_at' => $observed,'realtime' => $fresh && ($last['tracking'] ?? false),
            'stale' => !$fresh,'cancelled' => $last['is_canceled'] ?? null,'delay_seconds' => $fresh ? ($last['delay']['actual'] ?? null) : null,
            'bearing' => $last['bearing'] ?? null,'speed_kmh' => $last['speed'] ?? null];
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
