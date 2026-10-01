<?php

declare(strict_types=1);

namespace App\Modules\Transport\Protocols\GtfsRealtime;

/** Decode vehicle observations only; never substitute the feed timestamp for a vehicle timestamp. */
final class GtfsRealtimeReader
{
    public static function vehicles(string $bytes): array
    {
        $feed = ProtobufReader::fields($bytes);
        $header = ProtobufReader::fields(ProtobufReader::value($feed, 1, 2, ''));
        if (
            !in_array(ProtobufReader::value($header, 1, 2), ['1.0', '2.0'], true)
            || ProtobufReader::value($header, 2, 0, 0) !== 0 || count($feed[2] ?? []) > 10000
        ) {
            ProtobufReader::invalid();
        }
        $rows = [];
        foreach ($feed[2] ?? [] as [$wire, $bytes]) {
            if ($wire !== 2) {
                ProtobufReader::invalid();
            }
            $entity = ProtobufReader::fields($bytes);
            if (ProtobufReader::value($entity, 2, 0, 0) === 1 || !isset($entity[4])) {
                continue;
            }
            $vehicle = ProtobufReader::fields(ProtobufReader::value($entity, 4, 2));
            $trip = ProtobufReader::fields(ProtobufReader::value($vehicle, 1, 2, ''));
            $position = ProtobufReader::fields(ProtobufReader::value($vehicle, 2, 2, ''));
            $identity = ProtobufReader::fields(ProtobufReader::value($vehicle, 8, 2, ''));
            $lat = ProtobufReader::value($position, 1, 5);
            $lon = ProtobufReader::value($position, 2, 5);
            if ($lat === null || $lon === null) {
                continue;
            }
            $rows[] = [
                'trip_id' => ProtobufReader::value($trip, 1, 2),
                'start_date' => ProtobufReader::value($trip, 3, 2),
                'start_time' => ProtobufReader::value($trip, 2, 2),
                'relationship' => ProtobufReader::value($trip, 4, 0, 0),
                'lat' => unpack('g', $lat)[1],
                'lon' => unpack('g', $lon)[1],
                'timestamp' => ProtobufReader::value($vehicle, 5, 0),
                'stop_id' => ProtobufReader::value($vehicle, 7, 2),
                'vehicle_id' => ProtobufReader::value($identity, 1, 2),
                'vehicle_label' => ProtobufReader::value($identity, 2, 2)
            ];
        }
        return $rows;
    }
}
