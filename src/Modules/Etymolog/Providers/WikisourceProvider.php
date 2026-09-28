<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\SyncException;
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest, HttpException};

/** A reviewed, append-only catalog. No arbitrary page or URL supplied by an API user. */
final class WikisourceProvider implements BatchProvider
{
    public const LICENSE = 'PD-old-70';
    public const LICENSE_URL = 'https://cs.wikisource.org/wiki/Wikizdroje:Licence#PD_old_70';
    public const AUTHOR = 'Alois Jirásek';
    private const BOOK = 'Staré pověsti české (1959)/';
    // Keep order stable: the cursor indexes this catalog. Add new chapters only at the end.
    private const CATALOG = [
        ['title' => 'O Libuši', 'names' => ['Libuše', 'Kazi', 'Teta'], 'region' => 'Čechy – Vyšehrad'],
        ['title' => 'O Přemyslovi', 'names' => ['Přemysl', 'Libuše'], 'region' => 'Čechy – Stadice a Vyšehrad'],
        ['title' => 'O Bivoji', 'names' => ['Bivoj', 'Kazi', 'Libuše'], 'region' => 'Čechy'],
        ['title' => 'O Krokovi a jeho dcerách', 'names' => ['Krok', 'Kazi', 'Teta', 'Libuše'], 'region' => 'Čechy'],
    ];

    public function __construct(private readonly HttpClient $http) {}

    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if ($language !== 'cs' || $kind !== 'stories' || $limit < 1 || $limit > 4 ||
            ($cursor !== null && !preg_match('/^(0|[1-9][0-9]{0,5})$/D', $cursor)) || (int)$cursor > count(self::CATALOG)) {
            throw new SyncException('invalid_provider_configuration');
        }
        $offset = (int)($cursor ?? '0');
        $items = [];
        foreach (array_slice(self::CATALOG, $offset, $limit) as $record) {
            $page = self::BOOK.$record['title'];
            $response = $this->http->send(new HttpRequest('https://cs.wikisource.org/w/api.php?'.http_build_query([
                'action' => 'parse', 'page' => $page, 'prop' => 'text|revid|categories', 'format' => 'json', 'maxlag' => 5,
            ]), headers: ['Accept' => 'application/json', 'User-Agent' => 'Etymolog/1.0 (php-core; public domain folklore catalog)'],
                timeoutMs: 25000, connectTimeoutMs: 5000, maxBytes: 2000000));
            $delay = max(300, min(604800, $response->retryAfter ?? 300));
            if (!$response->successful()) {
                throw new SyncException($response->status === 429 ? 'upstream_rate_limited' : 'upstream_unavailable', $delay);
            }
            try { $data = $response->json(); }
            catch (HttpException) { throw new SyncException('invalid_upstream_json'); }
            if (isset($data['error']) || isset($data['errors'])) {
                throw new SyncException('upstream_api_error', $delay);
            }
            $parsed = $data['parse'] ?? null;
            if (!is_array($parsed) || ($parsed['title'] ?? null) !== $page || !is_int($parsed['pageid'] ?? null) || $parsed['pageid'] < 1 ||
                !is_int($parsed['revid'] ?? null) || $parsed['revid'] < 1 || !is_string($parsed['text']['*'] ?? null)) {
                throw new SyncException('invalid_story_response');
            }
            $text = $this->extract($parsed['text']['*'], $record['title']);
            $sourceUrl = 'https://cs.wikisource.org/w/index.php?oldid='.$parsed['revid'];
            $items[] = [
                'external_id' => 'cs:'.$parsed['pageid'], 'revision' => (string)$parsed['revid'],
                'title' => $record['title'], 'body' => $text['body'], 'region' => $record['region'],
                'names' => $record['names'], 'source_url' => $sourceUrl, 'bibliography' => $text['bibliography'],
                'payload' => ['page' => $page, 'page_id' => $parsed['pageid'], 'revision' => $parsed['revid'],
                    'body' => $text['body'], 'bibliography' => $text['bibliography'], 'author' => self::AUTHOR,
                    'license' => self::LICENSE, 'license_url' => self::LICENSE_URL,
                    'suggested_names' => $record['names'], 'region' => $record['region']],
            ];
        }
        $next = $offset + count($items);
        $complete = $next >= count(self::CATALOG);
        return ['items' => $items, 'cursor' => $complete ? null : (string)$next, 'complete' => $complete];
    }

    /** Extract only prose; fail closed if the curated edition, license or markup changes. */
    private function extract(string $html, string $title): array
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) { throw new SyncException('invalid_story_html'); }
        $xp = new \DOMXPath($dom);
        $table = $xp->query('//table[contains(concat(" ", normalize-space(@class), " "), " textinfo ")]');
        if ($table->length !== 1) { throw new SyncException('story_metadata_changed'); }
        $metadata = [];
        foreach ($xp->query('.//tr', $table->item(0)) as $row) {
            $cells = $xp->query('./td', $row);
            if ($cells->length === 2) {
                $metadata[$this->normalize($cells->item(0)->textContent)] = $this->normalize($cells->item(1)->textContent);
            }
        }
        if (($metadata['Licence:'] ?? '') !== 'PD old 70') { throw new SyncException('story_license_not_allowed'); }
        if (($metadata['Autor:'] ?? '') !== self::AUTHOR || ($metadata['Titulek:'] ?? '') !== $title || empty($metadata['Zdroj:'])) {
            throw new SyncException('story_metadata_changed');
        }
        $prose = $xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " forma ") and contains(concat(" ", normalize-space(@class), " "), " proza ")]');
        if ($prose->length !== 1) { throw new SyncException('story_markup_changed'); }
        $body = $prose->item(0);
        // Never persist executable markup, image captions, navigation or reference markers.
        $remove = $xp->query('.//script|.//style|.//table|.//sup|.//figure|.//img|.//span[contains(concat(" ", normalize-space(@class), " "), " mw-editsection ")]', $body);
        foreach (iterator_to_array($remove) as $node) { $node->parentNode?->removeChild($node); }
        $paragraphs = [];
        foreach ($xp->query('.//p', $body) as $paragraph) {
            $value = $this->normalize($paragraph->textContent);
            if ($value !== '') { $paragraphs[] = $value; }
        }
        $text = implode("\n\n", $paragraphs);
        if (strlen($text) < 100 || strlen($text) > 60000 || str_contains($text, "\0")) {
            throw new SyncException('story_body_out_of_bounds');
        }
        return ['body' => $text, 'bibliography' => $metadata['Zdroj:']];
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
