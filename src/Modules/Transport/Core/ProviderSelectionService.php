<?php

declare(strict_types=1);
namespace App\Modules\Transport\Core;

use App\Modules\Transport\Model\TransportException;

use App\Modules\Transport\Contracts\Provider;
use App\Modules\Transport\Model\JourneyQuery;

/** One selector for tenant, capability and country/city/geographic coverage. */
final class ProviderSelectionService
{
    public function __construct(private readonly ProviderRegistry $registry) {}

    /** Explicit city overrides GPS; explicit country limits the eligible regions first. */
    public function select(string $operation, ?string $country = null, ?string $city = null, ?array $location = null): array
    {
        if ($country !== null && !preg_match('/^[A-Z]{2}$/D', $country)) { throw new TransportException('invalid_country', 'Invalid country.'); }
        if ($city !== null && (mb_strlen(trim($city)) < 1 || mb_strlen($city) > 120)) { throw new TransportException('invalid_city', 'Invalid city.'); }
        if ($location !== null) { JourneyQuery::assertFreshLocation($location); }
        $usePoint = $city === null && $location !== null;
        if ($usePoint && $country !== null) {
            // Keep explicit foreign-country searches usable when the device is elsewhere.
            $usePoint = false;
            foreach ($this->registry->all() as $provider) {
                foreach ($provider->definition()->coverage as $region) {
                    if (($region['country'] ?? null) === $country && self::contains($region['bbox'] ?? [], $location)) { $usePoint = true; }
                }
            }
        }
        $selected = [];
        foreach ($this->registry->all() as $code=>$provider) {
            if (!$provider->definition()->enabled($operation) || !in_array($operation, $provider->capabilities(), true)) { continue; }
            foreach ($provider->definition()->coverage as $region) {
                if ($country !== null && ($region['country'] ?? null) !== $country) { continue; }
                $cities = $region['cities'] ?? (isset($region['city']) ? [$region['city']] : []);
                if ($city !== null && $cities && !in_array(PlaceSearchService::normalize($city), array_map(PlaceSearchService::normalize(...), $cities), true)) { continue; }
                if ($usePoint && !self::contains($region['bbox'] ?? [], $location)) { continue; }
                $selected[$code] = $provider;
                break;
            }
        }
        uasort($selected, static fn (Provider $a, Provider $b) => $a->definition()->priority($operation) <=> $b->definition()->priority($operation));
        return $selected;
    }

    /** Routing still requires a planner that covers both endpoints, not just the device location. */
    public function journeys(JourneyQuery $query): array
    {
        return array_filter($this->select('journeys'),
            static fn (Provider $provider) => $provider->definition()->covers($query));
    }

    public static function contains(array $bbox, array $point): bool
    {
        if (count($bbox) !== 4 || !isset($point['lat'],$point['lon'])) { return false; }
        [$w,$s,$e,$n] = $bbox;
        return $point['lat'] >= $s && $point['lat'] <= $n && ($w <= $e ? $point['lon'] >= $w && $point['lon'] <= $e : $point['lon'] >= $w || $point['lon'] <= $e);
    }
}
