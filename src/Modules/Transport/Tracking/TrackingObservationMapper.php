<?php

declare(strict_types=1);

namespace App\Modules\Transport\Tracking;

/** A whitelist for ephemeral observations. Stale positions are removed, never replayed. */
final class TrackingObservationMapper
{
    public static function unavailable(string $status = 'unavailable'): array
    {
        return ['status' => $status,'position' => null,'observed_at' => null,'valid_until' => null,'delay_seconds' => null,'cancelled' => null];
    }
    public static function map(array $r, int $now): array
    {
        $observed = is_string($r['observed_at'] ?? null) ? strtotime($r['observed_at']) : false;
        $point = $r['position'] ?? null;
        $c = is_array($point) ? ($point['coordinates'] ?? null) : null;
        if (($r['realtime'] ?? false) !== true || $observed === false || $observed > $now + 5 || $now - $observed >= 30) {
            return self::unavailable('stale');
        }
        if (($point['type'] ?? null) !== 'Point' || !is_array($c) || count($c) !== 2 || !is_numeric($c[0]) || !is_numeric($c[1]) || !is_finite((float)$c[0]) || !is_finite((float)$c[1]) || abs((float)$c[0]) > 180 || abs((float)$c[1]) > 90) {
            return self::unavailable();
        }
        $delay = $r['delay_seconds'] ?? null;
        return ['status' => 'live','position' => ['lat' => (float)$c[1],'lon' => (float)$c[0]],'observed_at' => gmdate(DATE_RFC3339, $observed),'valid_until' => gmdate(DATE_RFC3339, $observed + 30),
            'delay_seconds' => is_numeric($delay) && abs((float)$delay) <= 86400 ? (int)$delay : null,'cancelled' => is_bool($r['cancelled'] ?? null) ? $r['cancelled'] : null];
    }
}
