<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Contracts\ResourceProvider;
use App\Modules\Transport\DTO\JourneyQuery;
use App\Modules\Transport\Repositories\TransportRepository;

/** Resolves a fresh GPS fix to the closest public stop, without persisting the fix. */
final class NearestStopService
{
    public const RADIUS_METRES = 2000;

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly HttpClient $http,
        private readonly TransportRepository $repository,
    ) {}

    public function resolve(array $location): array
    {
        JourneyQuery::assertFreshLocation($location);
        $providers = (new ProviderSelectionService($this->registry))->select('nearby_stops', location: $location);
        if (!$providers) { throw new TransportException('unsupported_coverage', 'No nearby-stop source covers this location.', 422); }
        $requests = $failed = $sources = $candidates = [];
        $input = ['location'=>$location,'limit'=>50,'radius_m'=>self::RADIUS_METRES];
        foreach ($providers as $code=>$provider) {
            if (!$provider instanceof ResourceProvider || $provider->definition()->role !== 'primary') { continue; }
            if (!$this->repository->acquireProviderAfterInterval($code, (int)($provider->definition()->config['min_interval_ms'] ?? 0))) {
                $sources[] = ['provider'=>$code,'status'=>'unavailable'];
                if ($this->repository->providerOutage($code)) { $failed[] = $code; }
                continue;
            }
            $requests[$code] = $provider->resourceRequest('nearby_stops', $input);
        }
        foreach ($this->http->sendAll($requests, 5000) as $code=>$response) {
            try {
                $rows = $providers[$code]->resourceResult('nearby_stops', $response, $input);
                $valid = [];
                foreach ($rows as $row) {
                    $ref = ResourceIdCodec::decode((string)($row['id'] ?? ''), $this->repository->tenant, 'stop');
                    if ($ref['provider'] !== $code) { throw new TransportException('invalid_upstream', 'Invalid stop source.', 502); }
                    $valid[] = $row + ['source_mode'=>'live'];
                }
                array_push($candidates, ...$valid);
                $this->repository->providerSuccess($code);
                $sources[] = ['provider'=>$code,'status'=>'ok'];
            } catch (\Throwable) {
                $failed[] = $code;
                $sources[] = ['provider'=>$code,'status'=>'unavailable'];
                $this->repository->providerFailure($code, $response->retryAfter);
            }
        }
        // A successful empty response never activates the catalogue fallback.
        if ($failed) {
            foreach ($this->repository->nearbyStops($location, self::RADIUS_METRES, $failed) as $row) {
                $candidates[] = array_replace($row, ['source_mode'=>'fallback']);
            }
        }
        $ranked = [];
        foreach ($candidates as $stop) {
            if (!is_numeric($stop['lat'] ?? null) || !is_numeric($stop['lon'] ?? null)
                || !is_finite((float)$stop['lat']) || !is_finite((float)$stop['lon'])
                || abs((float)$stop['lat']) > 90 || abs((float)$stop['lon']) > 180) { continue; }
            $distance = self::distance($location, $stop);
            if ($distance <= self::RADIUS_METRES) { $ranked[] = [$distance, $stop['id'], $stop]; }
        }
        JourneyQuery::assertFreshLocation($location);
        $partial = (bool)array_filter($sources, static fn ($s)=>$s['status'] !== 'ok');
        if (!$ranked) {
            throw new TransportException($partial ? 'sources_unavailable' : 'nearby_stop_not_found',
                'No nearby stop is available.', $partial ? 503 : 422);
        }
        usort($ranked, static fn ($a,$b)=>[$a[0],$a[1]] <=> [$b[0],$b[1]]);
        $stop = $ranked[0][2];
        $ref = ResourceIdCodec::decode($stop['id'], $this->repository->tenant, 'stop');
        return ['place'=>array_merge($stop, $ref, ['type'=>'stop']),
            'partial'=>$partial,'sources'=>$sources];
    }

    /** Geographical distance, not a claimed walkable path. */
    public static function distance(array $a, array $b): float
    {
        $lat = deg2rad((float)$b['lat']-(float)$a['lat']);
        $lon = deg2rad((float)$b['lon']-(float)$a['lon']);
        $h = sin($lat/2)**2 + cos(deg2rad((float)$a['lat'])) * cos(deg2rad((float)$b['lat'])) * sin($lon/2)**2;
        return 6371000 * 2 * asin(sqrt(max(0, min(1, $h))));
    }
}
