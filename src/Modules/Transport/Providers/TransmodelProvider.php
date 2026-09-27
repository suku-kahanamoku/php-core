<?php

declare(strict_types=1);

namespace App\Modules\Transport\Providers;

use App\Modules\Transport\Contracts\JourneySearchProvider;
use App\Modules\Transport\Contracts\ResourceProvider;
use App\Modules\Transport\DTO\JourneyQuery;
use App\Modules\Transport\DTO\ProviderDefinition;
use App\Modules\Http\HttpRequest;
use App\Modules\Http\HttpResponse;
use App\Modules\Transport\{GeometryMapper,ResourceIdCodec,TransportException};

/** Entur and OTP share a Transmodel contract, not their dataset or identifiers. */
final class TransmodelProvider implements JourneySearchProvider, ResourceProvider
{
    private const CALL = 'aimedArrivalTime expectedArrivalTime aimedDepartureTime expectedDepartureTime realtime cancellation date quay { id name latitude longitude publicCode timeZone } serviceJourney { id line { id publicCode name } }';
    public function __construct(private readonly ProviderDefinition $definition)
    {
    }
    public function definition(): ProviderDefinition
    {
        return $this->definition;
    }
    public function capabilities(): array
    {
        return array_merge(array_merge(['journeys','stop','departures','trip'], isset($this->definition->config['source_provider']) ? ['realtime'] : []), isset($this->definition->config['geocoder_url']) ? ['places'] : []);
    }
    private function request(string $query, array $variables): HttpRequest
    {
        $headers = [];
        if (isset($this->definition->config['client_name'])) {
            $headers[] = 'ET-Client-Name: '.$this->definition->config['client_name'];
        }
        return new HttpRequest($this->definition->config['url'], 'POST', $headers, ['query' => $query,'variables' => $variables]);
    }
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
        $modeMap = ['train' => 'rail','metro' => 'metro','cable_car' => 'cableway','gondola' => 'cableway','ferry' => 'water','airplane' => 'air'];
        $modes = array_values(array_unique(array_map(fn ($m) => $modeMap[$m] ?? $m, $query->modes)));
        // Transmodel uses the same mode for rail subcategories; filters are explicit.
        return $this->request($gql, ['from' => $this->location($query->from),'to' => $this->location($query->to),
            'date' => $query->time->format(DATE_RFC3339),'arrive' => $query->arriveBy,'limit' => $query->limit,'transfers' => $query->maxTransfers,
            'modes' => ['accessMode' => 'foot','egressMode' => 'foot','directMode' => 'foot','transportModes' => array_map(fn ($m) => ['transportMode' => $m], $modes)]]);
    }
    private function location(array $place): array
    {
        if (($place['provider'] ?? null) === $this->definition->code && isset($place['external'])) {
            return ['place' => $place['external']];
        }
        return ['coordinates' => ['latitude' => $place['lat'],'longitude' => $place['lon']]];
    }
    public function searchResult(HttpResponse $result): array
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
                foreach (['aimedStartTime','aimedEndTime','fromPlace','toPlace','mode'] as $key) {
                    if (!isset($leg[$key])) {
                        throw new TransportException('invalid_upstream', 'Incomplete journey leg.', 502);
                    }
                }
                $trip = $leg['serviceJourney']['id'] ?? null;
                $legs[] = ['mode' => self::mode($leg['mode']),'from' => $this->place($leg['fromPlace']),'to' => $this->place($leg['toPlace']),
                    'scheduled_departure' => $leg['aimedStartTime'],'scheduled_arrival' => $leg['aimedEndTime'],
                    'expected_departure' => ($leg['realtime'] ?? false) ? ($leg['expectedStartTime'] ?? null) : null,
                    'expected_arrival' => ($leg['realtime'] ?? false) ? ($leg['expectedEndTime'] ?? null) : null,
                    'realtime' => (bool)($leg['realtime'] ?? false),'cancelled' => (bool)(($leg['fromEstimatedCall']['cancellation'] ?? false) || ($leg['toEstimatedCall']['cancellation'] ?? false)),
                    'trip_id' => $trip && !empty($leg['serviceDate']) ? $this->id('trip', $trip, $leg['serviceDate']) : null,
                    'service_date' => $leg['serviceDate'] ?? null,'line' => $this->line($leg['line'] ?? null, self::mode($leg['mode'])),'operator' => $leg['operator'] ?? null,
                    'distance_m' => $leg['distance'] ?? null,'geometry' => !empty($leg['pointsOnLink']['points']) ? GeometryMapper::polyline($leg['pointsOnLink']['points']) : null];
            }
            $items[] = ['duration_seconds' => $pattern['duration'] ?? null,'transfers' => max(0, count(array_filter($legs, fn ($l) => $l['mode'] !== 'walk')) - 1),'legs' => $legs];
        }
        return $items;
    }
    public function resourceRequest(string $operation, array $input): HttpRequest
    {
        if ($operation === 'places' && isset($this->definition->config['geocoder_url'])) {
            return new HttpRequest($this->definition->config['geocoder_url'].'?'.http_build_query(['text' => $input['query'],'size' => $input['limit'],'layers' => 'venue','boundary.country' => $this->definition->config['geocoder_country'] ?? null]), headers:['ET-Client-Name: '.$this->definition->config['client_name']]);
        }
        $id = $input['external'];
        if ($operation === 'stop') {
            return $this->request('query($id:String!){stopPlace(id:$id){id name latitude longitude timeZone quays{id name latitude longitude publicCode timeZone}} quay(id:$id){id name latitude longitude publicCode timeZone}}', ['id' => $id]);
        }
        if ($operation === 'departures') {
            $selection = 'estimatedCalls(startTime:$date,numberOfDepartures:$limit,timeRange:86400){'.self::CALL.'}';
            return $this->request('query($id:String!,$date:DateTime!,$limit:Int!){stopPlace(id:$id){'.$selection.'} quay(id:$id){'.$selection.'}}', ['id' => $id,'date' => $input['at'],'limit' => $input['limit']]);
        }
        if ($operation === 'trip') {
            return $this->request('query($id:String!,$date:Date!){serviceJourney(id:$id){id line{id publicCode name} estimatedCalls(date:$date){'.self::CALL.'}}}', ['id' => $id,'date' => $input['date']]);
        }
        throw new TransportException('unsupported_capability', 'Provider does not support this operation.', 422);
    }
    public function resourceResult(string $operation, HttpResponse $result, array $input): array
    {
        $json = UpstreamResponseMapper::json($result);
        $data = $json['data'] ?? [];
        if ($operation === 'places') {
            if (!isset($json['features']) || !is_array($json['features'])) {
                throw new TransportException('invalid_upstream', 'Invalid place response.', 502);
            }
            $items = [];
            foreach ($json['features'] as $f) {
                $id = $f['properties']['id'] ?? null;
                $xy = $f['geometry']['coordinates'] ?? [];
                if (!$id || count($xy) !== 2 || !str_contains($id, ':StopPlace:')) {
                    continue;
                }
                $items[] = ['id' => $this->id('stop', $id),'name' => $f['properties']['name'] ?? '', 'lat' => $xy[1],'lon' => $xy[0],'timezone' => 'Europe/Oslo'];
            }
            return $items;
        }
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
            return array_map(fn ($c) => $this->call($c), $stop['estimatedCalls'] ?? []);
        }
        $trip = $data['serviceJourney'] ?? null;
        if (!$trip) {
            throw new TransportException('not_found', 'Trip not found.', 404);
        }
        return ['id' => $this->id('trip', $trip['id'], $input['date']),'service_date' => $input['date'],'line' => $this->line($trip['line']),'stops' => array_map(fn ($c) => $this->call($c), $trip['estimatedCalls'] ?? [])];
    }
    private function call(array $call): array
    {
        $realtime = (bool)($call['realtime'] ?? false);
        $trip = $call['serviceJourney']['id'] ?? null;
        return ['stop' => $this->stop($call['quay']),'scheduled_departure' => $call['aimedDepartureTime'] ?? null,'scheduled_arrival' => $call['aimedArrivalTime'] ?? null,
            'expected_departure' => $realtime ? ($call['expectedDepartureTime'] ?? null) : null,'expected_arrival' => $realtime ? ($call['expectedArrivalTime'] ?? null) : null,
            'realtime' => $realtime,'cancelled' => (bool)($call['cancellation'] ?? false),'line' => $this->line($call['serviceJourney']['line'] ?? null), 'headsign' => null, 'external_trip_id' => $trip,
            'trip_id' => $trip && isset($call['date']) ? $this->id('trip', $trip, $call['date']) : null];
    }
    private function line(?array $line, ?string $mode = null): ?array
    {
        if ($line === null) {
            return null;
        }
        return ['id' => isset($line['id']) ? $this->id('line', $line['id']) : null,
            'name' => $line['name'] ?? null,'code' => $line['publicCode'] ?? null,'mode' => $mode];
    }

    private function stop(array $s): array
    {
        return ['id' => $this->id('stop', $s['id']),'name' => $s['name'],'lat' => $s['latitude'],'lon' => $s['longitude'],'platform' => $s['publicCode'] ?? null,'timezone' => $s['timeZone'] ?? null];
    }
    private function place(array $s): array
    {
        return ['id' => isset($s['quay']['id']) ? $this->id('stop', $s['quay']['id']) : null,'name' => $s['name'],'lat' => $s['latitude'],'lon' => $s['longitude'],'platform' => $s['quay']['publicCode'] ?? null,'timezone' => $s['quay']['timeZone'] ?? null];
    }
    private function id(string $kind, string $external, ?string $date = null): string
    {
        return ResourceIdCodec::encode($this->definition->tenant, $this->definition->code, $kind, $external, $date);
    }
    private static function mode(string $mode): string
    {
        return ['foot' => 'walk','rail' => 'train','cableway' => 'cable_car','water' => 'ferry','air' => 'airplane'][$mode] ?? $mode;
    }
}
