<?php

declare(strict_types=1);

namespace App\Modules\Transport\Import;

use App\Modules\Transport\Repositories\TransportRepository;
use App\Modules\Transport\TransportException;

/**
 * Synchronizace importovaného feedu (GTFS) včetně verze a platnosti.
 *
 * Běh drží databázový zámek `GET_LOCK`, takže dva souběžné importy téhož feedu
 * se nepotkají. Nejprve se stáhne archiv do dočasného souboru a porovná se
 * kontrolní součet: nezměněný feed se označí `unchanged` a nic neimportuje.
 * Nová verze se importuje v transakci; při chybě zůstává předchozí data
 * aktivní a běh končí stavem `failed`.
 */
final class FeedSyncService
{
    /**
     * @param  TransportRepository                     $repository Repozitář daného okurku.
     * @param  string                                  $storage   Adresář pro archivy (právo 0700, soubory 0600).
     * @param  \App\Modules\Http\Contracts\HttpClient|null $http      Klient pro testy; jinak `HttpModule::client()`.
     * @return void
     */
    public function __construct(private readonly TransportRepository $repository, private readonly string $storage, private readonly ?\App\Modules\Http\Contracts\HttpClient $http = null)
    {
    }

    /**
     * Stáhne a naimportuje feed, nebo potvrdí, že se nezměnil.
     *
     * @param  string      $feedCode     Kód feedu okurku.
     * @param  string|null $localArchive Místní GTFS archiv místo stahování (testy), max. 500 MB.
     * @return array{version_id: int, status: string, checksum?: string, counts?: array<string, int>, valid_from?: string, valid_until?: string}
     *         Identifikátor nové verze a její stav.
     * @throws TransportException 'not_found' (404), 'sync_locked' (409), 'invalid_feed_url',
     *                            'download_failed' (503), 'empty_calendar' nebo chyba importu.
     */
    public function sync(string $feedCode, ?string $localArchive = null): array
    {
        $r = $this->repository;
        $db = $r->db;
        $feed = $r->rows('SELECT * FROM transport_feed WHERE franchise_code=? AND code=?', [$r->tenant,$feedCode])[0] ?? throw new TransportException('not_found', 'Feed not configured.', 404);
        $lock = 'tram:'.substr(hash('sha256', $r->tenant.':'.$feedCode), 0, 50);
        if ((int)$r->rows('SELECT GET_LOCK(?,0) acquired', [$lock])[0]['acquired'] !== 1) {
            throw new TransportException('sync_locked', 'Feed synchronization is already running.', 409);
        }
        $run = null;
        $temp = null;
        try {
            $r->execute("INSERT INTO transport_sync_run(franchise_code,feed_code,status) VALUES (?,?,'running')", [$r->tenant,$feedCode]);
            $run = (int)$db->lastInsertId();
            if (!is_dir($this->storage) && !mkdir($this->storage, 0700, true)) {
                throw new \RuntimeException('Cannot create storage directory.');
            }
            $temp = tempnam($this->storage, 'download-');
            if ($localArchive !== null) {
                if (!is_file($localArchive) || filesize($localArchive) > 500000000 || !copy($localArchive, $temp)) {
                    throw new TransportException('download_failed', 'Cannot read local GTFS archive.');
                }
            } else {
                $this->download($feed['url'], $temp);
            }
            $hash = hash_file('sha256', $temp);
            $existing = $r->rows('SELECT id,status FROM transport_feed_version WHERE franchise_code=? AND feed_code=? AND checksum=?', [$r->tenant,$feedCode,$hash]);
            if ($existing) {
                $r->execute("UPDATE transport_sync_run SET status='unchanged',version_id=?,finished_at=UTC_TIMESTAMP() WHERE franchise_code=? AND id=?", [$existing[0]['id'],$r->tenant,$run]);
                return ['version_id' => (int)$existing[0]['id'],'status' => 'unchanged'];
            }
            $archive = $this->storage.'/'.hash('sha256', $r->tenant.':'.$feedCode).'-'.$hash.'.zip';
            if (!rename($temp, $archive)) {
                throw new \RuntimeException('Cannot persist feed archive.');
            } $temp = null;
            chmod($archive, 0600);
            $db->beginTransaction();
            $r->execute('INSERT INTO transport_feed_version(franchise_code,feed_code,checksum,archive_path) VALUES (?,?,?,?)', [$r->tenant,$feedCode,$hash,$archive]);
            $version = (int)$db->lastInsertId();
            $counts = (new GtfsImportService($db, $r->tenant))->import($archive, $version);
            $dates = $r->rows('SELECT MIN(start_date) valid_from,MAX(end_date) valid_until FROM (SELECT start_date,end_date FROM transport_service WHERE franchise_code=? AND version_id=? UNION ALL SELECT service_date,service_date FROM transport_service_exception WHERE franchise_code=? AND version_id=? AND exception_type=1) d', [$r->tenant,$version,$r->tenant,$version])[0];
            if (!$dates['valid_from'] || !$dates['valid_until']) {
                throw new TransportException('empty_calendar', 'No valid service dates.');
            }
            $r->execute("UPDATE transport_feed_version SET status='ready',valid_from=?,valid_until=?,row_counts=? WHERE franchise_code=? AND id=?", [$dates['valid_from'],$dates['valid_until'],json_encode($counts, JSON_THROW_ON_ERROR),$r->tenant,$version]);
            $r->execute("UPDATE transport_sync_run SET status='ready',version_id=?,finished_at=UTC_TIMESTAMP() WHERE franchise_code=? AND id=?", [$version,$r->tenant,$run]);
            $db->commit();
            return ['version_id' => $version,'status' => 'ready','checksum' => $hash,'counts' => $counts,'valid_from' => $dates['valid_from'],'valid_until' => $dates['valid_until']];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($run !== null) {
                $r->execute("UPDATE transport_sync_run SET status='failed',error_code=?,finished_at=UTC_TIMESTAMP() WHERE franchise_code=? AND id=?", [$e instanceof TransportException ? $e->reason : 'import_failed',$r->tenant,$run]);
            }
            throw $e;
        } finally {
            if ($temp !== null && is_file($temp)) {
                unlink($temp);
            }
            $r->rows('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }
    /**
     * Stáhne GTFS archiv přímo do souboru přes sdílený HTTP klient.
     *
     * @param  string $url    Adresa feedu; musí být HTTPS.
     * @param  string $target Cílový soubor (`sink`).
     * @return void           Vedlejší efekt: zápis archivu na disk.
     * @throws TransportException 'invalid_feed_url' nebo 'download_failed' (503);
     *                            při chybě zůstávají předchozí data aktivní.
     */
    private function download(string $url, string $target): void
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new TransportException('invalid_feed_url', 'Feed URLs must use HTTPS.');
        }
        $response = ($this->http ?? \App\Modules\Http\HttpModule::client())->send(new \App\Modules\Http\HttpRequest(
            $url,
            timeoutMs: 180000,
            maxBytes: 500000000,
            connectTimeoutMs: 10000,
            sink: $target,
        ));
        if ($response->error !== null || $response->status !== 200) {
            throw new TransportException('download_failed', 'GTFS download failed; previous data remain active.', 503);
        }
    }
}
