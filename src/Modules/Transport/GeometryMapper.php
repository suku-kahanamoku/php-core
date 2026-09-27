<?php

declare(strict_types=1);

namespace App\Modules\Transport;

final class GeometryMapper
{
    public static function polyline(string $encoded): array
    {
        $points = [];
        $lat = 0;
        $lon = 0;
        $offset = 0;
        $length = strlen($encoded);
        if ($length > 1000000) {
            throw new TransportException('invalid_geometry', 'GeometryMapper exceeds limit.', 502);
        }
        while ($offset < $length) {
            foreach (['lat','lon'] as $axis) {
                $value = 0;
                $shift = 0;
                do {
                    if ($offset >= $length || $shift > 30) {
                        throw new TransportException('invalid_geometry', 'Invalid polyline.', 502);
                    }
                    $byte = ord($encoded[$offset++]) - 63;
                    if ($byte < 0 || $byte > 63) {
                        throw new TransportException('invalid_geometry', 'Invalid polyline.', 502);
                    }
                    $value |= ($byte & 31) << $shift;
                    $shift += 5;
                } while ($byte >= 32);
                $delta = ($value & 1) ? ~($value >> 1) : ($value >> 1);
                if ($axis === 'lat') {
                    $lat += $delta;
                } else {
                    $lon += $delta;
                }
            }
            if (abs($lat) > 9000000 || abs($lon) > 18000000) {
                throw new TransportException('invalid_geometry', 'Invalid coordinates.', 502);
            }
            $points[] = [$lon / 100000,$lat / 100000];
        }
        return ['type' => 'LineString','coordinates' => $points];
    }
}
