<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Providers\PidProvider;
use App\Modules\Transport\Repositories\TransportRepository;

/** Přidává ověřené PID predikce do živé odpovědi; GPS ani dotazy nezapisuje. */
final class PidJourneyEnrichmentService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly HttpClient $http,
        private readonly TransportRepository $repository,
    ) {
    }

    /**
     * @param  list<array<string, mixed>> $journeys Seřazené itineráře.
     * @return list<array<string, mixed>>           Itineráře s ověřenými odjezdy.
     */
    public function enrich(array $journeys): array
    {
        $pid = $this->registry->all()['pid'] ?? null;
        if (!$pid instanceof PidProvider || !$journeys) {
            return $journeys;
        }
        $matches = [];
        $stopIds = [];
        $earliest = null;
        foreach ($journeys as $journeyIndex => $journey) {
            foreach ($journey['legs'] as $legIndex => $leg) {
                $trip = $this->pidId($leg['trip_id'] ?? null, 'trip');
                $stop = $this->pidId($leg['from']['id'] ?? null, 'stop');
                $scheduled = isset($leg['scheduled_departure']) ? strtotime($leg['scheduled_departure']) : false;
                if ($trip === null || $stop === null || $scheduled === false) {
                    continue;
                }
                $key = $stop.'|'.$trip.'|'.$scheduled;
                $matches[$key][] = [$journeyIndex,$legIndex];
                $stopIds[$stop] = true;
                $earliest = $earliest === null ? $scheduled : min($earliest, $scheduled);
            }
        }
        if (!$matches || count($stopIds) > 8 || !$this->repository->acquireProviderAfterInterval('pid', (int)($pid->definition()->config['min_interval_ms'] ?? 0))) {
            return $journeys;
        }
        try {
            $at = gmdate('Y-m-d\TH:i:s\Z', $earliest);
            $response = $this->http->sendAll(['pid-departures' => $pid->departuresBatchRequest(array_keys($stopIds), $at)], 1500)['pid-departures'];
            $departures = $pid->resourceResult('departures', $response, []);
            foreach ($departures as $departure) {
                if (!isset($departure['stop']['id'],$departure['external_trip_id'],$departure['scheduled_departure'])) {
                    continue;
                }
                $stop = ResourceIdCodec::decode($departure['stop']['id'], $this->repository->tenant, 'stop');
                $scheduled = strtotime($departure['scheduled_departure']);
                if ($stop['provider'] !== 'pid' || $scheduled === false) {
                    continue;
                }
                $key = $stop['external'].'|'.$departure['external_trip_id'].'|'.$scheduled;
                foreach ($matches[$key] ?? [] as [$journeyIndex,$legIndex]) {
                    if ($departure['expected_departure'] !== null) {
                        $journeys[$journeyIndex]['legs'][$legIndex]['expected_departure'] = $departure['expected_departure'];
                        $journeys[$journeyIndex]['legs'][$legIndex]['realtime'] = true;
                    }
                    if ($departure['cancelled']) {
                        $journeys[$journeyIndex]['legs'][$legIndex]['cancelled'] = true;
                    }
                    $journeys[$journeyIndex]['source']['realtime_provider'] = 'pid';
                }
            }
            $this->repository->providerSuccess('pid');
        } catch (\Throwable) {
            $this->repository->providerFailure('pid');
            // A failed enrichment never turns a valid planned itinerary into an outage.
        }
        return $journeys;
    }

    private function pidId(?string $id, string $kind): ?string
    {
        if ($id === null) {
            return null;
        }
        try {
            $ref = ResourceIdCodec::decode($id, $this->repository->tenant, $kind);
        } catch (TransportException) {
            return null;
        }
        if ($ref['provider'] === 'pid') {
            return $ref['external'];
        }
        $source = $this->registry->all()[$ref['provider']] ?? null;
        $config = $source?->definition()->config ?? [];
        $prefix = ($config['otp_feed_id'] ?? '').':';
        if (($config['source_provider'] ?? null) !== 'pid' || !str_starts_with($ref['external'], $prefix)) {
            return null;
        }
        return substr($ref['external'], strlen($prefix));
    }
}
