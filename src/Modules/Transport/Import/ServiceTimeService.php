<?php

declare(strict_types=1);

namespace App\Modules\Transport\Import;

use App\Modules\Transport\TransportException;

final class ServiceTimeService
{
    public static function seconds(string $time): ?int
    {
        if ($time === '') {
            return null;
        }
        if (!preg_match('/^(\d{1,3}):([0-5]\d):([0-5]\d)$/D', $time, $m)) {
            throw new TransportException('invalid_gtfs_time', 'Invalid GTFS time.');
        }
        return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
    }
    public static function instant(string $date, int $seconds, string $timezone): \DateTimeImmutable
    {
        $noon = new \DateTimeImmutable($date.' 12:00:00', new \DateTimeZone($timezone));
        return $noon->setTimestamp($noon->getTimestamp() - 43200 + $seconds);
    }
    public static function date(string $value): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Ymd', $value);
        if (!$d || $d->format('Ymd') !== $value) {
            throw new TransportException('invalid_gtfs_date', 'Invalid GTFS service date.');
        }
        return $d->format('Y-m-d');
    }
}
