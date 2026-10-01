<?php
declare(strict_types=1);
namespace App\Modules\Transport\Integrations\IdsJmk;

/** Join two public sources only by trip, dated schedule, vehicle label and geographically consistent measurements. */
final class IdsJmkObservationMapper
{
    public static function map(array $vehicles, array $traffic, array $trip, int $now): array
    {
        $empty = ['realtime' => false, 'position' => null, 'observed_at' => null, 'delay_seconds' => null];
        if ($now < $trip['start'] - 300 || $now > $trip['end'] + 7200) { return $empty; }
        $matches = [];
        foreach ($vehicles as $v) {
            if ($v['trip_id'] !== $trip['trip_id'] || $v['relationship'] !== 0 || !is_int($v['timestamp'])
                || $v['timestamp'] > $now + 5 || $now - $v['timestamp'] >= 30
                || !is_finite($v['lat']) || !is_finite($v['lon']) || abs($v['lat']) > 90 || abs($v['lon']) > 180
                || ($v['start_date'] !== null && $v['start_date'] !== str_replace('-', '', $trip['date']))) { continue; }
            // This producer omits start_date. Only accept the same local day, within the verified run window.
            if ($v['start_date'] === null && (new \DateTimeImmutable('@'.$v['timestamp']))->setTimezone(new \DateTimeZone('Europe/Prague'))->format('Y-m-d') !== $trip['date']) { continue; }
            if ($v['start_time'] !== null && $v['start_time'] !== (new \DateTimeImmutable('@'.$trip['start']))->setTimezone(new \DateTimeZone('Europe/Prague'))->format('H:i:s')) { continue; }
            $matches[] = $v;
        }
        if (count($matches) !== 1) { return $empty; }
        $v = $matches[0]; $delay = null; $cars = [];
        if (($traffic['lineId'] ?? null) === $trip['line']) {
            foreach ($traffic['cars'] ?? [] as $car) {
                if (!is_array($car) || ($car['lineId'] ?? null) !== $trip['line'] || ($car['routeId'] ?? null) !== $trip['number']
                    || (string)($car['carNum'] ?? '') !== $v['vehicle_label']
                    || !is_numeric($car['latitude'] ?? null) || !is_numeric($car['longitude'] ?? null)) { continue; }
                // Independent current feeds refresh at different instants. Identity is the exact dated run + vehicle,
                // not coordinate equality; reject spatially inconsistent records (>500m) rather than normal movement.
                $distance = hypot(((float)$car['latitude'] - $v['lat']) * 111320,
                    ((float)$car['longitude'] - $v['lon']) * 111320 * cos(deg2rad($v['lat'])));
                if (!is_finite($distance) || $distance > 500) { continue; }
                $cars[] = $car;
            }
        }
        if (count($cars) === 1 && is_numeric($cars[0]['delayInMins'] ?? null) && abs((float)$cars[0]['delayInMins']) <= 1440) {
            $delay = (int)round((float)$cars[0]['delayInMins'] * 60);
        }
        return ['realtime' => true, 'position' => ['type' => 'Point', 'coordinates' => [$v['lon'], $v['lat']]],
            'observed_at' => gmdate(DATE_RFC3339, $v['timestamp']), 'delay_seconds' => $delay, 'cancelled' => null];
    }
}
