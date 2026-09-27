<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Contracts\{JourneySearchProvider};
use App\Modules\Transport\DTO\JourneyQuery;
use App\Modules\Transport\Repositories\TransportRepository;

final class JourneyService
{
    public function __construct(private readonly ProviderRegistry $registry, private readonly HttpClient $http, private readonly TransportRepository $repository, private readonly ResourceService $resources)
    {
    }
    public function search(JourneyQuery $query): array
    {
        $query = $query->withPlaces($this->resources->resolve($query->from), $this->resources->resolve($query->to));
        $candidates = [];
        foreach ($this->registry->all() as $code => $p) {
            if ($p instanceof JourneySearchProvider && $p->definition()->covers($query)) {
                $candidates[$code] = $p;
            }
        }
        if (!$candidates) {
            throw new TransportException('unsupported_coverage', 'No configured journey provider covers both destinations.', 422);
        }
        $deadline = microtime(true) + 8;
        $sources = [];
        $journeys = [];
        $failed = [];
        $success = [];
        foreach (['primary','fallback'] as $phase) {
            $selected = [];
            $requests = [];
            foreach ($candidates as $code => $provider) {
                $d = $provider->definition();
                if ($d->role !== $phase) {
                    continue;
                }
                if ($phase === 'fallback' && !array_intersect($d->fallbackFor, $failed)) {
                    continue;
                }
                $config = $d->config;
                $date = $query->time->setTimezone(new \DateTimeZone($config['timezone'] ?? 'UTC'))->format('Y-m-d');
                $ready = ($config['graph_ready'] ?? true) && (!isset($config['valid_from']) || $date >= $config['valid_from']) && (!isset($config['valid_until']) || $date <= $config['valid_until']);
                if (!$ready || !$this->repository->acquireProvider($code, (int)($config['min_interval_ms'] ?? 0))) {
                    $failed[] = $code;
                    $sources[] = ['provider' => $code,'status' => $ready ? 'temporarily_unavailable' : 'schedule_unavailable'];
                    continue;
                }
                $selected[$code] = $provider;
                $requests[$code] = $provider->searchRequest($query);
            }
            $budget = max(1, min($phase === 'primary' ? 5000 : 3000, (int)(($deadline - microtime(true)) * 1000)));
            foreach ($this->http->sendAll($requests, $budget) as $code => $response) {
                try {
                    $data = $selected[$code]->searchResult($response);
                    $candidateJourneys = [];
                    $config = $selected[$code]->definition()->config;
                    $mode = $phase === 'fallback' ? 'fallback' : ($selected[$code]->definition()->adapter === 'otp_transmodel' ? 'schedule' : 'live');
                    $source = ['provider' => $code,'status' => 'ok','mode' => $mode,'fetched_at' => gmdate(DATE_RFC3339),'graph_version' => $config['graph_version'] ?? null,'valid_until' => $config['valid_until'] ?? null,'attribution' => $config['attribution'] ?? null];
                    foreach ($data as $journey) {
                        $legs = $journey['legs'];
                        $first = $legs[0]['expected_departure'] ?? $legs[0]['scheduled_departure'];
                        $last = $legs[count($legs) - 1]['expected_arrival'] ?? $legs[count($legs) - 1]['scheduled_arrival'];
                        if (($query->arriveBy && strtotime($last) > $query->time->getTimestamp()) || (!$query->arriveBy && strtotime($first) < $query->time->getTimestamp()) || $journey['transfers'] > $query->maxTransfers) {
                            continue;
                        }
                        if (array_filter($legs, fn ($l) => $l['mode'] !== 'walk' && !in_array($l['mode'], $query->modes, true))) {
                            continue;
                        }
                        $journey['source'] = $source;
                        // Conservative deduplication: preserve alternatives if provider trip identities differ.
                        $signature = hash('sha256', json_encode(array_map(fn ($l) => [$l['trip_id'],$l['mode'],$l['from']['lat'],$l['from']['lon'],$l['to']['lat'],$l['to']['lon'],$l['scheduled_departure'],$l['scheduled_arrival']], $legs), JSON_THROW_ON_ERROR));
                        $candidateJourneys[$signature] ??= $journey;
                    }
                    $journeys += $candidateJourneys;
                    $this->repository->providerSuccess($code);
                    $success[] = $code;
                    $sources[] = $source;
                } catch (\Throwable) {
                    $this->repository->providerFailure($code, $response->retryAfter);
                    $failed[] = $code;
                    $sources[] = ['provider' => $code,'status' => 'unavailable'];
                }
            }
        }
        if (!$success) {
            throw new TransportException('sources_unavailable', 'Journey search is unavailable; no valid fallback is ready.', 503, ['sources' => $sources]);
        }
        $journeys = array_values($journeys);
        usort($journeys, static function ($a, $b) use ($query): int {
            if ($query->arriveBy) {
                return strtotime($b['legs'][0]['expected_departure'] ?? $b['legs'][0]['scheduled_departure']) <=> strtotime($a['legs'][0]['expected_departure'] ?? $a['legs'][0]['scheduled_departure']);
            }
            $al = $a['legs'][count($a['legs']) - 1];
            $bl = $b['legs'][count($b['legs']) - 1];
            return strtotime($al['expected_arrival'] ?? $al['scheduled_arrival']) <=> strtotime($bl['expected_arrival'] ?? $bl['scheduled_arrival']);
        });
        return ['journeys' => array_map(fn ($j) => $this->repository->cacheJourney($j), array_slice($journeys, 0, $query->limit)),
            'partial' => (bool)$failed,'sources' => $sources,'warnings' => $failed ? ['Some sources were unavailable; coverage may be incomplete.'] : []];
    }
}
