<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\SyncException;
use App\Modules\Http\Contracts\HttpClient;

/** Curated primary texts only. No generated stories, name etymologies or inferred dates. */
final class ErbenFolkloreProvider implements BatchProvider
{
    private const BOOK = 'Prostonárodní české písně a říkadla';
    // Append only: cursor indexes this reviewed catalog. Names are unreviewed associations.
    private const CATALOG = [
        ['title' => '25. ledna', 'type' => 'proverb', 'names' => ['Pavel'], 'month' => 1, 'day' => 25],
        ['title' => '24. února', 'type' => 'proverb', 'names' => ['Matěj', 'Josef'], 'month' => 2, 'day' => 24],
        ['title' => '12. března', 'type' => 'tradition', 'names' => ['Řehoř'], 'month' => 3, 'day' => 12],
        ['title' => 'Na jmena', 'type' => 'tradition', 'names' => ['Mikuláš', 'Michal', 'Havel', ['name' => 'Kučera', 'kind' => 'surname']]],
    ];
    public function __construct(private readonly HttpClient $http) {}

    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if ($language !== 'cs' || $kind !== 'folklore' || $limit < 1 || $limit > 4 || ($cursor !== null && !preg_match('/^[0-4]$/D', $cursor))) { throw new SyncException('invalid_provider_configuration'); }
        $offset = (int)($cursor ?? '0'); $items = [];
        foreach (array_slice(self::CATALOG, $offset, $limit) as $record) {
            $page = self::BOOK.'/'.$record['title'];
            $data = ProviderHttp::json($this->http, 'https://cs.wikisource.org/w/api.php?'.http_build_query(['action' => 'parse', 'page' => $page, 'prop' => 'text|revid', 'format' => 'json', 'maxlag' => 5]));
            $p = $data['parse'] ?? [];
            if (($p['title'] ?? null) !== $page || !is_int($p['pageid'] ?? null) || $p['pageid'] < 1 || !is_int($p['revid'] ?? null) || $p['revid'] < 1 || !is_string($p['text']['*'] ?? null)) { throw new SyncException('invalid_folklore_response'); }
            $text = $this->extract($p['text']['*'], $record['title']);
            $url = 'https://cs.wikisource.org/w/index.php?oldid='.$p['revid'];
            $item = $record + ['external_id' => 'cs:'.$p['pageid'], 'revision' => (string)$p['revid'], 'body' => $text['body'], 'region' => null,
                'source_url' => $url, 'bibliography' => $text['bibliography'], 'author' => 'Karel Jaromír Erben', 'source_title' => self::BOOK.' – '.$record['title'],
                'license' => WikisourceProvider::LICENSE, 'license_url' => WikisourceProvider::LICENSE_URL];
            if (isset($record['month'])) {
                $item['calendar'] = ['import_key' => 'erben:1864', 'title' => 'Erben 1864 – výroční tradice', 'country_code' => 'CZ',
                    'system' => 'gregorian', 'tradition' => 'Lidový výroční cyklus zachycený ve sbírce z roku 1864',
                    'notes' => 'Historické datum z názvu kapitoly; není tvrzením o dnešních jmeninách ani o době vzniku tradice.'];
            }
            $item['payload'] = ['page' => $page, 'revision' => $p['revid'], 'body' => $text['body'], 'bibliography' => $text['bibliography'],
                'author' => $item['author'], 'license' => $item['license'], 'suggested_names' => $record['names'],
                'source_date' => isset($record['month']) ? ['month' => $record['month'], 'day' => $record['day']] : null,
                'changes' => 'Plain-text transcription; verse line breaks preserved; navigation and markup omitted. No AI-generated or rewritten content.'];
            $items[] = $item;
        }
        $next = $offset + count($items); $complete = $next >= count(self::CATALOG);
        return ['items' => $items, 'cursor' => $complete ? null : (string)$next, 'complete' => $complete];
    }

    private function extract(string $html, string $title): array
    {
        $dom = new \DOMDocument(); $previous = libxml_use_internal_errors(true);
        try {$ok = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);}
        finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
        if (!$ok) {throw new SyncException('folklore_markup_changed');}
        $xp = new \DOMXPath($dom);
        $tables = $xp->query('//table[contains(concat(" ",normalize-space(@class)," ")," textinfo ")]');
        if ($tables->length !== 1) {throw new SyncException('folklore_metadata_changed');}
        $norm = static fn(string $s): string => trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        $meta = [];
        foreach ($xp->query('.//tr', $tables->item(0)) as $row) {
            $cells = $xp->query('./td', $row);
            if ($cells->length === 2) {$meta[$norm($cells->item(0)->textContent)] = $norm($cells->item(1)->textContent);}
        }
        if (($meta['Licence:'] ?? '') !== 'PD old 70') {throw new SyncException('story_license_not_allowed');}
        if (($meta['Autor:'] ?? '') !== 'zapsal Karel Jaromír Erben' || ($meta['Titulek:'] ?? '') !== $title || !str_contains($meta['Zdroj:'] ?? '', '1864')) {throw new SyncException('folklore_metadata_changed');}
        $roots = $xp->query('//div[contains(concat(" ",normalize-space(@class)," ")," mw-parser-output ")]');
        if ($roots->length !== 1) {throw new SyncException('folklore_markup_changed');}
        $root = $roots->item(0);
        foreach (iterator_to_array($xp->query('.//table|.//script|.//style|.//sup|.//figure|.//img|.//nav|.//div[contains(@class,"navbox")]|.//span[contains(@class,"mw-editsection")]', $root)) as $node) {$node->parentNode?->removeChild($node);}
        foreach (iterator_to_array($xp->query('.//br', $root)) as $br) {$br->parentNode->replaceChild($dom->createTextNode("\n"), $br);}
        $paragraphs = [];
        foreach ($xp->query('.//p', $root) as $paragraph) {
            $lines = array_values(array_filter(array_map($norm, explode("\n", $paragraph->textContent)), static fn($s) => $s !== ''));
            if ($lines !== []) {$paragraphs[] = implode("\n", $lines);}
        }
        $body = implode("\n\n", $paragraphs);
        if (strlen($body) < 30 || strlen($body) > 60000 || str_contains($body, "\0")) {throw new SyncException('folklore_body_out_of_bounds');}
        return ['body' => $body, 'bibliography' => $meta['Zdroj:']];
    }
}
