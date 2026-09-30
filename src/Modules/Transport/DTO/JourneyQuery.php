<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTO;

use App\Modules\Transport\TransportException;

/**
 * Normalizovaný a zvalidovaný dotaz na spojení.
 *
 * Dotaz se sestavuje výhradně z povolených atributů (`from-dest`, `to-dest`,
 * `from-date`, `to-date`, `state`, `city`, `modes`, `max-transfers`, `limit`);
 * čas musí být RFC3339 s explicitním posunem a právě jeden z `from-date` a
 * `to-date` určuje, zda se hledá odjezd, nebo příjezd.
 */
final class JourneyQuery
{
    /** Podporované způsoby dopravy. */
    public const MODES = ['bus','tram','train','metro','trolleybus','ferry','coach','airplane','cable_car','gondola','funicular','monorail'];

    /**
     * @param  array{type: string, id?: string, lat?: float, lon?: float} $from         Výchozí místo (zastávka nebo souřadnice).
     * @param  array{type: string, id?: string, lat?: float, lon?: float} $to           Cílové místo.
     * @param  \DateTimeImmutable                                          $time         Čas odjezdu nebo příjezdu.
     * @param  bool                                                         $arriveBy    true, pokud `time` znamená čas příjezdu.
     * @param  string|null                                                  $country      Kód země ISO 3166-1 alpha-2, nebo null.
     * @param  string|null                                                  $city         Město jako volitelný hint.
     * @param  list<string>                                                 $modes        Způsoby dopravy.
     * @param  int                                                          $maxTransfers Maximální počet přestupů (0–10).
     * @param  int                                                          $limit        Maximální počet výsledků (1–20).
     * @return void
     */
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
    /**
     * Sestaví dotaz ze vstupu API a všechny hodnoty zvaliduje.
     *
     * @param  array<string, mixed> $input Vstup s povolenými atributy dotazu.
     * @return self                        Normalizovaný dotaz.
     * @throws TransportException          'invalid_query', 'invalid_date', 'invalid_country',
     *                                    'invalid_city', 'invalid_modes', 'invalid_limit'
     *                                    nebo 'invalid_place'.
     */
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
    /**
     * Načte čas ve formátu RFC3339 s povinným posunem.
     *
     * @param  mixed $value Hodnota z `from-date` nebo `to-date`.
     * @return \DateTimeImmutable Čas jako neměnný objekt.
     * @throws TransportException  'invalid_date', pokud formát nesedí nebo jde o
     *                             neexistující datum v kalendáři.
     */
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
    /**
     * Načte celé číslo v zadaném rozsahu.
     *
     * @param  mixed $value Hodnota z dotazu.
     * @param  int   $min   Nejmenší přípustná hodnota.
     * @param  int   $max   Největší přípustná hodnota.
     * @return int          Hodnota jako celé číslo.
     * @throws TransportException 'invalid_limit', pokud hodnota není celé číslo
     *                             nebo neleží v rozsahu.
     */
    public static function integer(mixed $value, int $min, int $max): int
    {
        if (!(is_int($value) || (is_string($value) && ctype_digit($value))) || (int)$value < $min || (int)$value > $max) {
            throw new TransportException('invalid_limit', "Expected an integer between $min and $max.");
        }
        return (int)$value;
    }
    /**
     * Normalizuje místo na zastávku nebo na souřadnice.
     *
     * @param  mixed $value Hodnota z `from-dest` nebo `to-dest`.
     * @return array{type: string, id?: string, lat?: float, lon?: float, observed_at?: string} Místo.
     * @throws TransportException 'invalid_place' pro neplatnou zastávku/bod;
     *                            'stale_location' pro starý GPS fix uživatele.
     */
    private static function place(mixed $value): array
    {
        if (!is_array($value)) {
            throw new TransportException('invalid_place', 'Select a stop or provide coordinates.');
        }
        if (($value['type'] ?? '') === 'stop' && is_string($value['id'] ?? null) && strlen($value['id']) <= 2048) {
            return ['type' => 'stop','id' => $value['id']];
        }
        $type = $value['type'] ?? null;
        if (in_array($type, ['coordinates','current_location'], true) && is_numeric($value['lat'] ?? null) && is_numeric($value['lon'] ?? null)) {
            $lat = (float)$value['lat'];
            $lon = (float)$value['lon'];
            if (is_finite($lat) && is_finite($lon) && abs($lat) <= 90 && abs($lon) <= 180) {
                if ($type === 'coordinates') {
                    if (isset($value['observed-at'])) {
                        throw new TransportException('invalid_place', 'Use current_location for a device GPS fix.');
                    }
                    return ['type' => 'coordinates','lat' => $lat,'lon' => $lon];
                }
                try {
                    $observed = self::date($value['observed-at'] ?? null);
                } catch (TransportException) {
                    throw new TransportException('invalid_place', 'Current location requires observed-at in RFC3339 format.');
                }
                $place = ['type' => 'current_location','lat' => $lat,'lon' => $lon,'observed_at' => $observed->format(DATE_RFC3339)];
                self::assertFreshLocation($place);
                return $place;
            }
        }
        throw new TransportException('invalid_place', 'Invalid destination.');
    }
    /** Require a new device fix if the request waited long enough to become stale. */
    public static function assertFreshLocation(array $place): void
    {
        if (($place['type'] ?? null) !== 'current_location') {
            return;
        }
        $observed = is_string($place['observed_at'] ?? null) ? strtotime($place['observed_at']) : false;
        if ($observed === false || time() - $observed > 30 || $observed - time() > 5) {
            throw new TransportException('stale_location', 'Get a fresh device location before searching.');
        }
    }

    /**
     * Vytvoří kopii dotazu s nahrazenými místy (např. po geokódování).
     *
     * @param  array{type: string, id?: string, lat?: float, lon?: float} $from Nové výchozí místo.
     * @param  array{type: string, id?: string, lat?: float, lon?: float} $to   Nové cílové místo.
     * @return self                                                     Kopie dotazu.
     */
    public function withPlaces(array $from, array $to): self
    {
        return new self($from, $to, $this->time, $this->arriveBy, $this->country, $this->city, $this->modes, $this->maxTransfers, $this->limit);
    }
}
