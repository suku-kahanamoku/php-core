<?php

declare(strict_types=1);

namespace App\Modules\Transport\Protocols\GtfsRealtime;

use App\Modules\Transport\Model\TransportException;

/** Bounded wire reader. Unknown standard wire fields are skipped by consumers; groups are rejected. */
final class ProtobufReader
{
    public static function fields(string $bytes): array
    {
        if (strlen($bytes) > 8000000) {
            self::invalid();
        }
        $at = 0;
        $fields = [];
        $count = 0;
        $length = strlen($bytes);
        while ($at < $length) {
            if (++$count > 100000) {
                self::invalid();
            }
            $tag = self::integer($bytes, $at);
            $wire = $tag & 7;
            $field = $tag >> 3;
            if ($field < 1 || $field > 536870911) {
                self::invalid();
            }
            if ($wire === 0) {
                $value = self::integer($bytes, $at);
            } else {
                $size = match ($wire) {
                    1 => 8,
                    5 => 4,
                    2 => self::integer($bytes, $at),
                    default => self::invalid()
                };
                if ($size < 0 || $size > $length - $at) {
                    self::invalid();
                }
                $value = substr($bytes, $at, $size);
                $at += $size;
            }
            $fields[$field][] = [$wire, $value];
        }
        return $fields;
    }
    public static function value(array $fields, int $number, int $wire, mixed $default = null): mixed
    {
        if (!isset($fields[$number])) {
            return $default;
        }
        if (count($fields[$number]) !== 1 || $fields[$number][0][0] !== $wire) {
            self::invalid();
        }
        return $fields[$number][0][1];
    }
    private static function integer(string $bytes, int &$at): int
    {
        $value = 0;
        for ($i = 0; $i < 10; ++$i) {
            if ($at >= strlen($bytes)) {
                self::invalid();
            }
            $byte = ord($bytes[$at++]);
            if ($i === 9 && $byte > 1) {
                self::invalid();
            }
            $value |= ($byte & 127) << ($i * 7);
            if (($byte & 128) === 0) {
                return $value;
            }
        }
        self::invalid();
    }
    public static function invalid(): never
    {
        throw new TransportException('invalid_upstream', 'Invalid GTFS Realtime message.', 502);
    }
}
