<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\SyncException;
use App\Modules\Etymolog\EtymologSnapshotRepository;
use App\Modules\Http\Contracts\HttpClient;

/** Official national surname counts, separate male/female populations; never inferred ethnicity. */
final class PolandPeselProvider implements BatchProvider
{
    private const API = 'https://api.dane.gov.pl/1.4/';
    public function __construct(private readonly HttpClient $http, private readonly ?EtymologSnapshotRepository $snapshots = null) {}

    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if ($language !== 'pl' || !in_array($kind, ['surname_male', 'surname_female'], true) || $limit < 1 || $limit > 500) { throw new SyncException('invalid_provider_configuration'); }
        $state = $cursor === null ? null : json_decode($cursor, true);
        if ($cursor !== null && (!is_array($state) || !is_int($state['resource'] ?? null) || $state['resource'] < 1 || !is_int($state['offset'] ?? null) || $state['offset'] < 1 || $state['offset'] > 2000000 || !is_string($state['hash'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $state['hash']))) { throw new SyncException('invalid_provider_cursor'); }
        if (isset($state['position']) && (!is_int($state['position']) || $state['position'] < 1 || $state['position'] > 25000000)) { throw new SyncException('invalid_provider_cursor'); }
        $dataset = ProviderHttp::json($this->http, self::API.'datasets/1681');
        $rights = $dataset['data']['attributes'] ?? [];
        if (($dataset['data']['id'] ?? null) !== '1681' || ($rights['license_name'] ?? null) !== 'CC0 1.0') { throw new SyncException('upstream_license_changed'); }
        foreach ($rights as $key => $value) {
            if ((str_starts_with($key, 'license_condition_') || $key === 'current_condition_descriptions' || $key === 'license_description') && !empty($value)) { throw new SyncException('upstream_license_changed'); }
        }
        $prefix = $kind === 'surname_male' ? 'Nazwiska męskie - stan na ' : 'Nazwiska żeńskie - stan na ';
        if ($state === null) {
            $list = ProviderHttp::json($this->http, self::API.'datasets/1681/resources?page=1&per_page=20&sort=-data_date');
            $candidates = array_values(array_filter($list['data'] ?? [], static fn ($r) => str_starts_with($r['attributes']['title'] ?? '', $prefix)));
            usort($candidates, static fn ($a, $b) => strcmp($b['attributes']['data_date'] ?? '', $a['attributes']['data_date'] ?? '') ?: ((int)$b['id'] <=> (int)$a['id']));
            if ($candidates === []) { throw new SyncException('national_surname_resource_missing'); }
            $resourceId = (int)$candidates[0]['id'];
        } else { $resourceId = $state['resource']; }
        $resource = ProviderHttp::json($this->http, self::API.'resources/'.$resourceId)['data'] ?? [];
        $a = $resource['attributes'] ?? [];
        $date = $a['data_date'] ?? '';
        $d = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (($resource['relationships']['dataset']['data']['id'] ?? null) !== '1681' || (string)($resource['id'] ?? '') !== (string)$resourceId ||
            !$d || $d->format('Y-m-d') !== $date || ($a['title'] ?? '') !== $prefix.$date || ($a['contains_protected_data'] ?? true) !== false) {
            throw new SyncException('invalid_statistics_resource');
        }
        $csvUrl = $a['csv_file_url'] ?? '';
        $url = is_string($csvUrl) ? parse_url($csvUrl) : false;
        if (!$url || ($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== 'api.dane.gov.pl' || isset($url['user'], $url['pass']) || isset($url['user']) || isset($url['port']) || isset($url['query']) || isset($url['fragment']) ||
            !preg_match('~^/media/resources/[0-9]{8}/[^/]+\.csv$~D', $url['path'] ?? '') || str_contains(rawurldecode($url['path']), '..') || str_contains(rawurldecode(substr($url['path'], 26)), '/')) {
            throw new SyncException('statistics_download_url_not_allowed');
        }
        $csv = $state === null ? null : $this->snapshots?->get($csvUrl, $state['hash']);
        $downloaded = $csv === null;
        if ($downloaded) { $csv = ProviderHttp::get($this->http, $csvUrl, 25000000)->body; }
        if (!preg_match('//u', $csv)) { throw new SyncException('statistics_encoding_changed'); }
        $hash = hash('sha256', $csv);
        if ($state !== null && $state['hash'] !== $hash) { throw new SyncException('statistics_snapshot_changed'); }
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) { throw new SyncException('statistics_buffer_failed'); }
        try {
            fwrite($stream, $csv); rewind($stream);
            $header = fgetcsv($stream, 0, ',', '"', '');
            if (is_array($header)) { $header[0] = ltrim($header[0], "\xEF\xBB\xBF"); }
            if ($header !== ['Nazwisko aktualne', 'Liczba']) { throw new SyncException('statistics_schema_changed'); }
            $offset = $state['offset'] ?? 0;
            if (isset($state['position'])) {
                $position = $state['position'];
                if ($position < ftell($stream) || $position >= strlen($csv) || !in_array($csv[$position - 1], ["\n", "\r"], true) || fseek($stream, $position) !== 0) {
                    throw new SyncException('invalid_provider_cursor');
                }
            } else {
                // Existing cursors traverse preceding rows once, then switch to a byte position.
                for ($i = 0; $i < $offset; ++$i) {
                    if (fgetcsv($stream, 0, ',', '"', '') === false) { throw new SyncException('invalid_provider_cursor'); }
                }
            }
            $items = []; $eof = false;
            for ($i = 0; $i < $limit; ++$i) {
                $row = fgetcsv($stream, 0, ',', '"', '');
                if ($row === false) { $eof = true; break; }
                if (count($row) !== 2 || !is_string($row[0]) || trim($row[0]) === '' || strlen($row[0]) > 255 || str_contains($row[0], "\0") || !is_string($row[1]) || !preg_match('/^[0-9]+$/D', $row[1]) || (float)$row[1] > 2147483647) { throw new SyncException('invalid_statistics_row'); }
                $name = $row[0]; $count = (int)$row[1];
                $sourceUrl = 'https://dane.gov.pl/pl/dataset/1681/resource/'.$resourceId;
                $sex = $kind === 'surname_male' ? 'male' : 'female';
                $notes = 'PESEL: living registered persons, national total, '.$sex.', as of '.$date.'. Dataset excludes deceased persons and single-occurrence surnames according to publisher metadata; this is not ethnicity, births or total across sexes.';
                $items[] = [
                    'external_id' => $resourceId.':'.hash('sha256', $name), 'revision' => $hash,
                    'name' => $name, 'kind' => 'surname', 'language' => null, 'country_code' => 'PL',
                    'source_key' => (string)$resourceId, 'source_title' => $a['title'], 'source_url' => $sourceUrl,
                    'license' => 'CC0-1.0', 'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
                    'attribution' => 'Ministerstwo Cyfryzacji – rejestr PESEL; dane.gov.pl', 'notes' => $notes,
                    'occurrence' => ['country_code' => 'PL', 'observed_year' => (int)substr($date, 0, 4), 'observed_on' => $date,
                        'sex' => $sex, 'measure' => 'living_persons', 'count' => $count, 'original_spelling' => $name,
                        'locator' => 'CSV row '.($offset + $i + 2), 'notes' => $notes],
                    'payload' => ['dataset_id' => 1681, 'resource_id' => $resourceId, 'name' => $name, 'count' => $count, 'sex' => $sex,
                        'observed_on' => $date, 'measure' => 'living_persons', 'csv_url' => $csvUrl, 'csv_sha256' => $hash,
                        'row' => $offset + $i + 2, 'license_evidence' => ['license_name' => $rights['license_name'], 'conditions' => $rights['current_condition_descriptions'] ?? []]],
                ];
            }
            $position = ftell($stream);
            if (!$eof) { $eof = fgetcsv($stream, 0, ',', '"', '') === false; }
        } finally { fclose($stream); }
        if (!$eof && $downloaded) { $this->snapshots?->put($csvUrl, $csv); }
        return ['items' => $items, 'cursor' => $eof ? null : json_encode(['resource' => $resourceId, 'offset' => $offset + count($items), 'hash' => $hash, 'position' => $position], JSON_THROW_ON_ERROR), 'complete' => $eof];
    }
}
