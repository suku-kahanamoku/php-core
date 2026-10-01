<?php

declare(strict_types=1);

namespace App\Modules\Transport\Tracking;

use App\Modules\Transport\Core\{ProviderRegistry,ResourceService};
use App\Modules\Transport\Model\{ResourceIdCodec,TransportException};

/** Tracking is live-only; unsupported sources never fall back to catalogue positions. */
final class TripTrackingService
{
    public function __construct(private readonly ProviderRegistry $registry, private readonly ResourceService $resources, private readonly string $tenant, private readonly array $env)
    {
    }
    private function supported(string $id): bool
    {
        $ref = ResourceIdCodec::decode($id, $this->tenant, 'trip');
        if (!$ref['date']) {
            throw new TransportException('missing_service_date', 'Trip service date is required.');
        }
        try {
            $ref = $this->registry->canonicalReference('realtime', $ref);
        } catch (TransportException $e) {
            if ($e->reason === 'unsupported_capability') {
                return false;
            } throw $e;
        }
        $p = $this->registry->get($ref['provider']);
        return $p->definition()->enabled('realtime') && in_array('realtime', $p->capabilities(), true);
    }
    public function session(string $id): array
    {
        if (!$this->supported($id)) {
            return ['status' => 'unsupported'];
        }
        $url = $this->env['TRANSPORT_TRACKING_WS_URL'] ?? '';
        if (!preg_match('~^(wss://[^/?#]+(?:/[^?#]*)?|ws://(?:127\.0\.0\.1|localhost):\d+(?:/[^?#]*)?)$~D', $url) || strlen($this->env['TRANSPORT_TRACKING_SECRET'] ?? '') < 32) {
            return ['status' => 'disabled'];
        }
        return ['status' => 'available','url' => $url] + (new TrackingTicketService($this->env['TRANSPORT_TRACKING_SECRET']))->issue($this->tenant, $id, time());
    }
    public function observation(string $id): array
    {
        if (!$this->supported($id)) {
            return TrackingObservationMapper::unavailable('unsupported');
        }
        try {
            return TrackingObservationMapper::map($this->resources->resource('realtime', $id)['result'], time());
        } catch (TransportException $e) {
            if (in_array($e->status, [404,422,429,502,503], true)) {
                return TrackingObservationMapper::unavailable();
            } throw $e;
        }
    }
}
