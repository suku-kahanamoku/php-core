<?php

declare(strict_types=1);

namespace App\Modules\Transport\Repositories;

use App\Modules\Transport\{TransportException,ResourceIdCodec};
use App\Modules\Transport\Import\ServiceTimeService;

/**
 * Databázová vrstva dopravy jednoho okurku.
 *
 * Každý výrok je omezen na `franchise_code`, včetně mezipaměti spojů a stavu
 * poskytovatelů. Místní plánovače (`otp_transmodel`) se obracejí výhradně na
 * aktivní verzi grafu, takže se nikdy nepošle dotaz na napůl importovaný feed.
 */
final class TransportRepository
{
    /**
     * @param  \PDO   $db     Připojení k databázi.
     * @param  string $tenant Kód okurku, kterému patří všechny dotazy.
     * @return void
     */
    public function __construct(public readonly \PDO $db, public readonly string $tenant)
    {
    }

    /**
     * Provede výběrový dotaz a vrátí všechny řádky.
     *
     * @param  string               $sql    Výběrový dotaz včetně `franchise_code` podmínky.
     * @param  list<mixed>          $params Parametry dotazu.
     * @return list<array<string, mixed>> Řádky jako asociativní pole.
     * @throws \PDOException Při chybě dotazu.
     */
    public function rows(string $sql, array $params = []): array
    {
        $q = $this->db->prepare($sql);
        $q->execute($params);
        return $q->fetchAll(\PDO::FETCH_ASSOC);
    }
    /**
     * Provede změnový dotaz.
     *
     * @param  string      $sql    Dotaz včetně `franchise_code` podmínky.
     * @param  list<mixed> $params Parametry dotazu.
     * @return int                Počet ovlivněných řádků.
     * @throws \PDOException Při chybě dotazu.
     */
    public function execute(string $sql, array $params = []): int
    {
        $q = $this->db->prepare($sql);
        $q->execute($params);
        return $q->rowCount();
    }
    /**
     * Načte zveřejněné poskytovatele okurku a doplní stav jejich grafu.
     *
     * U lokálních plánovačů se adresa nahradí koncovým bodem aktivní verze a
     * doplní se období platnosti; neaktivovaný graf se označí jako nepřipravený.
     *
     * @return list<array<string, mixed>> Řádky poskytovatelů.
     * @throws \JsonException Pokud uložená konfigurace není platný JSON.
     */
    public function providers(): array
    {
        $rows = $this->rows('SELECT * FROM transport_provider WHERE franchise_code=? AND published=1', [$this->tenant]);
        // An OTP endpoint is immutable per activated graph version. Never route against a half-imported feed.
        foreach ($rows as &$r) {
            $c = json_decode($r['config'], true, 32, JSON_THROW_ON_ERROR);
            if ($r['adapter'] === 'otp_transmodel' && isset($c['feed_code'])) {
                $v = $this->activeFeed($c['feed_code']);
                $c['url'] = $v['graph_url'] ?? ($c['url'] ?? '');
                $c['graph_ready'] = $v !== null && !empty($v['graph_url']);
                $c['valid_from'] = $v['valid_from'] ?? null;
                $c['valid_until'] = $v['valid_until'] ?? null;
                $c['graph_version'] = $v['id'] ?? null;
                $r['config'] = json_encode($c, JSON_THROW_ON_ERROR);
            }
        }
        return $rows;
    }
    /**
     * Rezervuje poskytovatele pro jeden požadavek podle limitu na minimální interval.
     *
     * @param  string $code           Kód poskytovatele.
     * @param  int    $minIntervalMs  Nejmenší povolený odstup mezi požadavky v milisekundách.
     * @return bool                   true, pokud je poskytovatel právě získán a smí se volat.
     */
    public function acquireProvider(string $code, int $minIntervalMs = 0): bool
    {
        return $this->execute(
            'UPDATE transport_provider SET probe_until=IF(open_until IS NOT NULL,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 SECOND),probe_until),next_request_at=DATE_ADD(UTC_TIMESTAMP(3),INTERVAL ? MICROSECOND)
            WHERE franchise_code=? AND code=? AND published=1 AND (open_until IS NULL OR open_until<=UTC_TIMESTAMP()) AND (probe_until IS NULL OR probe_until<=UTC_TIMESTAMP()) AND (next_request_at IS NULL OR next_request_at<=UTC_TIMESTAMP(3))',
            [max(1, $minIntervalMs * 1000),$this->tenant,$code]
        ) === 1;
    }
    /**
     * Resetuje stav poskytovatele po úspěšném požadavku.
     *
     * @param  string $code Kód poskytovatele.
     * @return void         Vedlejší efekt: vynulování počtu selhání a uvolnění období nedostupnosti.
     */
    public function providerSuccess(string $code): void
    {
        $this->execute('UPDATE transport_provider SET failure_count=0,open_until=NULL,probe_until=NULL WHERE franchise_code=? AND code=?', [$this->tenant,$code]);
    }
    /**
     * Zapíše selhání poskytovatele a případně jej dočasně odstaví.
     *
     * Po třetím selhání (nebo při doporučeném čekání od zdroje) se nastaví
     * `open_until`; odstavení trvá 30–3600 sekund.
     *
     * @param  string   $code       Kód poskytovatele.
     * @param  int|null $retryAfter Doporučené čekání v sekundách od zdroje.
     * @return void                 Vedlejší efekt: zvýšení počtu selhání a případné odstavení.
     */
    public function providerFailure(string $code, ?int $retryAfter = null): void
    {
        $seconds = max(30, min(3600, $retryAfter ?? 30));
        $this->execute('UPDATE transport_provider SET open_until=IF(failure_count>=2 OR ?=1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),NULL),failure_count=failure_count+1,probe_until=NULL WHERE franchise_code=? AND code=?', [$retryAfter !== null ? 1 : 0,$seconds,$this->tenant,$code]);
    }
    /**
     * Uloží spojení do mezipaměti a doplní mu veřejné ID a čas vypršení.
     *
     * @param  array<string, mixed> $journey Spojení k uložení.
     * @param  int                   $ttl     Doba platnosti v sekundách.
     * @return array<string, mixed>          Spojení s přidaným `id` a `expires_at`.
     * @throws \JsonException               Pokud spojení nelze serializovat.
     */
    public function cacheJourney(array $journey, int $ttl = 900): array
    {
        $id = bin2hex(random_bytes(16));
        $expires = gmdate('Y-m-d H:i:s', time() + $ttl);
        $journey['id'] = $id;
        $journey['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', time() + $ttl);
        $this->execute('INSERT INTO transport_journey_cache (franchise_code,id,payload,expires_at) VALUES (?,?,?,?)', [$this->tenant,$id,json_encode($journey, JSON_THROW_ON_ERROR),$expires]);
        return $journey;
    }
    /**
     * Načte spojení z mezipaměti okurku.
     *
     * @param  string $id ID spojení (32 hex znaků).
     * @return array<string, mixed> Uložené spojení.
     * @throws TransportException 'not_found' (404) při neplatném tvaru ID,
     *                            'expired_journey' (404), pokud vypršelo nebo
     *                            patří jinému okurku.
     * @throws \JsonException    Pokud uložená data nejsou platný JSON.
     */
    public function journey(string $id): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new TransportException('not_found', 'Journey not found.', 404);
        }
        $r = $this->rows('SELECT payload FROM transport_journey_cache WHERE franchise_code=? AND id=? AND expires_at>UTC_TIMESTAMP()', [$this->tenant,$id]);
        if (!$r) {
            throw new TransportException('expired_journey', 'Journey expired or not found; search again.', 404);
        }
        return json_decode($r[0]['payload'], true, 64, JSON_THROW_ON_ERROR);
    }
    /**
     * Načte aktivní verzi feedu včetně poskytovatele a časového pásma.
     *
     * @param  string $code Kód feedu.
     * @return array<string, mixed>|null Aktivní verze, nebo null.
     */
    public function activeFeed(string $code): ?array
    {
        return $this->rows('SELECT v.*,f.provider_code,f.timezone FROM transport_feed f JOIN transport_feed_version v ON v.franchise_code=f.franchise_code AND v.id=f.active_version_id AND v.feed_code=f.code WHERE f.franchise_code=? AND f.code=? AND v.status=\'active\'', [$this->tenant,$code])[0] ?? null;
    }
    /**
     * Vyhledá zastávky v aktivních feedech okurku podle začátku názvu.
     *
     * Zastávky z neplatného feedu (konec platnosti v minulosti) se nehledají a
     * volitelná země se filtruje přes pokrytí poskytovatele.
     *
     * @param  string      $query   Hledaný začátek názvu; `%` a `_` jsou escapovány.
     * @param  int         $limit   Maximální počet výsledků (1–50).
     * @param  string|null $country Kód země ISO 3166-1 alpha-2, nebo null.
     * @return list<array<string, mixed>> Zastávky s veřejným ID.
     */
    public function places(string $query, int $limit, ?string $country): array
    {
        $sql = "SELECT s.*,f.provider_code,f.timezone FROM transport_feed f JOIN transport_feed_version v ON v.franchise_code=f.franchise_code AND v.id=f.active_version_id JOIN transport_stop s ON s.franchise_code=v.franchise_code AND s.version_id=v.id JOIN transport_provider p ON p.franchise_code=f.franchise_code AND p.code=f.provider_code WHERE f.franchise_code=? AND v.status='active' AND v.valid_until>=UTC_DATE() AND s.name LIKE ? ESCAPE '!'";
        $params = [$this->tenant,str_replace(['!','%','_'], ['!!','!%','!_'], $query).'%'];
        if ($country !== null) {
            $sql .= " AND JSON_CONTAINS(p.coverage,JSON_OBJECT('country',?))";
            $params[] = $country;
        }
        $sql .= ' ORDER BY s.name,s.external_id LIMIT '.max(1, min(50, $limit));
        return array_map(fn ($s) => $this->stopRow($s), $this->rows($sql, $params));
    }
    /**
     * Načte jednu zastávku z aktivního feedu poskytovatele.
     *
     * @param  string $provider Kód poskytovatele.
     * @param  string $external Externí ID zastávky.
     * @return array<string, mixed>|null Zastávka s veřejným ID, nebo null.
     */
    public function stop(string $provider, string $external): ?array
    {
        $rows = $this->rows("SELECT s.*,f.provider_code,f.timezone FROM transport_feed f JOIN transport_feed_version v ON v.franchise_code=f.franchise_code AND v.id=f.active_version_id JOIN transport_stop s ON s.franchise_code=v.franchise_code AND s.version_id=v.id WHERE f.franchise_code=? AND f.provider_code=? AND s.external_id=? AND v.status='active' AND v.valid_until>=UTC_DATE()", [$this->tenant,$provider,$external]);
        return isset($rows[0]) ? $this->stopRow($rows[0]) : null;
    }
    /**
     * Převede řádek zastávky na veřejný tvar.
     *
     * @param  array<string, mixed> $s Řádek `transport_stop` doplněný o poskytovatele a časové pásmo.
     * @return array<string, mixed> Zastávka s neprůhledným ID a zdrojem `schedule`.
     */
    private function stopRow(array $s): array
    {
        return ['id' => ResourceIdCodec::encode($this->tenant, $s['provider_code'], 'stop', $s['external_id']),'name' => $s['name'],'lat' => $s['lat'] !== null ? (float)$s['lat'] : null,'lon' => $s['lon'] !== null ? (float)$s['lon'] : null,'platform' => $s['platform'],'timezone' => $s['timezone'],'source_mode' => 'schedule'];
    }
    /**
     * Načte spoj včetně zastávek, pokud v daném datu skutečně jede.
     *
     * Platnost se ověří podle kalendáře, dne v týdnu a případné výjimky služby;
     * spoj mimo platnost se nevrátí. Časy se převádí do časového pásma dopravce.
     *
     * @param  string $provider Kód poskytovatele.
     * @param  string $external Externí ID spoje.
     * @param  string $date     Datum ve formátu `Y-m-d`.
     * @return array<string, mixed>|null Spoj se zastávkami, nebo null.
     * @throws \JsonException Pokud uložená data trasy nejsou platný JSON.
     */
    public function trip(string $provider, string $external, string $date): ?array
    {
        $rows = $this->rows("SELECT t.*,r.name line,r.mode,r.data route_data,o.timezone FROM transport_feed f JOIN transport_feed_version v ON v.franchise_code=f.franchise_code AND v.id=f.active_version_id JOIN transport_trip t ON t.franchise_code=v.franchise_code AND t.version_id=v.id JOIN transport_route r ON r.franchise_code=t.franchise_code AND r.version_id=t.version_id AND r.external_id=t.route_id JOIN transport_operator o ON o.franchise_code=r.franchise_code AND o.version_id=r.version_id AND o.external_id=r.operator_id WHERE f.franchise_code=? AND f.provider_code=? AND t.external_id=? AND v.status='active' AND v.valid_from<=? AND v.valid_until>=?", [$this->tenant,$provider,$external,$date,$date]);
        if (!$rows) {
            return null;
        } $trip = $rows[0];
        $version = $trip['version_id'];
        $service = $this->rows('SELECT * FROM transport_service WHERE franchise_code=? AND version_id=? AND external_id=?', [$this->tenant,$version,$trip['service_id']])[0];
        $exception = $this->rows('SELECT exception_type FROM transport_service_exception WHERE franchise_code=? AND version_id=? AND service_id=? AND service_date=?', [$this->tenant,$version,$trip['service_id'],$date])[0]['exception_type'] ?? null;
        $runs = $date >= ($service['start_date'] ?? '9999') && $date <= ($service['end_date'] ?? '0000') && ($service['weekdays'][(int)(new \DateTimeImmutable($date))->format('N') - 1] ?? '0') === '1';
        if ($exception !== null) {
            $runs = (int)$exception === 1;
        }
        if (!$runs) {
            return null;
        }
        $stops = $this->rows('SELECT st.*,s.name,s.lat,s.lon,s.platform FROM transport_stop_time st JOIN transport_stop s ON s.franchise_code=st.franchise_code AND s.version_id=st.version_id AND s.external_id=st.stop_id WHERE st.franchise_code=? AND st.version_id=? AND st.trip_id=? ORDER BY st.sequence', [$this->tenant,$version,$external]);
        $calls = [];
        foreach ($stops as $s) {
            /**
             * Převede sekundy od půlnoci na okamžik v časovém pásmu dopravce.
             *
             * @param  int|null $seconds Sekundy od půlnoci, nebo null pro neuvedený čas.
             * @return string|null         Čas ve formátu RFC3339, nebo null.
             */
            $clock = fn ($seconds) => $seconds === null ? null : ServiceTimeService::instant($date, (int)$seconds, $trip['timezone'])->format(DATE_RFC3339);
            $calls[] = ['stop' => ['id' => ResourceIdCodec::encode($this->tenant, $provider, 'stop', $s['stop_id']),'name' => $s['name'],'lat' => (float)$s['lat'],'lon' => (float)$s['lon'],'platform' => $s['platform'],'timezone' => $trip['timezone']],
                'scheduled_arrival' => $clock($s['arrival_seconds']),'scheduled_departure' => $clock($s['departure_seconds']),'expected_arrival' => null,'expected_departure' => null,'realtime' => false,'cancelled' => null];
        }
        $frequency = (bool)$this->rows('SELECT 1 FROM transport_frequency WHERE franchise_code=? AND version_id=? AND trip_id=? LIMIT 1', [$this->tenant,$version,$external]);
        return ['id' => ResourceIdCodec::encode($this->tenant, $provider, 'trip', $external, $date),'service_date' => $date,'line' => ['id' => ResourceIdCodec::encode($this->tenant, $provider, 'line', $trip['route_id']),'name' => $trip['line'],'code' => json_decode($trip['route_data'], true)['route_short_name'] ?? null,'mode' => $trip['mode']],'stops' => $calls,'frequency_based' => $frequency,'source_mode' => 'schedule'];
    }
}
