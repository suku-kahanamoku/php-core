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
                foreach (['from'=>'departure', 'to'=>'arrival'] as $side=>$event) {
                    $stop = $this->pidId($leg[$side]['id'] ?? null, 'stop');
                    $scheduled = isset($leg['scheduled_'.$event]) ? strtotime($leg['scheduled_'.$event]) : false;
                    if ($trip === null || $stop === null || $scheduled === false) { continue; }
                    if (!isset($stopIds[$stop]) && count($stopIds) >= 8) { continue; }
                    $key = $stop.'|'.$trip.'|'.$event.'|'.$scheduled;
                    $matches[$key][] = [$journeyIndex,$legIndex,$event];
                    $stopIds[$stop] = true;
                    $earliest = $earliest === null ? $scheduled : min($earliest, $scheduled);
                }
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
            foreach (['departure','arrival'] as $event) {
                $scheduled = strtotime($departure['scheduled_'.$event] ?? '');
                if ($stop['provider'] !== $this->pid->definition()->code || $scheduled === false) { continue; }
                $key = $stop['external'].'|'.$departure['external_trip_id'].'|'.$event.'|'.$scheduled;
                foreach ($matches[$key] ?? [] as [$journeyIndex,$legIndex]) {
                    if (($departure['expected_'.$event] ?? null) !== null) {
                        $journeys[$journeyIndex]['legs'][$legIndex]['expected_'.$event] = $departure['expected_'.$event];
                        $journeys[$journeyIndex]['legs'][$legIndex]['realtime'] = true;
                    }
                    if ($departure['cancelled']) { $journeys[$journeyIndex]['legs'][$legIndex]['cancelled'] = true; }
                    $journeys[$journeyIndex]['source']['realtime_provider'] = $this->pid->definition()->code;
                }
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
