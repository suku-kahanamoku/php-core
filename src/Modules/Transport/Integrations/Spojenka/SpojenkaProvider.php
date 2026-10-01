<?php

declare(strict_types=1);

namespace App\Modules\Transport\Integrations\Spojenka;

use App\Modules\Transport\Model\UpstreamResponseMapper;

use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Transport\Contracts\JourneySearchProvider;
use App\Modules\Transport\Contracts\ResourceProvider;
use App\Modules\Transport\Model\JourneyQuery;
use App\Modules\Transport\Model\ProviderDefinition;
use App\Modules\Transport\Core\PlaceSearchService;
use App\Modules\Transport\Model\TransportException;

/** Public Spojenka REST API. All data are fetched online via the shared HttpModule. */
final class SpojenkaProvider implements JourneySearchProvider, ResourceProvider, \App\Modules\Transport\Contracts\ResourceEnrichmentProvider
{
    private readonly SpojenkaMapper $mapper;

    public function __construct(private readonly ProviderDefinition $definition)
    {
        $base = rtrim((string)($definition->config['url'] ?? ''), '/');
        if (!in_array($base, ['https://spojenka.d3s.mff.cuni.cz/api'], true)) {
            throw new TransportException('invalid_configuration', 'Unsupported Spojenka endpoint.', 500);
        }
        $this->mapper = new SpojenkaMapper($definition->tenant, $definition->code);
    }

    public function definition(): ProviderDefinition
    {
        return $this->definition;
    }
    public function capabilities(): array
    {
        return ['cities', 'places', 'nearby_stops', 'stop', 'journeys', 'trip'];
    }

    private function request(string $path, ?array $body = null): HttpRequest
    {
        return new HttpRequest(
            rtrim($this->definition->config['url'], '/') . $path,
            $body === null ? 'GET' : 'POST',
            ['Accept: application/json', 'User-Agent: TRAM/1.0'],
            $body
        );
    }

    public function resourceRequest(string $operation, array $input): HttpRequest
    {
        if ($operation === 'cities') {
            // Spojenka's municipality register currently covers CZ. The catalogue
            // endpoint needs a reference point even when returning all municipalities.
            // This is a fixed dataset reference, never a user's GPS location.
            if (($input['country'] ?? null) !== 'CZ') {
                throw new TransportException('unsupported_capability', 'This catalogue covers CZ only.', 422);
            }
            return new HttpRequest(rtrim($this->definition->config['url'], '/') . '/places/search?' . http_build_query([
                'lat' => 49.75,
                'lon' => 15.5,
                'limit' => 10000
            ]) . '&typeMask%5B%5D=MUNICIPALITY', headers: ['Accept' => 'application/json'], maxBytes: 12000000);
        }
        $path = match ($operation) {
            'places' => '/stations/search/name?' . http_build_query(['name' => trim((!empty($input['city']) && !str_contains(PlaceSearchService::normalize($input['query']), PlaceSearchService::normalize($input['city'])) ? $input['city'] . ' ' : '') . PlaceSearchService::normalize($input['query'])), 'lat' => $input['location']['lat'] ?? null, 'lon' => $input['location']['lon'] ?? null, 'limit' => min(200, max(50, $input['limit'] * 5))]),
            'nearby_stops' => '/stations/search/name?' . http_build_query(['lat' => $input['location']['lat'], 'lon' => $input['location']['lon'], 'limit' => $input['limit']]),
            'stop' => '/stations/' . rawurlencode($input['external']),
            'trip' => '/connections/' . rawurlencode($input['external']) . '?' . http_build_query(['route' => 'true', 'trajectory' => 'false', 'atTime' => $input['date'] . 'T12:00:00' . (new \DateTimeImmutable($input['date'], new \DateTimeZone('Europe/Prague')))->format('P')]),
            default => throw new TransportException('unsupported_capability', 'Operation is not available from Spojenka.', 422),
        };
        return $this->request($path);
    }

    public function resourceResult(string $operation, HttpResponse $result, array $input): array
    {
        if ($result->status === 404) {
            throw new TransportException('not_found', 'Source record not found.', 404);
        }
        $data = UpstreamResponseMapper::json($result);
        if ($operation === 'cities') {
            if (!array_is_list($data) || count($data) >= 10000) {
                throw new TransportException('invalid_upstream', 'Invalid or truncated municipality catalogue.', 502);
            }
            $rows = [];
            foreach ($data as $place) {
                if (
                    !is_array($place) || ($place['type'] ?? null) !== 'MUNICIPALITY' || !is_string($place['name'] ?? null) || trim($place['name']) === '' || mb_strlen($place['name']) > 120
                    || !is_string($place['id']['listId'] ?? null) || !is_string($place['id']['objectId'] ?? null)
                ) {
                    throw new TransportException('invalid_upstream', 'Invalid municipality.', 502);
                }
                $rows[] = ['id' => \App\Modules\Transport\Model\ResourceIdCodec::encode($this->definition->tenant, $this->definition->code, 'city', json_encode($place['id'], JSON_THROW_ON_ERROR)), 'name' => $place['name'], 'state' => 'CZ'];
            }
            return $rows;
        }
        if (in_array($operation, ['places', 'nearby_stops'], true)) {
            if (!array_is_list($data) || count($data) > 200) {
                throw new TransportException('invalid_upstream', 'Invalid station list.', 502);
            }
            if (!empty($input['city'])) {
                $city = PlaceSearchService::normalize($input['city']);
                $data = array_values(array_filter($data, static function ($station) use ($city) {
                    foreach ($station['placeHierarchy'] ?? [] as $place) {
                        if (($place['type'] ?? '') === 'MUNICIPALITY' && PlaceSearchService::normalize($place['name'] ?? '') === $city) {
                            return true;
                        }
                    }
                    return false;
                }));
            }
            return $operation === 'nearby_stops' ? array_map($this->mapper->station(...), $data)
                : PlaceSearchService::rank(array_map($this->mapper->station(...), $data), $input['query'], $input['limit'], empty($input['city']) ? ($input['location'] ?? null) : null);
        }
        return match ($operation) {
            'stop' => $this->mapper->station($data),
            'trip' => $this->mapper->trip($data, $input['external'], $input['date']),
            default => throw new TransportException('unsupported_capability', 'Unsupported operation.', 422),
        };
    }

    /** Resolve public stop coordinates online; no vehicle or user positions are persisted. */
    public function enrichResource(string $operation, array $result, array $input, \App\Modules\Http\Contracts\HttpClient $http): array
    {
        if ($operation !== 'trip') {
            return $result;
        }
        $requests = [];
        foreach ($result['stops'] as $call) {
            $stop = $call['stop'];
            if ($stop['lat'] !== null && $stop['lon'] !== null) {
                continue;
            }
            $ref = \App\Modules\Transport\Model\ResourceIdCodec::decode($stop['id'], $this->definition->tenant, 'stop');
            if ($ref['provider'] !== $this->definition->code || count($requests) >= 64) {
                continue;
            }
            $requests[$stop['id']] = $this->resourceRequest('stop', $ref);
        }
        if (!$requests) {
            return $result;
        }
        $resolved = [];
        try {
            $responses = $http->sendAll($requests);
        } catch (TransportException) {
            return $result;
        }
        foreach ($responses as $id => $response) {
            try {
                $stop = $this->mapper->station(UpstreamResponseMapper::json($response));
                if ($stop['id'] === $id) {
                    $resolved[$id] = $stop;
                }
            } catch (TransportException) { /* Timetable remains available if optional coordinates fail. */
            }
        }
        foreach ($result['stops'] as &$call) {
            $stop = $resolved[$call['stop']['id']] ?? null;
            if ($stop) {
                $call['stop']['lat'] = $stop['lat'];
                $call['stop']['lon'] = $stop['lon'];
            }
        }
        unset($call);
        return $result;
    }

    public function searchRequest(JourneyQuery $query): HttpRequest
    {
        $location = fn(array $place) => ($place['provider'] ?? null) === $this->definition->code
            ? ['@type' => 'station', 'stationId' => $place['external']]
            : ['@type' => 'coordinates', 'latitude' => $place['lat'], 'longitude' => $place['lon'], 'maxRadius' => 1000];
        $means = [];
        foreach ($query->modes as $mode) {
            $mapped = SpojenkaMapper::UPSTREAM_MODES[$mode] ?? null;
            if ($mapped !== null) {
                $means[$mapped] = $mapped;
            }
        }
        return $this->request('/journey/search', [
            'from' => $location($query->from),
            'to' => $location($query->to),
            'time' => $query->time->format(DATE_RFC3339),
            'type' => $query->arriveBy ? 'ARRIVAL' : 'DEPARTURE',
            'direction' => $query->arriveBy ? 'BACKWARD' : 'FORWARD',
            'maxTransfers' => $query->maxTransfers,
            'maxResults' => min(10, $query->limit),
            'connectionFilter' => ['permittedMeans' => array_values($means)],
            'allowManualTransfers' => true,
            'calculateTariff' => false,
        ]);
    }

    public function searchResult(HttpResponse $result, ?JourneyQuery $query = null): array
    {
        return $this->mapper->journeys(UpstreamResponseMapper::json($result), $query);
    }
}
