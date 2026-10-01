<?php

declare(strict_types=1);

namespace App\Modules\Transport\Protocols\Transmodel;

use App\Modules\Transport\Model\UpstreamResponseMapper;

use App\Modules\Transport\Contracts\JourneySearchProvider;
use App\Modules\Transport\Contracts\ResourceProvider;
use App\Modules\Transport\Model\JourneyQuery;
use App\Modules\Transport\Model\ProviderDefinition;
use App\Modules\Http\HttpRequest;
use App\Modules\Http\HttpResponse;
use App\Modules\Transport\Model\GeometryMapper;
use App\Modules\Transport\Model\ResourceIdCodec;
use App\Modules\Transport\Model\TransportException;

/** Shared Transmodel GraphQL contract. Integration-specific headers are injected. */
class TransmodelProvider implements JourneySearchProvider, ResourceProvider
{
    /** @var string Fragment GraphQL pro odhady volání na zastávce. */
    private const CALL = 'aimedArrivalTime expectedArrivalTime aimedDepartureTime expectedDepartureTime realtime cancellation date quay { id name latitude longitude publicCode timeZone } serviceJourney { id line { id publicCode name } }';

    /**
     * @param  ProviderDefinition $definition Definice poskytovatele okurku.
     * @return void
     */
    public function __construct(protected readonly ProviderDefinition $definition, private readonly array $headers = []) {}

    /**
     * @return ProviderDefinition Definice poskytovatele.
     */
    public function definition(): ProviderDefinition
    {
        return $this->definition;
    }

    /**
     * @return list<string> Podporované operace; `realtime` jen při mapovaném
     *                   živém zdroji, `places` jen při nakonfigurovaném geocoderu.
     */
    public function capabilities(): array
    {
        return ['journeys', 'stop', 'departures', 'trip'];
    }
    /**
     * Sestaví GraphQL požadavek na koncový bod poskytovatele.
     *
     * @param  string               $query     Text dotazu.
     * @param  array<string, mixed> $variables Proměnné dotazu.
     * @return HttpRequest                     POST s případnou hlavičkou klienta.
     */
    private function request(string $query, array $variables): HttpRequest
    {
        return new HttpRequest($this->definition->config['url'], 'POST', $this->headers, ['query' => $query, 'variables' => $variables]);
    }
    /**
     * Sestaví GraphQL dotaz na spojení.
     *
     * @param  JourneyQuery $query Normalizovaný dotaz na spojení.
     * @return HttpRequest       Požadavek s `query` a `variables`.
     */
    public function searchRequest(JourneyQuery $query): HttpRequest
    {
        $gql = <<<'GQL'
query TramSearch($from:Location!,$to:Location!,$date:DateTime!,$arrive:Boolean!,$limit:Int!,$transfers:Int!,$modes:Modes!) {
 trip(from:$from,to:$to,dateTime:$date,arriveBy:$arrive,numTripPatterns:$limit,maximumTransfers:$transfers,modes:$modes) {
  tripPatterns { duration legs {
   mode distance realtime serviceDate aimedStartTime expectedStartTime aimedEndTime expectedEndTime
   fromPlace { name latitude longitude quay { id publicCode timeZone } }
   toPlace { name latitude longitude quay { id publicCode timeZone } }
   line { id name publicCode } operator { id name } serviceJourney { id }
   fromEstimatedCall { cancellation } toEstimatedCall { cancellation }
   pointsOnLink { points }
  } }
 }
}
GQL;
        $modeMap = ['train' => 'rail', 'metro' => 'metro', 'cable_car' => 'cableway', 'gondola' => 'cableway', 'ferry' => 'water', 'airplane' => 'air'];
        $modes = array_values(array_unique(array_map(fn($m) => $modeMap[$m] ?? $m, $query->modes)));
        // Transmodel uses the same mode for rail subcategories; filters are explicit.
        return $this->request($gql, [
            'from' => $this->location($query->from),
            'to' => $this->location($query->to),
            'date' => $query->time->format(DATE_RFC3339),
            'arrive' => $query->arriveBy,
            'limit' => $query->limit,
            'transfers' => $query->maxTransfers,
            'modes' => ['accessMode' => 'foot', 'egressMode' => 'foot', 'directMode' => 'foot', 'transportModes' => array_map(fn($m) => ['transportMode' => $m], $modes)]
        ]);
    }
    /**
     * Převede místo na vstup Transmodelu: vlastní ID zastávky nebo souřadnice.
     *
     * ID se použije pouze tehdy, když patří tomuto poskytovateli; cizí místa
     * se hledají podle souřadnic.
     *
     * @param  array<string, mixed> $place Místo z dotazu.
     * @return array<string, mixed>        `place` nebo `coordinates`.
     */
    private function location(array $place): array
    {
        if (($place['provider'] ?? null) === $this->definition->code && isset($place['external'])) {
            return ['place' => $place['external']];
        }
        return ['coordinates' => ['latitude' => $place['lat'], 'longitude' => $place['lon']]];
    }
    /**
     * Převede odpověď s trip patterns na jednotná spojení.
     *
     * @param  HttpResponse $result    Odpověď GraphQL.
     * @return list<array<string, mixed>> Spojení s úseky, přestupy a geometrií.
     * @throws TransportException 'invalid_upstream' (502), pokud chybí vzory
     *                            trasy, úseky nebo povinné pole úseku.
     */
    public function searchResult(HttpResponse $result, ?JourneyQuery $query = null): array
    {
        $data = UpstreamResponseMapper::json($result);
        $patterns = $data['data']['trip']['tripPatterns'] ?? null;
        if (!is_array($patterns)) {
            throw new TransportException('invalid_upstream', 'Missing journey results.', 502);
        }
        $items = [];
        foreach ($patterns as $pattern) {
            if (empty($pattern['legs']) || !is_array($pattern['legs'])) {
                throw new TransportException('invalid_upstream', 'Missing journey legs.', 502);
            }
            $legs = [];
            foreach ($pattern['legs'] as $leg) {
                foreach (['aimedStartTime', 'aimedEndTime', 'fromPlace', 'toPlace', 'mode'] as $key) {
                    if (!isset($leg[$key])) {
                        throw new TransportException('invalid_upstream', 'Incomplete journey leg.', 502);
                    }
                }
                $trip = $leg['serviceJourney']['id'] ?? null;
                $legs[] = [
                    'mode' => self::mode($leg['mode']),
                    'from' => $this->place($leg['fromPlace']),
                    'to' => $this->place($leg['toPlace']),
                    'scheduled_departure' => $leg['aimedStartTime'],
                    'scheduled_arrival' => $leg['aimedEndTime'],
                    'expected_departure' => ($leg['realtime'] ?? false) ? ($leg['expectedStartTime'] ?? null) : null,
                    'expected_arrival' => ($leg['realtime'] ?? false) ? ($leg['expectedEndTime'] ?? null) : null,
                    'realtime' => (bool)($leg['realtime'] ?? false),
                    'cancelled' => (bool)(($leg['fromEstimatedCall']['cancellation'] ?? false) || ($leg['toEstimatedCall']['cancellation'] ?? false)),
                    'trip_id' => $trip && !empty($leg['serviceDate']) ? $this->id('trip', $trip, $leg['serviceDate']) : null,
                    'service_date' => $leg['serviceDate'] ?? null,
                    'line' => $this->line($leg['line'] ?? null, self::mode($leg['mode'])),
                    'operator' => $leg['operator'] ?? null,
                    'distance_m' => $leg['distance'] ?? null,
                    'geometry' => !empty($leg['pointsOnLink']['points']) ? GeometryMapper::polyline($leg['pointsOnLink']['points']) : null
                ];
            }
            $items[] = ['duration_seconds' => $pattern['duration'] ?? null, 'transfers' => max(0, count(array_filter($legs, fn($l) => $l['mode'] !== 'walk')) - 1), 'legs' => $legs];
        }
        return $items;
    }
    /**
     * Sestaví požadavek pro jednotlivé operace (místa, zastávka, odjezdy, spoj).
     *
     * @param  string               $operation Jedna z podporovaných operací.
     * @param  array<string, mixed> $input     Vstup operace (dotaz, ID, čas, limit).
     * @return HttpRequest                     Geokódovací REST volání nebo GraphQL dotaz.
     * @throws TransportException 'unsupported_capability' (422), pokud operace
     *                            poskytovatel nepodporuje.
     */
    public function resourceRequest(string $operation, array $input): HttpRequest
    {
        $id = $input['external'];
        if ($operation === 'stop') {
            return $this->request('query($id:String!){stopPlace(id:$id){id name latitude longitude timeZone quays{id name latitude longitude publicCode timeZone}} quay(id:$id){id name latitude longitude publicCode timeZone}}', ['id' => $id]);
        }
        if ($operation === 'departures') {
            $selection = 'estimatedCalls(startTime:$date,numberOfDepartures:$limit,timeRange:86400){' . self::CALL . '}';
            return $this->request('query($id:String!,$date:DateTime!,$limit:Int!){stopPlace(id:$id){' . $selection . '} quay(id:$id){' . $selection . '}}', ['id' => $id, 'date' => $input['at'], 'limit' => $input['limit']]);
        }
        if ($operation === 'trip') {
            return $this->request('query($id:String!,$date:Date!){serviceJourney(id:$id){id line{id publicCode name} estimatedCalls(date:$date){' . self::CALL . '}}}', ['id' => $id, 'date' => $input['date']]);
        }
        throw new TransportException('unsupported_capability', 'Provider does not support this operation.', 422);
    }
    /**
     * Převede odpověď na jednotný výsledek operace.
     *
     * Chybějící zastávka nebo spoj končí 404. Geokódování patří integraci,
     * protože není součástí společného Transmodel kontraktu.
     *
     * @param  string               $operation Provedená operace.
     * @param  HttpResponse         $result    Odpověď poskytovatele.
     * @param  array<string, mixed> $input     Vstup operace (u spoje datum).
     * @return array<string, mixed>           Místa, zastávka, odjezdy nebo spoj.
     * @throws TransportException 'invalid_upstream' (502) při chybné odpovědi
     *                            geokódování, 'not_found' (404), pokud zastávka
     *                            nebo spoj neexistuje.
     */
    public function resourceResult(string $operation, HttpResponse $result, array $input): array
    {
        $json = UpstreamResponseMapper::json($result);
        $data = $json['data'] ?? [];
        if ($operation === 'stop') {
            $stop = $data['stopPlace'] ?? $data['quay'] ?? null;
            if (!$stop) {
                throw new TransportException('not_found', 'Stop not found.', 404);
            }
            return $this->stop($stop);
        }
        if ($operation === 'departures') {
            $stop = $data['stopPlace'] ?? $data['quay'] ?? null;
            if ($stop === null) {
                throw new TransportException('not_found', 'Stop not found.', 404);
            }
            return array_map(fn($c) => $this->call($c), $stop['estimatedCalls'] ?? []);
        }
        $trip = $data['serviceJourney'] ?? null;
        if (!$trip) {
            throw new TransportException('not_found', 'Trip not found.', 404);
        }
        return ['id' => $this->id('trip', $trip['id'], $input['date']), 'service_date' => $input['date'], 'line' => $this->line($trip['line']), 'stops' => array_map(fn($c) => $this->call($c), $trip['estimatedCalls'] ?? [])];
    }
    /**
     * Převede jeden odhad volání na zastávce na jednotný tvar.
     *
     * @param  array<string, mixed> $call Prvek `estimatedCalls`.
     * @return array<string, mixed>      Volání se zastávkou, linkou a časy.
     */
    private function call(array $call): array
    {
        $realtime = (bool)($call['realtime'] ?? false);
        $trip = $call['serviceJourney']['id'] ?? null;
        return [
            'stop' => $this->stop($call['quay']),
            'scheduled_departure' => $call['aimedDepartureTime'] ?? null,
            'scheduled_arrival' => $call['aimedArrivalTime'] ?? null,
            'expected_departure' => $realtime ? ($call['expectedDepartureTime'] ?? null) : null,
            'expected_arrival' => $realtime ? ($call['expectedArrivalTime'] ?? null) : null,
            'realtime' => $realtime,
            'cancelled' => (bool)($call['cancellation'] ?? false),
            'line' => $this->line($call['serviceJourney']['line'] ?? null),
            'headsign' => null,
            'external_trip_id' => $trip,
            'trip_id' => $trip && isset($call['date']) ? $this->id('trip', $trip, $call['date']) : null
        ];
    }
    /**
     * Převede dopravní linku na jednotný tvar.
     *
     * @param  array<string, mixed>|null $line Linka z odpovědi.
     * @param  string|null               $mode Dopravní mód, pokud je znám.
     * @return array<string, mixed>|null        Linka s ID, názvem, kódem a módem.
     */
    private function line(?array $line, ?string $mode = null): ?array
    {
        if ($line === null) {
            return null;
        }
        return [
            'id' => isset($line['id']) ? $this->id('line', $line['id']) : null,
            'name' => $line['name'] ?? null,
            'code' => $line['publicCode'] ?? null,
            'mode' => $mode
        ];
    }

    /**
     * Převede quay (nástupiště) na jednotnou zastávku.
     *
     * @param  array<string, mixed> $s Prvek `quay` z odpovědi.
     * @return array<string, mixed>   Zastávka s veřejným ID.
     */
    private function stop(array $s): array
    {
        return ['id' => $this->id('stop', $s['id']), 'name' => $s['name'], 'lat' => $s['latitude'], 'lon' => $s['longitude'], 'platform' => $s['publicCode'] ?? null, 'timezone' => $s['timeZone'] ?? null];
    }
    /**
     * Převede místo z konce úseku na jednotný tvar se souřadnicemi.
     *
     * @param  array<string, mixed> $s Prvek `fromPlace` nebo `toPlace`.
     * @return array<string, mixed>   Místo s volitelným ID zastávky.
     */
    private function place(array $s): array
    {
        return ['id' => isset($s['quay']['id']) ? $this->id('stop', $s['quay']['id']) : null, 'name' => $s['name'], 'lat' => $s['latitude'], 'lon' => $s['longitude'], 'platform' => $s['quay']['publicCode'] ?? null, 'timezone' => $s['quay']['timeZone'] ?? null];
    }
    /**
     * Zakóduje veřejné ID zdroje Transmodelu.
     *
     * @param  string      $kind     Druh zdroje (`stop`, `trip`, `line`).
     * @param  string      $external ID v Transmodelu.
     * @param  string|null $date     Datum platnosti instance spoje, nebo null.
     * @return string                ID vhodné pro naše API.
     */
    protected function id(string $kind, string $external, ?string $date = null): string
    {
        return ResourceIdCodec::encode($this->definition->tenant, $this->definition->code, $kind, $external, $date);
    }
    /**
     * Převede mód Transmodelu na náš dopravní mód.
     *
     * @param  string $mode Mód z odpovědi (např. `rail`).
     * @return string       Mód v našem tvaru (`train`, `walk`, ...).
     */
    private static function mode(string $mode): string
    {
        return ['foot' => 'walk', 'rail' => 'train', 'cableway' => 'cable_car', 'water' => 'ferry', 'air' => 'airplane'][$mode] ?? $mode;
    }
}
