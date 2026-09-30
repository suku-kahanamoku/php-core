<?php

declare(strict_types=1);
namespace App\Modules\Transport;

use App\Modules\Transport\DTO\JourneyQuery;

/** Determine a common municipality from verified endpoint metadata and the returned itinerary. */
final class JourneyAreaService
{
    public static function city(JourneyQuery $query, array $journeys): ?string
    {
        $city = $query->from['city'] ?? null;
        if (!$journeys || !is_string($city) || $city === '' || $city !== ($query->to['city'] ?? null)) { return null; }
        $endpoints = array_filter([$query->from['id'] ?? null, $query->to['id'] ?? null]);
        foreach ($journeys as $journey) {
            foreach ($journey['legs'] as $leg) {
                foreach (['from','to'] as $side) {
                    $stop = $leg[$side];
                    if (in_array($stop['id'] ?? null, $endpoints, true) || ($stop['city'] ?? null) === $city) { continue; }
                    // Spojenka journey references carry municipality-qualified labels, not full place hierarchies.
                    if (!str_starts_with((string)($stop['name'] ?? ''), $city.', ')) { return null; }
                }
            }
        }
        return $city;
    }
}
