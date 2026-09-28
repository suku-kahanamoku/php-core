<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\SyncException;
use App\Modules\Http\Contracts\HttpClient;

/** Community calendar, not an official civil or liturgical authority. Never executes source JS. */
final class CzechNamedaysProvider implements BatchProvider
{
    private const REPO = 'segeda/svatky-api-nodejs';
    private const LICENSE_HASH = 'f0510d3b9d1223c45e8fdd2b49270ec8734fff491c7f7d1b948ee70162e1ceff';
    private const OBSERVANCES = ['Den obnovy samostatného českého státu', 'Nový rok', 'Svátek práce', 'Památka zesnulých',
        'Den slovanských věrozvěstů Cyrila a Metoděje', 'Tři králové', 'Den upálení mistra Jana Husa', 'Den vítězství',
        'Den boje za svobodu a demokracii', 'Štědrý den', '1. svátek vánoční', '2. svátek vánoční', 'Den české státnosti', 'Den vzniku samostatného československého státu'];
    public function __construct(private readonly HttpClient $http) {}

    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if ($language !== 'cs' || $kind !== 'calendar' || $limit < 1 || $limit > 500) {throw new SyncException('invalid_provider_configuration');}
        $state = $cursor === null ? null : json_decode($cursor, true);
        if ($cursor !== null && (!is_array($state) || !is_string($state['revision'] ?? null) || !preg_match('/^[a-f0-9]{40}$/D', $state['revision']) || !is_int($state['offset'] ?? null) || $state['offset'] < 1 || $state['offset'] > 2000)) {throw new SyncException('invalid_provider_cursor');}
        $revision = $state['revision'] ?? (ProviderHttp::json($this->http, 'https://api.github.com/repos/'.self::REPO.'/commits/master')['sha'] ?? '');
        if (!is_string($revision) || !preg_match('/^[a-f0-9]{40}$/D', $revision)) {throw new SyncException('calendar_revision_invalid');}
        $base = 'https://raw.githubusercontent.com/'.self::REPO.'/'.$revision.'/';
        $license = ProviderHttp::get($this->http, $base.'LICENSE.md', 20000)->body;
        if (hash('sha256', str_replace("\r\n", "\n", trim($license))) !== self::LICENSE_HASH) {throw new SyncException('upstream_license_changed');}
        $data = ProviderHttp::get($this->http, $base.'cs.js', 200000)->body;
        if (!preg_match('/var\s+json_data\s*=\s*(\{.*?\})\s*;?\s*var\s+result\s*=/s', $data, $match)) {throw new SyncException('calendar_schema_changed');}
        try {$dates = json_decode($match[1], true, 16, JSON_THROW_ON_ERROR);}
        catch (\JsonException) {throw new SyncException('calendar_schema_changed');}
        if (!is_array($dates) || count($dates) !== 366 || preg_match_all('/"[0-9]{4}"\s*:/', $match[1]) !== 366) {throw new SyncException('calendar_coverage_changed');}
        $rows = [];
        foreach ($dates as $ddmm => $labels) {
            $ddmm = (string)$ddmm;
            $day = (int)substr($ddmm, 0, 2); $month = (int)substr($ddmm, 2, 2);
            if (!preg_match('/^[0-9]{4}$/D', $ddmm) || !checkdate($month, $day, 2000)) {throw new SyncException('invalid_calendar_date');}
            $labels = is_string($labels) ? [$labels] : $labels;
            if (!is_array($labels) || !array_is_list($labels) || count($labels) < 1 || count($labels) > 10 || count(array_unique($labels, SORT_REGULAR)) !== count($labels)) {throw new SyncException('calendar_schema_changed');}
            foreach ($labels as $label) {
                if (!is_string($label) || strlen($label) > 255 || trim($label) !== $label || str_contains($label, "\0")) {throw new SyncException('calendar_schema_changed');}
                $observance = in_array($label, self::OBSERVANCES, true);
                if (!$observance && !preg_match('/^[\p{L}\p{M}]+(?:-[\p{L}\p{M}]+)*$/uD', $label)) {throw new SyncException('calendar_label_needs_review');}
                $rows[] = ['name' => $observance ? null : $label, 'title' => $label, 'kind' => $observance ? 'observance' : 'name_day', 'month' => $month, 'day' => $day];
            }
        }
        usort($rows, static fn($a,$b) => [$a['month'],$a['day'],$a['title']] <=> [$b['month'],$b['day'],$b['title']]);
        $offset = $state['offset'] ?? 0;
        if ($offset >= count($rows)) {throw new SyncException('invalid_provider_cursor');}
        $url = 'https://github.com/'.self::REPO.'/blob/'.$revision.'/cs.js';
        $items = [];
        foreach (array_slice($rows, $offset, $limit) as $row) {
            $items[] = $row + ['external_id' => 'cs:'.$row['month'].':'.$row['day'].':'.hash('sha256', $row['title']), 'revision' => $revision,
                'source_url' => $url, 'license' => 'Unlicense', 'license_url' => 'https://github.com/'.self::REPO.'/blob/'.$revision.'/LICENSE.md',
                'attribution' => 'segeda/svatky-api-nodejs contributors',
                'payload' => $row + ['repository' => self::REPO, 'revision' => $revision, 'file_sha256' => hash('sha256', $data), 'license_sha256' => self::LICENSE_HASH,
                    'scope' => 'Community Czech name-day calendar. Observances are not given names; not an authoritative public-holiday register.']];
        }
        $next = $offset + count($items); $complete = $next >= count($rows);
        return ['items' => $items, 'cursor' => $complete ? null : json_encode(['revision' => $revision, 'offset' => $next], JSON_THROW_ON_ERROR), 'complete' => $complete];
    }
}
