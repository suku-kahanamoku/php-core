<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTO;

use App\Modules\Transport\TransportException;

final class JourneyQuery
{
    public const MODES = ['bus','tram','train','metro','trolleybus','ferry','coach','airplane','cable_car','gondola','funicular','monorail'];
    public function __construct(
        public readonly array $from,
        public readonly array $to,
        public readonly \DateTimeImmutable $time,
        public readonly bool $arriveBy,
        public readonly ?string $country,
        public readonly ?string $city,
        public readonly array $modes,
        public readonly int $maxTransfers,
        public readonly int $limit,
    ) {
    }
    public static function fromArray(array $input): self
    {
        $allowed = ['from-dest','to-dest','from-date','to-date','state','city','modes','max-transfers','limit'];
        if (array_diff(array_keys($input), $allowed)) {
            throw new TransportException('invalid_query', 'Unknown search attributes.');
        }
        if (isset($input['from-date']) === isset($input['to-date'])) {
            throw new TransportException('invalid_date', 'Supply exactly one of from-date and to-date.');
        }
        $country = $input['state'] ?? null;
        if ($country !== null && (!is_string($country) || !preg_match('/^[A-Z]{2}$/D', $country))) {
            throw new TransportException('invalid_country', 'state must be an ISO 3166-1 alpha-2 country code.');
        }
        $city = $input['city'] ?? null;
        if ($city !== null && (!is_string($city) || mb_strlen($city) > 120)) {
            throw new TransportException('invalid_city', 'Invalid city.');
        }
        $modes = $input['modes'] ?? self::MODES;
        if (!is_array($modes) || !array_is_list($modes) || !$modes || array_filter($modes, fn ($m) => !is_string($m) || !in_array($m, self::MODES, true))) {
            throw new TransportException('invalid_modes', 'Unsupported transport mode.');
        }
        return new self(
            self::place($input['from-dest'] ?? null),
            self::place($input['to-dest'] ?? null),
            self::date($input['from-date'] ?? $input['to-date']),
            isset($input['to-date']),
            $country,
            $city,
            array_values(array_unique($modes)),
            self::integer($input['max-transfers'] ?? 5, 0, 10),
            self::integer($input['limit'] ?? 10, 1, 20)
        );
    }
    public static function date(mixed $value): \DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new TransportException('invalid_date', 'Use RFC3339 date-time with an explicit offset.');
        }
        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new TransportException('invalid_date', 'Invalid date-time.');
        }
        $errors = \DateTimeImmutable::getLastErrors();
        if ($errors && ($errors['warning_count'] || $errors['error_count'])) {
            throw new TransportException('invalid_date', 'Invalid calendar date.');
        }
        return $date;
    }
    public static function integer(mixed $value, int $min, int $max): int
    {
        if (!(is_int($value) || (is_string($value) && ctype_digit($value))) || (int)$value < $min || (int)$value > $max) {
            throw new TransportException('invalid_limit', "Expected an integer between $min and $max.");
        }
        return (int)$value;
    }
    private static function place(mixed $value): array
    {
        if (!is_array($value)) {
            throw new TransportException('invalid_place', 'Select a stop or provide coordinates.');
        }
        if (($value['type'] ?? '') === 'stop' && is_string($value['id'] ?? null) && strlen($value['id']) <= 2048) {
            return ['type' => 'stop','id' => $value['id']];
        }
        if (($value['type'] ?? '') === 'coordinates' && is_numeric($value['lat'] ?? null) && is_numeric($value['lon'] ?? null)) {
            $lat = (float)$value['lat'];
            $lon = (float)$value['lon'];
            if (is_finite($lat) && is_finite($lon) && abs($lat) <= 90 && abs($lon) <= 180) {
                return ['type' => 'coordinates','lat' => $lat,'lon' => $lon];
            }
        }
        throw new TransportException('invalid_place', 'Invalid destination.');
    }
    public function withPlaces(array $from, array $to): self
    {
        return new self($from, $to, $this->time, $this->arriveBy, $this->country, $this->city, $this->modes, $this->maxTransfers, $this->limit);
    }
}
