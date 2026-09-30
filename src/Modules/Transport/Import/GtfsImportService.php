<?php

declare(strict_types=1);

namespace App\Modules\Transport\Import;

use App\Modules\Transport\TransportException;

/**
 * Import souborů GTFS do verzí dopravních dat.
 *
 * Volající vlastní transakci a zámek feedu, takže jsou všechny řádky neviditelné
 * až do `commit`. Zápisy se sdružují do dávek (500 řádků nebo 1 MB), po nichž
 * se kontrolují vztahy mezi zastávkami, spoji a časy; GTFS Flex se záměrně
 * odmítá, protože vyžaduje jiný profil importu.
 */
final class GtfsImportService
{
    /** Připravené výkazy pro dávkové vkládání (podle tvaru dávky). */
    private array $statements = [];

    /** Aktuálně sestavovaná dávka řádků. */
    private array $batch = [];

    /** Tabulka, do které patří aktuální dávka. */
    private string $batchTable = '';

    /** Sloupce aktuální dávky. */
    private array $batchColumns = [];

    /** Orientační velikost aktuální dávky v bajtech. */
    private int $batchBytes = 0;

    /**
     * @param  \PDO   $db     Připravené připojení; import běží v cizí transakci.
     * @param  string $tenant Kód okurku, do kterého se importuje.
     * @return void
     */
    public function __construct(private readonly \PDO $db, private readonly string $tenant)
    {
    }

    /**
     * Naimportuje GTFS archiv do dané verze feedu.
     *
     * @param  string $path    Cesta k archivu na disku.
     * @param  int    $version ID verze feedu.
     * @return array<string, int> Počet vložených řádků podle tabulky.
     * @throws TransportException Při neplatném archivu, souřadnicích, časech, kalendáři
     *                            nebo vztazích mezi záznamy.
     */
    public function import(string $path, int $version): array
    {
        $zip = new GtfsArchiveReader($path);
        $counts = [];
        $operators = [];
        /**
         * Vloží jeden řádek s výchozími sloupci verze a započítá ho.
         *
         * @param  string              $table Tabulka bez předpony `transport_`.
         * @param  array<string, mixed> $data Atributy řádku.
         * @return void               Vedlejší efekt: zápis do aktuální dávky.
         */
        $put =
            /**
             * Vloží jeden řádek s výchozími sloupci verze a započítá ho.
             *
             * @param  string               $table Tabulka bez předpony `transport_`.
             * @param  array<string, mixed> $data  Atributy řádku.
             * @return void                Vedlejší efekt: zápis do aktuální dávky.
             */
            function (string $table, array $data) use ($version, &$counts): void {
            $this->insert($table, ['franchise_code' => $this->tenant,'version_id' => $version] + $data);
            $counts[$table] = ($counts[$table] ?? 0) + 1;
        };
        foreach ($zip->rows('agency.txt', ['agency_name','agency_timezone']) as $r) {
            $id = $r['agency_id'] ?? 'default';
            if ($id === '') {
                $id = 'default';
            }
            try {
                new \DateTimeZone($r['agency_timezone']);
            } catch (\Exception) {
                throw new TransportException('invalid_timezone', 'Invalid agency timezone.');
            }
            $operators[] = $id;
            $put('operator', ['external_id' => $id,'name' => $r['agency_name'],'timezone' => $r['agency_timezone'],'data' => self::json($r)]);
        }
        foreach ($zip->rows('stops.txt', ['stop_id','stop_name']) as $r) {
            $lat = self::coordinate($r['stop_lat'] ?? '', 90);
            $lon = self::coordinate($r['stop_lon'] ?? '', 180);
            if (($lat === null || $lon === null) && in_array($r['location_type'] ?? '0', ['','0','1','2'], true)) {
                throw new TransportException('invalid_stop', 'Stop coordinates are required.');
            }
            $put('stop', ['external_id' => self::id($r['stop_id']),'name' => $r['stop_name'],'lat' => $lat,'lon' => $lon,'parent_id' => self::nullable($r['parent_station'] ?? ''),
                'location_type' => self::number($r['location_type'] ?? '', 0, 4),'platform' => self::nullable($r['platform_code'] ?? ''),'data' => self::json($r)]);
        }
        foreach ($zip->rows('routes.txt', ['route_id','route_type']) as $r) {
            $agency = self::nullable($r['agency_id'] ?? '') ?? (count($operators) === 1 ? $operators[0] : throw new TransportException('missing_agency', 'Route agency is required.'));
            $put('route', ['external_id' => self::id($r['route_id']),'operator_id' => $agency,'name' => ($r['route_short_name'] ?? '') ?: ($r['route_long_name'] ?? ''),'mode' => self::mode($r['route_type']),'data' => self::json($r)]);
        }
        if (!$zip->has('calendar.txt') && !$zip->has('calendar_dates.txt')) {
            throw new TransportException('missing_calendar', 'GTFS calendar is required.');
        }
        foreach ($zip->rows('calendar.txt', ['service_id','monday','tuesday','wednesday','thursday','friday','saturday','sunday','start_date','end_date'], true) as $r) {
            $days = '';
            foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $d) {
                $days .= (string)self::number($r[$d], 0, 1);
            }
            $start = ServiceTimeService::date($r['start_date']);
            $end = ServiceTimeService::date($r['end_date']);
            if ($end < $start) {
                throw new TransportException('invalid_calendar', 'Calendar ends before it starts.');
            }
            $put('service', ['external_id' => self::id($r['service_id']),'weekdays' => $days,'start_date' => $start,'end_date' => $end,'data' => self::json($r)]);
        }
        $this->flush();
        foreach ($zip->rows('calendar_dates.txt', ['service_id','date','exception_type'], true) as $r) {
            $id = self::id($r['service_id']);
            $st = $this->db->prepare("INSERT INTO transport_service (franchise_code,version_id,external_id,data) VALUES (?,?,?,'{}') ON DUPLICATE KEY UPDATE external_id=VALUES(external_id)");
            $st->execute([$this->tenant,$version,$id]);
            $put('service_exception', ['service_id' => $id,'service_date' => ServiceTimeService::date($r['date']),'exception_type' => self::number($r['exception_type'], 1, 2)]);
        }
        foreach ($zip->rows('shapes.txt', ['shape_id','shape_pt_lat','shape_pt_lon','shape_pt_sequence'], true) as $r) {
            $put('shape', ['external_id' => self::id($r['shape_id']),'sequence' => self::number($r['shape_pt_sequence'], 0, 2147483647),
                'lat' => self::coordinate($r['shape_pt_lat'], 90),'lon' => self::coordinate($r['shape_pt_lon'], 180),'distance' => self::nullable($r['shape_dist_traveled'] ?? '')]);
        }
        foreach ($zip->rows('trips.txt', ['trip_id','route_id','service_id']) as $r) {
            $put('trip', ['external_id' => self::id($r['trip_id']),'route_id' => $r['route_id'],'service_id' => $r['service_id'],'shape_id' => self::nullable($r['shape_id'] ?? ''),'headsign' => self::nullable($r['trip_headsign'] ?? ''),'data' => self::json($r)]);
        }
        foreach ($zip->rows('stop_times.txt', ['trip_id','stop_id','stop_sequence','arrival_time','departure_time']) as $r) {
            if (!empty($r['location_group_id']) || !empty($r['location_id']) || !empty($r['start_pickup_drop_off_window'])) {
                throw new TransportException('unsupported_gtfs_flex', 'GTFS Flex needs a dedicated importer profile.');
            }
            $arrival = ServiceTimeService::seconds($r['arrival_time']);
            $departure = ServiceTimeService::seconds($r['departure_time']);
            if ($arrival !== null && $departure !== null && $departure < $arrival) {
                throw new TransportException('invalid_stop_time', 'Departure precedes arrival.');
            }
            $put('stop_time', ['trip_id' => $r['trip_id'],'sequence' => self::number($r['stop_sequence'], 0, 2147483647),'stop_id' => $r['stop_id'],'arrival_seconds' => $arrival,'departure_seconds' => $departure,
                'pickup_type' => self::number($r['pickup_type'] ?? '', 0, 3),'drop_off_type' => self::number($r['drop_off_type'] ?? '', 0, 3),'data' => self::json($r)]);
        }
        foreach ($zip->rows('frequencies.txt', ['trip_id','start_time','end_time','headway_secs'], true) as $r) {
            $start = ServiceTimeService::seconds($r['start_time']);
            $end = ServiceTimeService::seconds($r['end_time']);
            if ($start === null || $end === null || $end <= $start) {
                throw new TransportException('invalid_frequency', 'Invalid frequency window.');
            }
            $put('frequency', ['trip_id' => $r['trip_id'],'start_seconds' => $start,'end_seconds' => $end,'headway_seconds' => self::number($r['headway_secs'], 1, 86400),'exact_times' => self::number($r['exact_times'] ?? '', 0, 1)]);
        }
        $sequence = 0;
        foreach ($zip->rows('transfers.txt', ['transfer_type'], true) as $r) {
            $put('transfer', ['sequence' => ++$sequence,'from_stop_id' => self::nullable($r['from_stop_id'] ?? ''),'to_stop_id' => self::nullable($r['to_stop_id'] ?? ''),'transfer_type' => self::number($r['transfer_type'], 0, 5),'min_transfer_seconds' => ($r['min_transfer_time'] ?? '') !== '' ? self::number($r['min_transfer_time'], 0, 86400) : null,'data' => self::json($r)]);
        }
        foreach (['operator','stop','route','trip','stop_time'] as $required) {
            if (empty($counts[$required])) {
                throw new TransportException('empty_gtfs', 'GTFS contains no '.$required.' records.');
            }
        }
        $this->flush();
        $this->validate($version);
        return $counts;
    }
    /**
     * Ověří konzistenci naimportovaných vztahů.
     *
     * Kontroluje existenci mateřské zastávky, směr časů uvnitř spoje (jedním
     * průchodem bez mezipaměti) a to, že každý spoj má zastávky.
     *
     * @param  int $version ID verze feedu.
     * @return void         Bez návratu; při chybě transakci zruší volající.
     * @throws TransportException 'invalid_gtfs_relations' při neplatných vztazích.
     */
    private function validate(int $version): void
    {
        $statement = $this->db->prepare('SELECT 1 FROM transport_stop s LEFT JOIN transport_stop p ON p.franchise_code=s.franchise_code AND p.version_id=s.version_id AND p.external_id=s.parent_id WHERE s.franchise_code=? AND s.version_id=? AND s.parent_id IS NOT NULL AND p.external_id IS NULL LIMIT 1');
        $statement->execute([$this->tenant, $version]);
        if ($statement->fetchColumn()) {
            throw new TransportException('invalid_gtfs_relations', 'Unknown parent station.');
        }
        $statement->closeCursor();

        // One ordered, unbuffered scan instead of repeated joins and disk-backed window sorts.
        $buffered = $this->db->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $this->db->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $statement = $this->db->prepare('SELECT trip_id,arrival_seconds,departure_seconds FROM transport_stop_time WHERE franchise_code=? AND version_id=? ORDER BY trip_id,sequence');
        $previousTrip = null;
        $count = 0;
        $last = null;
        $previousTime = null;
        /**
         * Ověří, že spoj má alespoň dvě zastávky a časované konce.
         *
         * @param  array<string, mixed>|null $last Poslední načtený řádek spoje.
         * @param  int                        $count Počet dosud načtených zastávek spoje.
         * @return void                                Bez návratu; při chybě vyhodí výjimku.
         * @throws TransportException 'invalid_gtfs_relations', pokud spoj nesplňuje podmínky.
         */
        $checkLast =
            /**
             * Ověří, že spoj má alespoň dvě zastávky a časované konce.
             *
             * @param  array<string, mixed>|null $last Poslední načtený řádek spoje.
             * @param  int                        $count Počet dosud načtených zastávek spoje.
             * @return void                                Bez návratu; při chybě vyhodí výjimku.
             * @throws TransportException 'invalid_gtfs_relations', pokud spoj nesplňuje podmínky.
             */
            static function (?array $last, int $count): void {
            if ($last !== null && ($count < 2 || $last['arrival_seconds'] === null || $last['departure_seconds'] === null)) {
                throw new TransportException('invalid_gtfs_relations', 'Trips require two stops and timed endpoints.');
            }
        };
        try {
            $statement->execute([$this->tenant, $version]);
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                if ($row['trip_id'] !== $previousTrip) {
                    $checkLast($last, $count);
                    $previousTrip = $row['trip_id'];
                    $count = 0;
                    $previousTime = null;
                    if ($row['arrival_seconds'] === null || $row['departure_seconds'] === null) {
                        throw new TransportException('invalid_gtfs_relations', 'First stop needs arrival and departure times.');
                    }
                }
                foreach (['arrival_seconds', 'departure_seconds'] as $column) {
                    if ($row[$column] !== null) {
                        if ($previousTime !== null && (int)$row[$column] < $previousTime) {
                            throw new TransportException('invalid_gtfs_relations', 'Stop times go backwards within a trip.');
                        }
                        $previousTime = (int)$row[$column];
                    }
                }
                $last = $row;
                ++$count;
            }
            $checkLast($last, $count);
        } finally {
            $statement->closeCursor();
            $this->db->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
        }
        $statement = $this->db->prepare('SELECT 1 FROM transport_trip t WHERE t.franchise_code=? AND t.version_id=? AND NOT EXISTS (SELECT 1 FROM transport_stop_time s WHERE s.franchise_code=t.franchise_code AND s.version_id=t.version_id AND s.trip_id=t.external_id) LIMIT 1');
        $statement->execute([$this->tenant, $version]);
        if ($statement->fetchColumn()) {
            throw new TransportException('invalid_gtfs_relations', 'Trip has no stop times.');
        }
        $statement->closeCursor();
    }

    /**
     * Přidá řádek do aktuální dávky a v případě potřeby ji vyprázdní.
     *
     * @param  string              $table Tabulka bez předpony `transport_`.
     * @param  array<string, mixed> $data  Atributy řádku.
     * @return void                     Vedlejší efekt: případné `flush()` před změnou tvaru dávky.
     */
    private function insert(string $table, array $data): void
    {
        $columns = array_keys($data);
        if ($this->batch && ($this->batchTable !== $table || $this->batchColumns !== $columns)) {
            $this->flush();
        }
        $this->batchTable = $table;
        $this->batchColumns = $columns;
        $this->batch[] = array_values($data);
        foreach ($data as $value) {
            $this->batchBytes += strlen((string) $value);
        }
        if (count($this->batch) >= 500 || $this->batchBytes >= 1048576) {
            $this->flush();
        }
    }

    /**
     * Vloží sestavenou dávku jedním výkazem (multi-row insert).
     *
     * @return void Vedlejší efekt: provedení připraveného výkazu.
     * @throws \PDOException Pokud se výkaz neprovede.
     */
    private function flush(): void
    {
        if (!$this->batch) {
            return;
        }
        $key = $this->batchTable . ':' . implode(',', $this->batchColumns) . ':' . count($this->batch);
        $row = '(' . implode(',', array_fill(0, count($this->batchColumns), '?')) . ')';
        $statement = $this->statements[$key] ??= $this->db->prepare(
            'INSERT INTO transport_' . $this->batchTable . ' (' . implode(',', $this->batchColumns) . ') VALUES '
            . implode(',', array_fill(0, count($this->batch), $row)),
        );
        $values = [];
        foreach ($this->batch as $record) {
            array_push($values, ...$record);
        }
        $statement->execute($values);
        $this->batch = [];
        $this->batchBytes = 0;
    }

    /**
     * Serializuje původní řádek GTFS pro audit a dohledávání.
     *
     * @param  array<string, string|null> $data Řádek souboru.
     * @return string                     JSON bez escapovaných lomítek a Unicode.
     * @throws \JsonException            Pokud řádek nelze serializovat.
     */
    private static function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Převede prázdnou hodnotu GTFS na `null`.
     *
     * @param  string $value Hodnota ze souboru.
     * @return string|null   Hodnota, nebo null pro prázdný řetězec.
     */
    private static function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * Ověří identifikátor z GTFS.
     *
     * @param  string $value Identifikátor ze souboru.
     * @return string       Identifikátor pro uložení.
     * @throws TransportException 'invalid_gtfs_id', pokud je prázdný nebo delší
     *                            než 255 znaků.
     */
    private static function id(string $value): string
    {
        if ($value === '' || strlen($value) > 255) {
            throw new TransportException('invalid_gtfs_id', 'Invalid GTFS identifier.');
        } return $value;
    }
    /**
     * Načte souřadnici a ověří její rozsah.
     *
     * @param  string $value Hodnota ze souboru.
     * @param  int    $bound Maximální absolutní hodnota (90 pro šířku, 180 pro délku).
     * @return float|null    Souřadnice, nebo null pro prázdnou hodnotu.
     * @throws TransportException 'invalid_coordinate', pokud hodnota není číslo
     *                            nebo překračuje rozsah.
     */
    private static function coordinate(string $value, int $bound): ?float
    {
        if ($value === '') {
            return null;
        } if (!is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > $bound) {
            throw new TransportException('invalid_coordinate', 'Invalid coordinate.');
        } return (float)$value;
    }
    /**
     * Načte celočíselnou hodnotu v zadaném rozsahu; prázdná hodnota znamená nulu.
     *
     * @param  string $value Hodnota ze souboru.
     * @param  int    $min   Nejmenší přípustná hodnota.
     * @param  int    $max   Největší přípustná hodnota.
     * @return int           Hodnota jako celé číslo.
     * @throws TransportException 'invalid_gtfs_number', pokud hodnota není nezáporné
     *                            celé číslo v rozsahu.
     */
    private static function number(string $value, int $min, int $max): int
    {
        if ($value === '') {
            $value = '0';
        } if (!ctype_digit($value) || (int)$value < $min || (int)$value > $max) {
            throw new TransportException('invalid_gtfs_number', 'Invalid GTFS numeric value.');
        } return (int)$value;
    }
    /**
     * Převede `route_type` z GTFS na způsob dopravy našeho systému.
     *
     * @param  string $value Hodnota `route_type` včetně rozšířených rozsahů GTFS.
     * @return string        `bus`, `tram`, `train`, `metro`, `trolleybus`, `ferry`,
     *                       `coach`, `airplane`, `cable_car`, `gondola`, `funicular`,
     *                       `monorail`, nebo `other` pro nespuštěné trasy.
     */
    public static function mode(string $value): string
    {
        $type = (int)$value;
        return match(true) {
            $type === 0 || ($type >= 900 && $type < 1000) => 'tram', $type === 1 || $type === 400 || $type === 401 || $type === 402 => 'metro',
            $type === 2 || ($type >= 100 && $type < 200) => 'train', $type >= 200 && $type < 300 => 'coach',
            $type === 3 || ($type >= 700 && $type < 800) => 'bus', $type === 4 || ($type >= 1000 && $type < 1100) || $type === 1200 => 'ferry',
            $type === 5 => 'cable_car', $type === 6 || $type === 1300 => 'gondola', $type === 7 || $type === 1400 => 'funicular',
            $type === 11 || $type === 800 => 'trolleybus', $type === 12 || $type === 405 => 'monorail', $type === 1100 => 'airplane',
            default => 'other'
        };
    }
}
