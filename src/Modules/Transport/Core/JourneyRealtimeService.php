<?php

declare(strict_types=1);

namespace App\Modules\Transport\Core;

use App\Modules\Transport\Model\{RequestBudget, ResourceIdCodec, TransportException};
use App\Modules\Transport\Tracking\TrackingObservationMapper;

/** Request-local enrichment. Exact resource identity, bounded work, no stored telemetry. */
final class JourneyRealtimeService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ResourceService $resources,
        private readonly string $tenant,
    ) {
    }

    public function enrich(array $journeys, RequestBudget $budget): array
    {
        $observations = [];
        $attempts = 0;
        foreach ($journeys as &$journey) {
            foreach ($journey['legs'] as &$leg) {
                $id = $leg['trip_id'] ?? null;
                if (!is_string($id) || ($journey['source']['mode'] ?? '') === 'fallback') {
                    continue;
                }
                if (!array_key_exists($id, $observations)) {
                    $observations[$id] = null;
                    if ($attempts >= 4 || $budget->remainingMs() < 150) {
                        continue;
                    }
                    try {
                        $ref = ResourceIdCodec::decode($id, $this->tenant, 'trip');
                        $this->registry->canonicalReference('realtime', $ref);
                        ++$attempts;
                        $raw = $this->resources->resource('realtime', $id, budget: $budget)['result'];
                        $observations[$id] = TrackingObservationMapper::map($raw, time());
                    } catch (TransportException $e) {
                        if ($e->status === 500) {
                            throw $e;
                        }
                    }
                }
                $live = $observations[$id];
                if (($live['status'] ?? '') !== 'live') {
                    continue;
                }
                if ($live['cancelled'] !== null) {
                    $leg['cancelled'] = $live['cancelled'];
                }
                $delay = $live['delay_seconds'];
                if ($delay === null) {
                    continue;
                }
                foreach (['departure', 'arrival'] as $event) {
                    // A stop-specific online prediction is more precise than a vehicle-delay estimate.
                    if (!empty($leg['expected_' . $event])) {
                        continue;
                    }
                    $scheduled = strtotime($leg['scheduled_' . $event] ?? '');
                    if ($scheduled === false || $scheduled + $delay < strtotime($live['observed_at'])) {
                        continue;
                    }
                    $leg['expected_' . $event] = gmdate(DATE_RFC3339, $scheduled + $delay);
                    $leg['realtime'] = true;
                    $leg['arrival_estimated'] = true;
                    $leg['prediction_valid_until'] = $live['valid_until'];
                }
                $leg['delay_seconds'] = max(0, $delay);
            }
            unset($leg);
        }
        unset($journey);
        return $journeys;
    }
}
