<?php

declare(strict_types=1);

namespace App\Modules\Transport\Model;


/**
 * Převod kódované geometrie na GeoJSON.
 *
 * Dekódování je omezené na 1 MB vstupu a 30 bitů na souřadnici, aby se
 * ztrátové nebo podvržené vstupy od upstreamu nezpracovaly bez omezení.
 */
final class GeometryMapper
{
    /**
     * Dekóduje kódovanou polyline (Google/OTP formát) na `LineString`.
     *
     * @param  string $encoded Kódovaná polyline.
     * @return array{type: string, coordinates: list<array{0: float, 1: float}>}
     *         GeoJSON geometrie s body `[lon, lat]`.
     * @throws TransportException 'invalid_geometry' (502), pokud je vstup příliš
     *                            dlouhý, obsahuje neplatné znaky nebo souřadnice
     *                            mimo rozsah.
     */
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
