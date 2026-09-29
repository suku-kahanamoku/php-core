<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\Readers\NameStatisticsXlsxReader;
use App\Modules\Etymolog\SyncException;
use App\Modules\Etymolog\EtymologSnapshotRepository;
use App\Modules\Http\Contracts\HttpClient;

/** Reviewed fixed annual release; new years require adding a separately verified release. */
final class CsuBabyNamesProvider implements BatchProvider
{
    public const FILE_URL = 'https://csu.gov.cz/docs/107508/0a6170f4-bc53-7d35-afe2-3d5fcd0acb47/data_detska_jmena_top_100_cesko_2025.xlsx?version=1.0';
    public const SOURCE_URL = 'https://csu.gov.cz/produkty/viktorie-byla-vubec-poprve-nejoblibenejsi-jakub-prvenstvi-obhajil-tesne';
    public const TERMS_URL = 'https://csu.gov.cz/podminky_pro_vyuzivani_a_dalsi_zverejnovani_statistickych_udaju_csu';
    public function __construct(private readonly HttpClient $http, private readonly ?EtymologSnapshotRepository $snapshots = null) {}

    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if ($language !== 'cs' || $kind !== 'births_2025' || $limit < 1 || $limit > 500) { throw new SyncException('invalid_provider_configuration'); }
        $state = $cursor === null ? null : json_decode($cursor, true);
        if ($cursor !== null && (!is_array($state) || !is_int($state['offset'] ?? null) || $state['offset'] < 1 || $state['offset'] > 300 || !is_string($state['hash'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $state['hash']))) { throw new SyncException('invalid_provider_cursor'); }
        $terms = ProviderHttp::get($this->http, self::TERMS_URL, 2000000)->body;
        if (!preg_match('~https://creativecommons\.org/licenses/by/4\.0(?:/|["\'])~', $terms)) { throw new SyncException('upstream_license_changed'); }
        $bytes = $state === null ? null : $this->snapshots?->get(self::FILE_URL, $state['hash']);
        $downloaded = $bytes === null;
        if ($downloaded) { $bytes = ProviderHttp::get($this->http, self::FILE_URL, 2000000)->body; }
        $hash = hash('sha256', $bytes);
        if ($state !== null && $state['hash'] !== $hash) { throw new SyncException('statistics_snapshot_changed'); }
        $rows = (new NameStatisticsXlsxReader())->read($bytes);
        $offset = $state['offset'] ?? 0;
        if ($offset >= count($rows) && $offset !== 0) { throw new SyncException('invalid_provider_cursor'); }
        $items = [];
        foreach (array_slice($rows, $offset, $limit) as $row) {
            $notes = 'ČSÚ, děti narozené v Česku v roce 2025, TOP 100 pro každé pohlaví včetně shodného pořadí; nejde o všechny nositele jména. Původní zápis zachován; pořadí '.$row['rank'].'.';
            $items[] = ['external_id' => '2025:'.$row['sex'].':'.hash('sha256', $row['name']), 'revision' => $hash,
                'name' => $row['name'], 'kind' => 'given', 'language' => null, 'country_code' => 'CZ',
                'source_key' => 'births:2025:top100', 'source_title' => 'ČSÚ – dětská jména TOP 100, Česko 2025',
                'source_url' => self::SOURCE_URL, 'license' => 'CC-BY-4.0', 'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
                'attribution' => 'Český statistický úřad (ČSÚ), Dětská jména TOP 100 v Česku za rok 2025; převedeno z XLSX, výběr jména a počtu.', 'notes' => $notes,
                'occurrence' => ['country_code' => 'CZ', 'observed_year' => 2025, 'observed_on' => null, 'sex' => $row['sex'], 'measure' => 'births', 'count' => $row['count'], 'original_spelling' => $row['name'], 'locator' => ($row['sex'] === 'male' ? 'Chlapci' : 'Dívky').', pořadí '.$row['rank'], 'notes' => $notes],
                'payload' => $row + ['observed_year' => 2025, 'measure' => 'births', 'coverage' => 'top100_per_sex', 'xlsx_sha256' => $hash, 'file_url' => self::FILE_URL, 'license_evidence_url' => self::TERMS_URL, 'license_evidence_sha256' => hash('sha256', $terms)],
            ];
        }
        $next = $offset + count($items); $complete = $next >= count($rows);
        if (!$complete && $downloaded) { $this->snapshots?->put(self::FILE_URL, $bytes); }
        return ['items' => $items, 'cursor' => $complete ? null : json_encode(['offset' => $next, 'hash' => $hash], JSON_THROW_ON_ERROR), 'complete' => $complete];
    }
}
