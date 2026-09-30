<?php

declare(strict_types=1);

namespace App\Modules\Transport\Providers;

use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Transport\Contracts\{JourneySearchProvider, ResourceProvider};
use App\Modules\Transport\DTO\{JourneyQuery, ProviderDefinition};
use App\Modules\Transport\{PlaceSearchService, TransportException};

/** Public Spojenka REST API. All data are fetched online via the shared HttpModule. */
final class SpojenkaProvider implements JourneySearchProvider, ResourceProvider
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

    public function definition(): ProviderDefinition { return $this->definition; }
    public function capabilities(): array { return ['places','nearby_stops','stop','journeys','trip']; }

    private function request(string $path, ?array $body = null): HttpRequest
    {
        return new HttpRequest(rtrim($this->definition->config['url'], '/').$path,
            $body === null ? 'GET' : 'POST', ['Accept: application/json','User-Agent: TRAM/1.0'], $body);
    }

    public function resourceRequest(string $operation, array $input): HttpRequest
    {
        $path = match ($operation) {
            'places' => '/stations/search/name?'.http_build_query(['name' => trim((!empty($input['city']) && !str_contains(PlaceSearchService::normalize($input['query']), PlaceSearchService::normalize($input['city'])) ? $input['city'].' ' : '').PlaceSearchService::normalize($input['query'])), 'lat'=>$input['location']['lat']??null,'lon'=>$input['location']['lon']??null,'limit' => min(200, max(50, $input['limit'] * 5))]),
            'nearby_stops' => '/stations/search/name?'.http_build_query(['lat'=>$input['location']['lat'],'lon'=>$input['location']['lon'],'limit'=>$input['limit']]),
            'stop' => '/stations/'.rawurlencode($input['external']),
            'trip' => '/connections/'.rawurlencode($input['external']).'?'.http_build_query(['route' => 'true','trajectory' => 'false','atTime' => $input['date'].'T12:00:00'.(new \DateTimeImmutable($input['date'], new \DateTimeZone('Europe/Prague')))->format('P')]),
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
        if (in_array($operation, ['places','nearby_stops'], true)) {
            if (!array_is_list($data) || count($data) > 200) {
                throw new TransportException('invalid_upstream', 'Invalid station list.', 502);
            }
            if (!empty($input['city'])) {
                $city = PlaceSearchService::normalize($input['city']);
                $data = array_values(array_filter($data, static function ($station) use ($city) {
                    foreach ($station['placeHierarchy'] ?? [] as $place) {
                        if (($place['type'] ?? '') === 'MUNICIPALITY' && PlaceSearchService::normalize($place['name'] ?? '') === $city) { return true; }
                    }
                    return false;
                }));
            }
            return $operation === 'nearby_stops' ? array_map($this->mapper->station(...), $data)
                : PlaceSearchService::rank(array_map($this->mapper->station(...), $data), $input['query'], $input['limit']);
        }
        return match ($operation) {
            'stop' => $this->mapper->station($data),
            'trip' => $this->mapper->trip($data, $input['external'], $input['date']),
            default => throw new TransportException('unsupported_capability', 'Unsupported operation.', 422),
        };
    }

    public function searchRequest(JourneyQuery $query): HttpRequest
    {
        $location = fn (array $place) => ($place['provider'] ?? null) === $this->definition->code
            ? ['@type'=>'station','stationId'=>$place['external']]
            : ['@type'=>'coordinates','latitude'=>$place['lat'],'longitude'=>$place['lon'],'maxRadius'=>1000];
        $means = [];
        foreach ($query->modes as $mode) {
            $mapped = SpojenkaMapper::UPSTREAM_MODES[$mode] ?? null;
            if ($mapped !== null) { $means[$mapped] = $mapped; }
        }
        return $this->request('/journey/search', [
            'from'=>$location($query->from),'to'=>$location($query->to),
            'time'=>$query->time->format(DATE_RFC3339),
            'type'=>$query->arriveBy ? 'ARRIVAL' : 'DEPARTURE',
            'direction'=>$query->arriveBy ? 'BACKWARD' : 'FORWARD',
            'maxTransfers'=>$query->maxTransfers,'maxResults'=>min(10, $query->limit),
            'connectionFilter'=>['permittedMeans'=>array_values($means)],
            'allowManualTransfers'=>true,'calculateTariff'=>false,
        ]);
    }

    public function searchResult(HttpResponse $result, ?JourneyQuery $query = null): array
    {
        return $this->mapper->journeys(UpstreamResponseMapper::json($result), $query);
    }
}
