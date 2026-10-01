<?php

declare(strict_types=1);

namespace App\Modules\Transport\Core;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Contracts\{ResourceProvider, ResourcePreparationProvider, ResourceMappingProvider, ScheduleProvider};
use App\Modules\Transport\Model\{JourneyQuery, RequestBudget, ResourceIdCodec, TransportException};
use App\Modules\Transport\Persistence\TransportRepository;

/** Provider-neutral resources. Exact identities and prerequisites belong to integrations. */
final class ResourceService
{
    public function __construct(private readonly ProviderRegistry $registry, private readonly HttpClient $http, private readonly TransportRepository $repository) {}

    public function cities(array $query): array
    {
        return (new CityCatalogService($this->registry, $this->http, $this->repository))->search($query);
    }

    public function places(?string $query, int $limit, ?string $country, ?string $city = null, ?array $location = null, ?RequestBudget $budget = null): array
    {
        $budget ??= new RequestBudget();
        if ($query === null) {
            if ($location === null) {
                throw new TransportException('invalid_query', 'Nearby lookup requires a fresh location.');
            }
            return (new NearestStopService($this->registry, $this->http, $this->repository))->search($location, $limit, $country, $city, $budget);
        }
        if (mb_strlen(trim($query)) < 2 || mb_strlen($query) > 120) {
            throw new TransportException('invalid_query', 'Place query must contain 2 to 120 characters.');
        }
        if ($location !== null) { JourneyQuery::assertFreshLocation($location); }
        $rankingLocation = $city === null ? $location : null;
        $input = ['query' => $query, 'limit' => $limit, 'city' => $city, 'location' => $rankingLocation];
        // For a named country, GPS ranks matches; it must not exclude distant city sources.
        $result = (new ResourceSearchService($this->registry, $this->http, $this->repository))->search('places', $input, $country, $city, $country === null ? $location : null, $budget);
        $items = array_column($result['rows'], null, 'id');
        if ($result['failed']) {
            foreach ($this->repository->places($query, $limit, $country, $result['failed'], $city, $rankingLocation) as $item) {
                $items[$item['id']] ??= array_replace($item, ['source_mode' => 'fallback']);
            }
        }
        $partial = (bool)array_filter($result['sources'], fn($s) => $s['status'] !== 'ok');
        if (!$items && $partial && !array_filter($result['sources'], fn($s) => $s['status'] === 'ok')) {
            throw new TransportException('sources_unavailable', 'Place sources are unavailable.', 503, ['sources' => $result['sources']]);
        }
        return ['places' => PlaceSearchService::rank(array_values($items), $query, $limit, $rankingLocation), 'partial' => $partial, 'sources' => $result['sources']];
    }

    public function resource(string $operation, string $id, array $input = [], int $depth = 0, ?RequestBudget $budget = null, bool $scheduleOnly = false): array
    {
        $budget ??= new RequestBudget();
        if ($depth > 8) {
            throw new TransportException('invalid_configuration', 'Cyclic provider mapping.', 500);
        }
        $kind = in_array($operation, ['stop', 'departures'], true) ? 'stop' : 'trip';
        $ref = ResourceIdCodec::decode($id, $this->repository->tenant, $kind);
        if ($kind === 'trip' && !$ref['date']) {
            throw new TransportException('missing_service_date', 'Trip ID must identify a service day.');
        }
        if (!$scheduleOnly) {
            $mapped = $this->registry->canonicalReference($operation, $ref);
            if ($mapped !== $ref) {
                return $this->resource($operation, ResourceIdCodec::encode($this->repository->tenant, $mapped['provider'], $kind, $mapped['external'], $mapped['date']), $input, $depth + 1, $budget);
            }
        }
        $provider = $this->registry->get($ref['provider']);
        $config = $provider->definition()->config;
        if (!$provider instanceof ResourceProvider || !$provider->definition()->enabled($operation) || !in_array($operation, $provider->capabilities(), true)) {
            throw new TransportException('unsupported_capability', 'This source does not provide the requested operation.', 422);
        }
        if (!($config['graph_ready'] ?? true)) {
            throw new TransportException('schedule_unavailable', 'No verified graph is active for this source.', 503);
        }
        $input = array_merge($input, $ref);
        $result = (new ProviderExecutionService($this->http, $this->repository))->run([$ref['provider'] => $provider], static function ($p, HttpClient $http) use ($operation, $input): array {
            if ($p instanceof ResourcePreparationProvider) {
                $input = $p->prepareResource($operation, $input, $http);
            }
            $response = $http->sendAll(['resource' => $p->resourceRequest($operation, $input)])['resource'];
            return $p->resourceResult($operation, $response, $input);
        }, $budget)[$ref['provider']];
        if ($result->succeeded()) {
            return ['result' => $result->data, 'source' => ['provider' => $ref['provider'], 'mode' => $provider instanceof ScheduleProvider ? 'schedule' : 'live', 'fetched_at' => gmdate(DATE_RFC3339)], 'partial' => false];
        }
        if ($result->status === 'configuration_error' || $result->status === 'not_found') {
            throw $result->error;
        }
        if (!$result->allowsFallback()) {
            throw new TransportException('source_unavailable', 'Provider quota or request budget exhausted.', 503);
        }
        if (in_array($operation, ['stop', 'trip'], true)) {
            $local = $kind === 'stop' ? $this->repository->stop($ref['provider'], $ref['external']) : $this->repository->trip($ref['provider'], $ref['external'], $ref['date']);
            if ($local) {
                return ['result' => $local, 'source' => ['provider' => $ref['provider'], 'mode' => 'fallback', 'realtime' => false, 'snapshot_version' => $local['snapshot_version'] ?? null, 'snapshot_at' => $local['snapshot_at'] ?? null], 'partial' => true];
            }
        }
        if (!$scheduleOnly && $provider instanceof ResourceMappingProvider && ($mapped = $provider->fallbackReference($operation, $ref)) !== null) {
            if (($mapped['date'] ?? null) !== $ref['date']) {
                throw new \LogicException('Fallback mapping changed the service day.');
            }
            $fallback = $this->registry->get($mapped['provider']);
            if (
                !$fallback instanceof ScheduleProvider || $fallback->definition()->roleFor($operation) !== 'fallback'
                || !in_array($ref['provider'], $fallback->definition()->fallbackFor($operation), true)
            ) {
                throw new TransportException('invalid_configuration', 'Resource fallback must reference a configured schedule backup.', 500);
            }
            $data = $this->resource($operation, ResourceIdCodec::encode($this->repository->tenant, $mapped['provider'], $kind, $mapped['external'], $mapped['date']), $input, $depth + 1, $budget, true);
            $data['partial'] = true;
            $data['source']['mode'] = 'fallback';
            return $data;
        }
        throw new TransportException('source_unavailable', 'Requested live data are unavailable.', 503);
    }

    public function resolve(array $place, ?RequestBudget $budget = null): array
    {
        if (in_array($place['type'], ['coordinates', 'current_location'], true)) {
            JourneyQuery::assertFreshLocation($place);
            return $place;
        }
        $requested = ResourceIdCodec::decode($place['id'], $this->repository->tenant, 'stop');
        $stop = $this->resource('stop', $place['id'], budget: $budget)['result'];
        $actual = ResourceIdCodec::decode((string)($stop['id'] ?? ''), $this->repository->tenant, 'stop');
        $expected = $this->registry->canonicalReference('stop', $requested);
        if ($actual['provider'] !== $expected['provider'] || $actual['external'] !== $expected['external']) {
            throw new TransportException('invalid_upstream', 'Resolved stop identity does not match the request.', 502);
        }
        if ((!is_numeric($stop['lat'] ?? null) || !is_numeric($stop['lon'] ?? null)) && !($this->registry->get($actual['provider'])->definition()->config['native_stop_search'] ?? false)) {
            throw new TransportException('missing_coordinates', 'Selected stop has no coordinates.');
        }
        return array_merge($place, $actual, ['id' => $stop['id'], 'name' => $stop['name'], 'city' => $stop['city'] ?? null, 'lat' => isset($stop['lat']) ? (float)$stop['lat'] : null, 'lon' => isset($stop['lon']) ? (float)$stop['lon'] : null]);
    }
}
