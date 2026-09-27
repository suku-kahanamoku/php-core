<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Contracts\{ResourceProvider};
use App\Modules\Transport\Repositories\TransportRepository;

final class ResourceService
{
    public function __construct(private readonly ProviderRegistry $registry, private readonly HttpClient $http, private readonly TransportRepository $repository)
    {
    }
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
