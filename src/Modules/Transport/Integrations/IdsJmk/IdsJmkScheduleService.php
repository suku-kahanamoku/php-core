<?php
declare(strict_types=1);
namespace App\Modules\Transport\Integrations\IdsJmk;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\HttpRequest;
use App\Modules\Transport\Import\Gtfs\GtfsArchiveReader;
use App\Modules\Transport\Import\ServiceTimeService;
use App\Modules\Transport\Model\TransportException;

/** HTTP-validated static identity cache. Never stores telemetry; never serves it on network failure. */
final class IdsJmkScheduleService
{
    public function __construct(private readonly string $directory, private readonly string $url) {}

    public function resolve(array $reference, HttpClient $http): ?array
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new TransportException('invalid_configuration', 'Static identity cache is not writable.', 500);
        }
        $metadata = $this->read($this->directory.'/http.json');
        $headers = ['Accept' => '*/*'];
        $cached = isset($metadata['hash']) && preg_match('/^[a-f0-9]{64}$/D', $metadata['hash'])
            ? $this->directory.'/'.$metadata['hash'].'.zip' : null;
        if ($cached && is_file($cached)) {
            foreach (['etag' => 'If-None-Match','modified' => 'If-Modified-Since'] as $field => $header) {
                if (is_string($metadata[$field] ?? null) && strlen($metadata[$field]) < 256 && !preg_match('/[\r\n]/', $metadata[$field])) {
                    $headers[$header] = $metadata[$field];
                }
            }
        }
        $response = $http->sendAll(['schedule' => new HttpRequest($this->url, headers: $headers, timeoutMs: 6000, maxBytes: 30000000)])['schedule'];
        if ($response->status === 304 && $response->error === null && $cached && is_file($cached)) {
            $hash = $metadata['hash'];
        } elseif ($response->successful()) {
            $hash = hash('sha256', $response->body); $cached = $this->directory.'/'.$hash.'.zip';
            if (!is_file($cached)) { $this->write($cached, $response->body); }
            // Validate ZIP before publishing the validator. No paths are extracted.
            new GtfsArchiveReader($cached, 2000000);
            $this->write($this->directory.'/http.json', json_encode(['hash' => $hash, 'etag' => $response->header('ETag'), 'modified' => $response->header('Last-Modified')], JSON_THROW_ON_ERROR));
        } else {
            throw new TransportException('source_unavailable', 'Online schedule identity could not be verified.', 503);
        }
        $key = hash('sha256', json_encode($reference, JSON_THROW_ON_ERROR));
        $resultPath = $this->directory.'/'.$hash.'-'.$key.'.json';
        $saved = $this->read($resultPath);
        if (isset($saved['resolved']) && is_array($saved['resolved'])) { return $saved['resolved']; }
        $result = $this->match($cached, $reference);
        if ($result !== null) { $this->write($resultPath, json_encode(['resolved' => $result], JSON_THROW_ON_ERROR)); }
        return $result;
    }

    /** Exact line/run mapping in api.txt + service calendar + requested stop occurrence times. */
    public function match(string $path, array $ref): ?array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) { throw new TransportException('invalid_upstream', 'Invalid IDS JMK schedule.', 502); }
        try {
            $stat = $zip->statName('api.txt');
            if (!$stat || $stat['size'] > 10000000) { throw new TransportException('invalid_upstream', 'IDS JMK identity index missing.', 502); }
            $api = $zip->getFromName('api.txt');
        } finally { $zip->close(); }
        $candidates = [];
        foreach (explode("\n", $api ?: '') as $row) {
            if (preg_match('/^Linka\/CVlaku = trip_id: (\d+)\/(\d+) = (\d+)\s*$/D', $row, $m)
                && (int)$m[1] === $ref['line'] && (int)$m[2] === $ref['number']) { $candidates[$m[3]] = true; }
        }
        if (!$candidates || count($candidates) > 100) { return null; }
        $archive = new GtfsArchiveReader($path, 2000000);
        $day = str_replace('-', '', $ref['date']); $weekday = strtolower((new \DateTimeImmutable($ref['date']))->format('l'));
        $services = [];
        foreach ($archive->rows('calendar.txt', ['service_id','start_date','end_date', $weekday], true) as $row) {
            if ($day >= $row['start_date'] && $day <= $row['end_date'] && $row[$weekday] === '1') { $services[$row['service_id']] = true; }
        }
        foreach ($archive->rows('calendar_dates.txt', ['service_id','date','exception_type'], true) as $row) {
            if ($row['date'] === $day) { $services[$row['service_id']] = $row['exception_type'] === '1'; }
        }
        $trips = [];
        foreach ($archive->rows('trips.txt', ['trip_id','route_id','service_id']) as $row) {
            if (isset($candidates[$row['trip_id']]) && !empty($services[$row['service_id']])
                && preg_match('/^L'.preg_quote((string)$ref['line'], '/').'D\d+$/D', $row['route_id'])) { $trips[$row['trip_id']] = []; }
        }
        if (!$trips) { return null; }
        foreach ($archive->rows('stop_times.txt', ['trip_id','stop_id','stop_sequence','arrival_time','departure_time']) as $row) {
            if (array_key_exists($row['trip_id'], $trips)) {
                if (count($trips[$row['trip_id']]) >= 2000) { throw new TransportException('invalid_upstream', 'Trip exceeds stop limit.', 502); }
                $trips[$row['trip_id']][] = $row;
            }
        }
        $matches = [];
        foreach ($trips as $id => $calls) {
            usort($calls, static fn ($a, $b) => (int)$a['stop_sequence'] <=> (int)$b['stop_sequence']);
            $from = $calls[$ref['from_index']] ?? null; $to = $calls[$ref['to_index']] ?? null;
            if (!$from || !$to || $from['departure_time'] !== $ref['from_time'] || $to['arrival_time'] !== $ref['to_time']) { continue; }
            $start = ServiceTimeService::instant($ref['date'], ServiceTimeService::seconds($calls[0]['departure_time']), 'Europe/Prague')->getTimestamp();
            $end = ServiceTimeService::instant($ref['date'], ServiceTimeService::seconds(end($calls)['arrival_time']), 'Europe/Prague')->getTimestamp();
            $matches[] = ['trip_id' => (string)$id, 'start' => $start, 'end' => $end, 'date' => $ref['date'], 'line' => $ref['line'], 'number' => $ref['number']];
        }
        return count($matches) === 1 ? $matches[0] : null;
    }
    private function read(string $path): array
    {
        if (!is_file($path) || filesize($path) > 100000) { return []; }
        return json_decode(file_get_contents($path), true) ?: [];
    }
    private function write(string $path, string $body): void
    {
        $tmp = tempnam($this->directory, '.write-');
        try {
            if ($tmp === false || file_put_contents($tmp, $body) !== strlen($body) || !rename($tmp, $path)) {
                throw new TransportException('invalid_configuration', 'Cannot write static identity cache.', 500);
            }
        } finally { if ($tmp && is_file($tmp)) { unlink($tmp); } }
    }
}
