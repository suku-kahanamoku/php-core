<?php

declare(strict_types=1);
namespace App\Modules\Transport\Core;

use App\Modules\Transport\Model\JourneyQuery;

/** Determine a common municipality from verified endpoint metadata and the returned itinerary. */
final class JourneyAreaService
{
    /** Missing municipality metadata is not evidence of an intercity journey. */
    public static function isIntercity(JourneyQuery $query, array $journeys): bool
    {
        if (!$journeys) { return false; }
        $cities = [];
        foreach ([$query->from, $query->to] as $stop) {
            if (is_string($stop['city'] ?? null) && trim($stop['city']) !== '') {
                $cities[PlaceSearchService::normalize($stop['city'])] = true;
            }
        }
        if (count($cities) > 1) { return true; }
        // Judge each itinerary independently, using only explicit municipality metadata.
        foreach ($journeys as $journey) {
            $visited = $cities;
            foreach ($journey['legs'] as $leg) {
                foreach (['from','to'] as $side) {
                    $city = $leg[$side]['city'] ?? null;
                    if (is_string($city) && trim($city) !== '') { $visited[PlaceSearchService::normalize($city)] = true; }
                }
            }
            if (count($visited) > 1) { return true; }
        }
        return false;
    }

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
