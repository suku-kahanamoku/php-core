<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\SyncException;
use App\Modules\Http\Contracts\HttpClient;

/** Reviewed article/section/name associations, never generated narratives or arbitrary URLs. */
final class WikipediaNamesProvider implements BatchProvider
{
    public const LICENSE_URL = 'https://creativecommons.org/licenses/by-sa/4.0/';

    // Append only: cursors are offsets; keys are permanent import identities, not revision IDs.
    private const CATALOG = [
        'etymologies' => [
            ['anna-origin', 'Anna', 'Svatá Anna', 'Etymologie', 'etymology'],
            ['jiri-origin', 'Jiří', 'Svatý Jiří', 'Etymologie jména', 'etymology'],
            ['diana-origin', 'Diana', 'Diana (mytologie)', 'Jméno', 'etymology'],
        ],
        'culture' => [
            ['anna-legend', 'Anna', 'Svatá Anna', 'Život', 'legend'],
            ['anna-patronage', 'Anna', 'Svatá Anna', 'Patronka', 'tradition'],
            ['anna-proverbs', 'Anna', 'Anna', 'Pranostiky', 'proverb'],
            ['anna-feast', 'Anna', 'Svatá Anna', 'Svátek', 'tradition'],
            ['jiri-dragon', 'Jiří', 'Svatý Jiří', 'Svatý Jiří a drak', 'legend'],
            ['martin-cloak', 'Martin', 'Martin z Tours', 'Legenda o plášti', 'legend'],
            ['mikulas-daughters', 'Mikuláš', 'Svatý Mikuláš', 'Legenda o šlechtici a jeho třech dcerách', 'legend'],
            ['mikulas-children', 'Mikuláš', 'Svatý Mikuláš', 'Legenda o třech dětech', 'legend'],
            ['mikulas-customs', 'Mikuláš', 'Svatý Mikuláš', 'Česko a Slovensko', 'tradition'],
            ['barbora-legend', 'Barbora', 'Barbora z Nikomédie', 'Život', 'legend'],
            ['barbora-customs', 'Barbora', 'Barbora z Nikomédie', 'Zajímavosti', 'tradition'],
            ['diana-mythology', 'Diana', 'Diana (mytologie)', 'Funkce', 'mythology'],
        ],
    ];

    public function __construct(private readonly HttpClient $http) {}

    private function api(array $params): array
    {
        return ProviderHttp::json($this->http, 'https://cs.wikipedia.org/w/api.php?'.http_build_query($params + ['format' => 'json', 'maxlag' => 5]));
    }

    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if ($language !== 'cs' || !isset(self::CATALOG[$kind]) || $limit < 1 || $limit > 3) { throw new SyncException('invalid_provider_configuration'); }
        $catalog = self::CATALOG[$kind];
        if ($cursor !== null && (!preg_match('/^(0|[1-9][0-9]*)$/D', $cursor) || (int)$cursor >= count($catalog))) { throw new SyncException('invalid_provider_cursor'); }
        $rights = $this->api(['action' => 'query', 'meta' => 'siteinfo', 'siprop' => 'rightsinfo']);
        if (!in_array($rights['query']['rightsinfo']['url'] ?? '', [self::LICENSE_URL, self::LICENSE_URL.'deed.cs', self::LICENSE_URL.'deed.en'], true)) { throw new SyncException('upstream_license_changed'); }
        $offset = (int)($cursor ?? '0');
        $items = []; $pages = [];
        foreach (array_slice($catalog, $offset, $limit) as [$key, $name, $title, $heading, $type]) {
            if (!isset($pages[$title])) {
                $response = $this->api(['action' => 'parse', 'page' => $title, 'prop' => 'revid|sections']);
                $p = $response['parse'] ?? null;
                if (!is_array($p) || ($p['title'] ?? null) !== $title || !is_int($p['pageid'] ?? null) || $p['pageid'] < 1 || !is_int($p['revid'] ?? null) || $p['revid'] < 1 || !is_array($p['sections'] ?? null)) { throw new SyncException('invalid_culture_response'); }
                $pages[$title] = $p;
            }
            $p = $pages[$title];
            $matches = array_values(array_filter($p['sections'], static fn ($s) => is_array($s) && is_string($s['line'] ?? null) && self::plain($s['line']) === $heading));
            if (count($matches) !== 1 || !is_string($matches[0]['index'] ?? null) || !preg_match('/^[1-9][0-9]*$/D', $matches[0]['index']) || !is_string($matches[0]['anchor'] ?? null) || $matches[0]['anchor'] === '') { throw new SyncException('culture_section_changed'); }
            $section = $matches[0];
            // Pin the body to the revision used to resolve the section number.
            $response = $this->api(['action' => 'parse', 'oldid' => $p['revid'], 'section' => $section['index'], 'prop' => 'text|revid']);
            $bodyPage = $response['parse'] ?? null;
            if (!is_array($bodyPage) || ($bodyPage['title'] ?? null) !== $title || ($bodyPage['pageid'] ?? null) !== $p['pageid'] || ($bodyPage['revid'] ?? null) !== $p['revid'] || !is_string($bodyPage['text']['*'] ?? null)) { throw new SyncException('invalid_culture_revision'); }
            $body = $this->extract($bodyPage['text']['*'], $heading);
            $url = 'https://cs.wikipedia.org/w/index.php?oldid='.$p['revid'].'#'.rawurlencode($section['anchor']);
            $history = 'https://cs.wikipedia.org/w/index.php?title='.rawurlencode($title).'&action=history';
            $notes = 'Převzatý oddíl české Wikipedie převedený do prostého textu; bez obrázků, navigace a značek poznámek. Bez překladu a generování. Při zveřejnění zachovat autorství, odkaz, CC BY-SA 4.0 a označení úprav. Zařazení legendy či mytologie není potvrzením historické skutečnosti.';
            $items[] = [
                'external_id' => 'cs:'.$key, 'revision' => (string)$p['revid'],
                'name' => $name, 'kind' => 'given', 'language' => 'cs', 'country_code' => null,
                'source_key' => 'cs:'.$p['pageid'], 'source_title' => 'Wikipedie: '.$title,
                'source_url' => $url, 'license' => 'CC-BY-SA-4.0', 'license_url' => self::LICENSE_URL,
                'attribution' => 'Přispěvatelé české Wikipedie; '.$history.'; výběr oddílu a převod do prostého textu, odstranění navigace a značek referencí.', 'notes' => $notes,
                'locator' => $title.' / '.$heading.'; revize '.$p['revid'],
                'entry' => ['type' => $type, 'title' => $title.' – '.$heading, 'body' => $body, 'language' => 'cs', 'source_url' => $url, 'certainty' => 'unverified', 'published' => 0],
                'payload' => ['catalog_key' => $key, 'page_id' => $p['pageid'], 'revision' => $p['revid'], 'section' => $heading, 'body' => $body, 'history_url' => $history, 'license_evidence' => $rights['query']['rightsinfo'], 'changes' => $notes],
            ];
        }
        $next = $offset + count($items);
        $complete = $next >= count($catalog);
        return ['items' => $items, 'scanned' => count($items), 'cursor' => $complete ? null : (string)$next, 'complete' => $complete];
    }

    private static function plain(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function extract(string $html, string $heading): string
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        if (!$loaded) { throw new SyncException('invalid_culture_html'); }
        $xp = new \DOMXPath($dom);
        $roots = $xp->query('//div[contains(concat(" ",normalize-space(@class)," ")," mw-parser-output ")]');
        if ($roots->length !== 1) { throw new SyncException('culture_markup_changed'); }
        $root = $roots->item(0);
        foreach (iterator_to_array($xp->query('.//script|.//style|.//table|.//sup|.//figure|.//img|.//nav|.//div[contains(@class,"thumb") or contains(@class,"navbox") or contains(@class,"hatnote") or contains(@class,"reflist")]|.//span[contains(@class,"mw-editsection")]', $root)) as $node) { $node->parentNode?->removeChild($node); }
        $heads = $xp->query('.//h2|.//h3|.//h4|.//h5|.//h6', $root);
        if ($heads->length < 1 || self::plain($heads->item(0)->textContent) !== $heading) { throw new SyncException('culture_section_changed'); }
        $paragraphs = [];
        foreach ($xp->query('.//p[not(ancestor::li)]|.//li[not(ancestor::li)]', $root) as $node) {
            foreach (iterator_to_array($xp->query('.//br', $node)) as $br) { $br->parentNode->replaceChild($dom->createTextNode("\n"), $br); }
            // textContent is already decoded; do not decode or strip it again.
            $text = trim(preg_replace('/[^\S\n]+/u', ' ', $node->textContent) ?? '');
            if ($text !== '') { $paragraphs[] = $text; }
        }
        $body = implode("\n\n", $paragraphs);
        if (strlen($body) < 30 || strlen($body) > 60000 || str_contains($body, "\0")) { throw new SyncException('culture_body_out_of_bounds'); }
        return $body;
    }
}
