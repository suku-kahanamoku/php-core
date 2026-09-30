<?php

declare(strict_types=1);

namespace App\Modules\Transport\Import;

use App\Modules\Transport\TransportException;

/**
 * Převod časů a dat GTFS.
 *
 * Časy v GTFS mohou překročit půlnoc (až 99:59:59), proto se ukládají jako
 * počet sekund od půlnoci a na okamžik se převádějí až podle zvoleného časového
 * pásma; datum musí být přesně osmimístné bez oddělovačů.
 */
final class ServiceTimeService
{
    /**
     * Převede čas GTFS na počet sekund od půlnoci.
     *
     * @param  string $time Čas ve tvaru `H:MM:SS` (až 99 hodin).
     * @return int|null     Sekundy od půlnoci, nebo null pro prázdnou hodnotu.
     * @throws TransportException 'invalid_gtfs_time', pokud formát nesedí.
     */
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
    /**
     * Převede čas GTFS na okamžik v zadaném časovém pásmu.
     *
     * @param  string               $date     Datum ve tvaru `Y-m-d`.
     * @param  int                  $seconds  Sekundy od půlnoci služby.
     * @param  string               $timezone IANA časové pásmo.
     * @return \DateTimeImmutable Okamžik odpovídající času služby.
     */
    public static function instant(string $date, int $seconds, string $timezone): \DateTimeImmutable
    {
        $noon = new \DateTimeImmutable($date.' 12:00:00', new \DateTimeZone($timezone));
        return $noon->setTimestamp($noon->getTimestamp() - 43200 + $seconds);
    }
    /**
     * Převede datum GTFS (`Ymd`) na `Y-m-d` a ověří, že skutečně existuje.
     *
     * @param  string $value Datum ve tvaru `Ymd`.
     * @return string        Datum ve tvaru `Y-m-d`.
     * @throws TransportException 'invalid_gtfs_date', pokud datum neexistuje.
     */
    public static function date(string $value): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Ymd', $value);
        if (!$d || $d->format('Ymd') !== $value) {
            throw new TransportException('invalid_gtfs_date', 'Invalid GTFS service date.');
        }
        return $d->format('Y-m-d');
    }
}
