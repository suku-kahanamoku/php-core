<?php

declare(strict_types=1);

namespace App\Modules\Transport\Import;

use App\Modules\Transport\{ConfigurationService,TransportException};
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\HttpRequest;
use App\Modules\Transport\Repositories\TransportRepository;

/**
 * Verzované adresáře grafů a neměnné koncové body umožňují atomickou aktivaci
 * i návrat na předchozí verzi.
 *
 * Export ověří kontrolní součet snapshotu a zkopíruje archiv do nového,
 * dosud nepoužitého adresáře. Aktivace navíc ověří manifest, potvrzení sestavení
 * a dvě prostorově shodné zastávky přes GraphQL dotaz na plánovač; adresa
 * koncového bodu smí patřit jen jedné verzi, jinak by šlo vrátit zpět k jinému
 * sestavení.
 */
final class GraphService
{
    /**
     * @param  TransportRepository $r    Repozitář daného okurku.
     * @param  HttpClient          $http Sdílený HTTP klient pro kontrolu plánovače.
     * @return void
     */
    public function __construct(private readonly TransportRepository $r, private readonly HttpClient $http)
    {
    }

    /**
     * Vyexportuje snapshot feedu do nového adresáře pro sestavení grafu.
     *
     * @param  int    $version   ID verze feedu v jednom ze stavů `ready`, `active` nebo `retired`.
     * @param  string $directory Cílový adresář, který ještě nesmí existovat.
     * @return array<string, mixed>         Manifest s kódem okurku, feedem, verzí, součtem a ID feedu pro plánovač.
     * @throws TransportException          'invalid_snapshot', 'directory_exists',
     *                                    'export_failed' (500) nebo chyba konfigurace.
     */
    public function export(int $version, string $directory): array
    {
        $v = $this->version($version);
        if (!in_array($v['status'], ['ready','active','retired'], true) || !is_file($v['archive_path']) || hash_file('sha256', $v['archive_path']) !== $v['checksum']) {
            throw new TransportException('invalid_snapshot', 'Feed snapshot is missing or changed.');
        }
        if (file_exists($directory)) {
            throw new TransportException('directory_exists', 'Use a new immutable build directory.');
        }
        if (!mkdir($directory, 0700, true)) {
            throw new \RuntimeException('Cannot create graph directory.');
        }
        $config = json_decode($v['config'], true, 32, JSON_THROW_ON_ERROR);
        $feedId = $config['otp_feed_id'] ?? $v['feed_code'];
        if (!copy($v['archive_path'], $directory.'/timetable.gtfs.zip')) {
            throw new TransportException('export_failed', 'Cannot copy the feed snapshot.', 500);
        }
        $manifest = ['tenant' => $this->r->tenant,'feed' => $v['feed_code'],'version_id' => $version,'checksum' => $v['checksum'],'otp_feed_id' => $feedId];
        file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/build-config.json', json_encode(['transitModelTimeZone' => $v['timezone'], 'transitServiceStart' => $v['valid_from'], 'transitServiceEnd' => $v['valid_until'], 'gtfs' => [['source' => 'timetable.gtfs.zip','feedId' => $feedId]],'osm' => [['source' => 'streets.osm.pbf']]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return $manifest;
    }
    /**
     * Ověří sestavený graf a atomicky jej aktivuje pro feed okurku.
     *
     * @param  int    $version     ID verze feedu.
     * @param  string $manifestPath Cesta k `manifest.json` sestavení.
     * @param  string $url         Vnitřní adresa plánovače; musí být jedinečná pro tuto verzi.
     * @return void               Vedlejší efekt: přechod stavů verzí a `active_version_id` feedu v transakci.
     * @throws TransportException 'invalid_snapshot', 'invalid_manifest', 'expired_snapshot',
     *                            'graph_not_ready' (503), 'mutable_graph_endpoint'
     *                            nebo chyba konfigurace koncové adresy.
     */
    public function activate(int $version, string $manifestPath, string $url): void
    {
        ConfigurationService::url($url, true);
        $v = $this->version($version);
        if (!in_array($v['status'], ['ready','active','retired'], true)) {
            throw new TransportException('invalid_snapshot', 'Snapshot is not ready.');
        }
        $m = json_decode(file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR);
        $directory = dirname($manifestPath);
        if (($m['tenant'] ?? null) !== $this->r->tenant || ($m['version_id'] ?? null) !== $version || ($m['checksum'] ?? null) !== $v['checksum'] || !is_file($directory.'/graph.obj') || !is_file($directory.'/build-receipt.json')) {
            throw new TransportException('invalid_manifest', 'Graph manifest does not match the imported version.');
        }
        $receipt = json_decode(file_get_contents($directory.'/build-receipt.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($receipt['manifest_sha256'] ?? '') !== hash_file('sha256', $manifestPath) || ($receipt['graph_sha256'] ?? '') !== hash_file('sha256', $directory.'/graph.obj') || hash_file('sha256', $directory.'/timetable.gtfs.zip') !== $v['checksum']) {
            throw new TransportException('invalid_manifest', 'Graph build receipt does not match its inputs.');
        }
        if ($v['valid_until'] < gmdate('Y-m-d')) {
            throw new TransportException('expired_snapshot', 'Cannot activate an expired timetable.');
        }
        // Two spatially verified stops are a readiness probe. The operator must serve THIS directory at an immutable URL.
        $stops = $this->r->rows('SELECT external_id,lat,lon FROM transport_stop WHERE franchise_code=? AND version_id=? AND location_type=0 AND lat IS NOT NULL ORDER BY external_id LIMIT 2', [$this->r->tenant,$version]);
        if (count($stops) < 2) {
            throw new TransportException('invalid_snapshot', 'Not enough stops to probe graph.');
        }
        $requests = [];
        foreach ($stops as $i => $s) {
            $requests[(string)$i] = new HttpRequest($url, 'POST', [], ['query' => 'query($id:String!){quay(id:$id){id latitude longitude}}','variables' => ['id' => $m['otp_feed_id'].':'.$s['external_id']]]);
        }
        foreach ($this->http->sendAll($requests) as $i => $response) {
            $stop = \App\Modules\Transport\Providers\UpstreamResponseMapper::json($response)['data']['quay'] ?? null;
            if (!$stop || abs($stop['latitude'] - $stops[$i]['lat']) > 0.0001 || abs($stop['longitude'] - $stops[$i]['lon']) > 0.0001) {
                throw new TransportException('graph_not_ready', 'Graph does not expose the expected stops.', 503);
            }
        }
        $db = $this->r->db;
        $db->beginTransaction();
        try {
            $this->r->rows('SELECT code FROM transport_feed WHERE franchise_code=? AND code=? FOR UPDATE', [$this->r->tenant,$v['feed_code']]);
            // Reusing an active endpoint for a different build breaks rollback and in-flight consistency.
            $other = $this->r->rows('SELECT id FROM transport_feed_version WHERE franchise_code=? AND graph_url=? AND id<>?', [$this->r->tenant,$url,$version]);
            if ($other) {
                throw new TransportException('mutable_graph_endpoint', 'Use a distinct internal URL for each graph version.');
            }
            $this->r->execute("UPDATE transport_feed_version SET status='retired' WHERE franchise_code=? AND feed_code=? AND status='active' AND id<>?", [$this->r->tenant,$v['feed_code'],$version]);
            $this->r->execute("UPDATE transport_feed_version SET status='active',graph_url=? WHERE franchise_code=? AND id=?", [$url,$this->r->tenant,$version]);
            $this->r->execute('UPDATE transport_feed SET active_version_id=? WHERE franchise_code=? AND code=?', [$version,$this->r->tenant,$v['feed_code']]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
    /**
     * Načte verzi feedu včetně konfigurace a časového pásma feedu.
     *
     * @param  int $id ID verze.
     * @return array<string, mixed> Řádek verze doplněný o `config` a `timezone`.
     * @throws TransportException 'not_found' (404), pokud verze v okurku neexistuje.
     */
    private function version(int $id): array
    {
        return $this->r->rows('SELECT v.*,f.config,f.timezone FROM transport_feed_version v JOIN transport_feed f ON f.franchise_code=v.franchise_code AND f.code=v.feed_code WHERE v.franchise_code=? AND v.id=?', [$this->r->tenant,$id])[0] ?? throw new TransportException('not_found', 'Feed version not found.', 404);
    }
}
