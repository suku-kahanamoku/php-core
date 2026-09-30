<?php

declare(strict_types=1);

namespace App\Modules\Transport\Integrations\Golemio;

use App\Modules\Transport\Core\ProviderRegistry;
use App\Modules\Transport\Model\ResourceIdCodec;
use App\Modules\Transport\Model\TransportException;

use App\Modules\Http\Contracts\HttpClient;

/** Přidává ověřené PID predikce do živé odpovědi; GPS ani dotazy nezapisuje. */
final class PidJourneyEnrichmentService
{
    public function __construct(
        private readonly PidProvider $pid,
        private readonly ProviderRegistry $registry,
        private readonly HttpClient $http,
    ) {
    }

    /**
     * @param  list<array<string, mixed>> $journeys Seřazené itineráře.
     * @return list<array<string, mixed>>           Itineráře s ověřenými odjezdy.
     */
    public function enrich(array $journeys): array
    {
        $pid = $this->pid;
        if (!$journeys) { return $journeys; }
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
        if (!$matches || count($stopIds) > 8) { return $journeys; }
        $at = gmdate('Y-m-d\TH:i:s\Z', $earliest);
        $response = $this->http->sendAll(['pid-departures' => $pid->departuresBatchRequest(array_keys($stopIds), $at)], 1500)['pid-departures'];
        $departures = $pid->resourceResult('departures', $response, []);
        foreach ($departures as $departure) {
            if (!isset($departure['stop']['id'],$departure['external_trip_id'],$departure['scheduled_departure'])) {
                continue;
            }
            $stop = ResourceIdCodec::decode($departure['stop']['id'], $this->pid->definition()->tenant, 'stop');
            $scheduled = strtotime($departure['scheduled_departure']);
            if ($stop['provider'] !== $this->pid->definition()->code || $scheduled === false) {
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
                $journeys[$journeyIndex]['source']['realtime_provider'] = $this->pid->definition()->code;
            }
        }
        return $journeys;
    }

    private function pidId(?string $id, string $kind): ?string
    {
        if ($id === null) {
            return null;
        }
        try {
            $ref = ResourceIdCodec::decode($id, $this->pid->definition()->tenant, $kind);
            $ref = $this->registry->canonicalReference($kind, $ref);
        } catch (TransportException $e) {
            if (!in_array($e->status, [404,422], true)) { throw $e; }
            return null;
        }
        return $ref['provider'] === $this->pid->definition()->code ? $ref['external'] : null;
    }
}
