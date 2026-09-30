<?php

declare(strict_types=1);

namespace App\Modules\Transport\Core;

use App\Modules\Transport\Model\ResourceIdCodec;
use App\Modules\Transport\Model\TransportException;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Model\RequestBudget;
use App\Modules\Transport\Contracts\ResourceProvider;
use App\Modules\Transport\Model\JourneyQuery;
use App\Modules\Transport\Persistence\TransportRepository;

/** Resolves a fresh GPS fix to the closest public stop, without persisting the fix. */
final class NearestStopService
{
    public const RADIUS_METRES = 2000;

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly HttpClient $http,
        private readonly TransportRepository $repository,
    ) {}

    public function resolve(array $location, ?RequestBudget $budget = null): array
    {
        $result = $this->search($location, 1, budget: $budget);
        if (!$result['places']) { throw new TransportException('nearby_stop_not_found', 'No nearby stop is available.', 422); }
        $stop = $result['places'][0];
        $ref = ResourceIdCodec::decode($stop['id'], $this->repository->tenant, 'stop');
        return ['place'=>array_merge($stop, $ref, ['type'=>'stop']), 'partial'=>$result['partial'], 'sources'=>$result['sources']];
    }

    /** Online nearby catalogue for an optional stop choice, ordered by geographical distance. */
    public function search(array $location, int $limit = 20, ?string $country = null, ?string $city = null, ?RequestBudget $budget = null): array
    {
        JourneyQuery::integer($limit, 1, 50);
        JourneyQuery::assertFreshLocation($location);
        $budget ??= new RequestBudget();
        $result = (new ResourceSearchService($this->registry,$this->http,$this->repository))->search('nearby_stops',
            ['location'=>$location,'limit'=>50,'radius_m'=>self::RADIUS_METRES],$country,$city,$location,$budget);
        if (!$result['covered']) { throw new TransportException('unsupported_coverage','No nearby-stop source covers this location.',422); }
        $failed = $result['failed'];
        $sources = $result['sources'];
        $candidates = $result['rows'];
        // A successful empty response never activates the catalogue fallback.
        if ($failed) {
            foreach ($this->repository->nearbyStops($location, self::RADIUS_METRES, $failed) as $row) {
                $candidates[] = array_replace($row, ['source_mode'=>'fallback']);
            }
        }
        $ranked = [];
        foreach ($candidates as $stop) {
            if ($city !== null && PlaceSearchService::normalize((string)($stop['city'] ?? '')) !== PlaceSearchService::normalize($city)
                && !str_starts_with(PlaceSearchService::normalize((string)($stop['name'] ?? '')), PlaceSearchService::normalize($city).', ')) { continue; }
            if (!is_numeric($stop['lat'] ?? null) || !is_numeric($stop['lon'] ?? null)
                || !is_finite((float)$stop['lat']) || !is_finite((float)$stop['lon'])
                || abs((float)$stop['lat']) > 90 || abs((float)$stop['lon']) > 180) { continue; }
            $distance = self::distance($location, $stop);
            if ($distance <= self::RADIUS_METRES) { $ranked[] = [$distance, $stop['id'], $stop]; }
        }
        JourneyQuery::assertFreshLocation($location);
        $partial = (bool)array_filter($sources, static fn ($s)=>$s['status'] !== 'ok');
        if (!$ranked && $partial && !array_filter($sources, static fn ($s)=>$s['status'] === 'ok')) {
            throw new TransportException('sources_unavailable', 'No nearby stop is available.', 503);
        }
        usort($ranked, static fn ($a,$b)=>[$a[0],$a[1]] <=> [$b[0],$b[1]]);
        $places = [];
        foreach ($ranked as $row) { $places[$row[1]] ??= $row[2]; }
        return ['places'=>array_slice(array_values($places), 0, $limit), 'partial'=>$partial,'sources'=>$sources];
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
