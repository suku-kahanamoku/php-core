<?php

declare(strict_types=1);

namespace App\Modules\Transport\Core;

use App\Modules\Transport\Model\JourneyQuery;

/** Revalidate after realtime enrichment. A late feeder never postpones the next vehicle. */
final class JourneyTimingService
{
    public static function update(array $journey, ?JourneyQuery $query = null): ?array
    {
        $legs = $journey['legs'] ?? [];
        if (!$legs) {
            return null;
        }
        $ready = null;
        $previousTransit = false;
        foreach ($legs as &$leg) {
            if (($leg['cancelled'] ?? false) === true) {
                return null;
            }
            $sd = strtotime($leg['scheduled_departure'] ?? '');
            $sa = strtotime($leg['scheduled_arrival'] ?? '');
            if ($sd === false || $sa === false || $sa < $sd) {
                return null;
            }
            $live = ($leg['realtime'] ?? false) === true;
            if (!$live) {
                $leg['expected_departure'] = null;
                $leg['expected_arrival'] = null;
            }
            $dep = $live && is_string($leg['expected_departure'] ?? null) ? strtotime($leg['expected_departure']) : $sd;
            $arr = $live && is_string($leg['expected_arrival'] ?? null) ? strtotime($leg['expected_arrival']) : $sa;
            if ($dep === false || $arr === false) {
                return null;
            }
            $walk = ($leg['mode'] ?? '') === 'walk';
            // If only a departure prediction is supplied, carry that delay conservatively
            // to the alighting stop, explicitly labelled as an estimate.
            if (!$walk && $live && empty($leg['expected_arrival']) && $dep > $sd) {
                $arr = $sa + ($dep - $sd);
                $leg['expected_arrival'] = gmdate(DATE_RFC3339, $arr);
                $leg['arrival_estimated'] = true;
            }
            if ($walk && $ready !== null) {
                $dep = max($sd, $ready);
                $arr = $dep + ($sa - $sd);
                if ($dep !== $sd) {
                    $leg['expected_departure'] = gmdate(DATE_RFC3339, $dep);
                    $leg['expected_arrival'] = gmdate(DATE_RFC3339, $arr);
                    $leg['realtime'] = true;
                    $leg['arrival_estimated'] = true;
                }
            } elseif ($ready !== null) {
                $minimum = max(0, min(1800, (int)($leg['min_transfer_seconds'] ?? ($previousTransit ? 60 : 0))));
                if ($dep < $ready + $minimum) {
                    return null;
                }
            }
            if ($arr < $dep) {
                return null;
            }
            if (!$walk && $live) {
                $leg['delay_seconds'] = max(0, $dep - $sd);
            }
            $ready = $arr;
            $previousTransit = !$walk;
        }
        unset($leg);
        $first = $legs[0];
        $last = $legs[count($legs) - 1];
        $start = strtotime($first['expected_departure'] ?? $first['scheduled_departure']);
        $end = strtotime($last['expected_arrival'] ?? $last['scheduled_arrival']);
        if ($query && (($query->arriveBy && $end > $query->time->getTimestamp()) || (!$query->arriveBy && $start < $query->time->getTimestamp()))) {
            return null;
        }
        $journey['legs'] = $legs;
        $journey['duration_seconds'] = $end - $start;
        return $journey;
    }
    public static function rank(array $journeys, JourneyQuery $query): array
    {
        $valid = [];
        foreach ($journeys as $journey) {
            $updated = self::update($journey, $query);
            if ($updated !== null) {
                $valid[] = $updated;
            }
        }
        usort($valid, static function ($a, $b) use ($query) {
            if ($query->arriveBy) {
                return strtotime($b['legs'][0]['expected_departure'] ?? $b['legs'][0]['scheduled_departure']) <=> strtotime($a['legs'][0]['expected_departure'] ?? $a['legs'][0]['scheduled_departure']);
            }
            $al = $a['legs'][count($a['legs']) - 1];
            $bl = $b['legs'][count($b['legs']) - 1];
            return strtotime($al['expected_arrival'] ?? $al['scheduled_arrival']) <=> strtotime($bl['expected_arrival'] ?? $bl['scheduled_arrival']);
        });
        return array_slice($valid, 0, $query->limit);
    }
}
