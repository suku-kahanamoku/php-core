<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\SyncException;
use App\Modules\Http\Contracts\HttpClient;

/** English edition, language-specific name categories; text language remains English. */
final class WiktionaryProvider implements BatchProvider
{
    private const LANGUAGES = ['cs' => 'Czech', 'sk' => 'Slovak', 'pl' => 'Polish', 'uk' => 'Ukrainian', 'de' => 'German', 'en' => 'English'];
    public const LICENSE_URL = 'https://creativecommons.org/licenses/by-sa/4.0/';
    public function __construct(private readonly HttpClient $http) {}

    private function api(array $params): array
    {
        return ProviderHttp::json($this->http, 'https://en.wiktionary.org/w/api.php?'.http_build_query($params + ['format' => 'json', 'maxlag' => 5]));
    }

    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if (!isset(self::LANGUAGES[$language]) || !in_array($kind, ['given', 'surname'], true) || $limit < 1 || $limit > 3) { throw new SyncException('invalid_provider_configuration'); }
        $categories = $kind === 'surname' ? ['surnames'] : ['male given names', 'female given names', 'unisex given names', 'given names'];
        $state = $cursor === null ? ['category' => 0, 'continue' => null] : json_decode($cursor, true);
        if (!is_array($state) || !is_int($state['category'] ?? null) || $state['category'] < 0 || $state['category'] >= count($categories) ||
            !array_key_exists('continue', $state) || ($state['continue'] !== null && (!is_string($state['continue']) || strlen($state['continue']) > 1500))) {
            throw new SyncException('invalid_provider_cursor');
        }
        $rights = $this->api(['action' => 'query', 'meta' => 'siteinfo', 'siprop' => 'rightsinfo']);
        if (!in_array($rights['query']['rightsinfo']['url'] ?? '', [self::LICENSE_URL, self::LICENSE_URL.'deed.en'], true)) { throw new SyncException('upstream_license_changed'); }
        $category = self::LANGUAGES[$language].' '.$categories[$state['category']];
        $params = ['action' => 'query', 'list' => 'categorymembers', 'cmtitle' => 'Category:'.$category, 'cmnamespace' => 0, 'cmtype' => 'page', 'cmlimit' => $limit];
        if ($state['continue'] !== null) { $params['cmcontinue'] = $state['continue']; }
        $discovery = $this->api($params);
        $pages = $discovery['query']['categorymembers'] ?? null;
        if (!is_array($pages) || !array_is_list($pages) || count($pages) > $limit) { throw new SyncException('invalid_discovery_response'); }
        $next = $discovery['continue']['cmcontinue'] ?? null;
        if ($next !== null && (!is_string($next) || $next === '' || strlen($next) > 1500 || $next === $state['continue'] || $pages === [])) { throw new SyncException('invalid_discovery_cursor'); }
        $items = [];
        foreach ($pages as $page) {
            if (!is_int($page['pageid'] ?? null) || $page['pageid'] < 1 || ($page['ns'] ?? null) !== 0 || !is_string($page['title'] ?? null) || strlen($page['title']) > 255) { throw new SyncException('invalid_discovery_response'); }
            $response = $this->api(['action' => 'parse', 'pageid' => $page['pageid'], 'prop' => 'text|revid|categories']);
            $p = $response['parse'] ?? null;
            if (!is_array($p) || ($p['pageid'] ?? null) !== $page['pageid'] || !is_string($p['title'] ?? null) || strlen($p['title']) > 255 || !is_int($p['revid'] ?? null) || $p['revid'] < 1 || !is_string($p['text']['*'] ?? null)) { throw new SyncException('invalid_etymology_response'); }
            $currentCategories = array_map(static fn ($v) => str_replace('_', ' ', (string)($v['*'] ?? '')), $p['categories'] ?? []);
            if (!in_array($category, $currentCategories, true)) { continue; } // stale index membership
            $text = $this->extract($p['text']['*'], self::LANGUAGES[$language], $kind);
            if ($text === null) { continue; } // Explicitly absent or ambiguous etymology; never invent one.
            $url = 'https://en.wiktionary.org/w/index.php?oldid='.$p['revid'].'#'.self::LANGUAGES[$language];
            $history = 'https://en.wiktionary.org/w/index.php?title='.rawurlencode($p['title']).'&action=history';
            $items[] = [
                'external_id' => 'en:'.$p['pageid'].':'.$language.':'.$kind, 'revision' => (string)$p['revid'],
                'name' => $p['title'], 'kind' => $kind, 'language' => $language, 'country_code' => null,
                'source_key' => 'en:'.$p['pageid'].':'.$language, 'source_title' => 'Wiktionary: '.$p['title'].' ('.self::LANGUAGES[$language].')',
                'source_url' => $url, 'license' => 'CC-BY-SA-4.0', 'license_url' => self::LICENSE_URL,
                'attribution' => 'Wiktionary contributors; '.$history,
                'notes' => 'English Wiktionary; extracted etymology paragraphs as plain text, no translation. Attribution and CC BY-SA 4.0 must accompany republication and adaptations.',
                'entry' => ['type' => 'etymology', 'title' => $p['title'].' – etymology (Wiktionary)', 'body' => $text, 'language' => 'en', 'certainty' => 'unverified', 'published' => 0],
                'payload' => ['name_language' => $language, 'text_language' => 'en', 'page_id' => $p['pageid'], 'revision' => $p['revid'], 'etymology' => $text, 'history_url' => $history, 'license_evidence' => $rights['query']['rightsinfo'], 'changes' => 'Etymology section extracted to plain text; other sections and markup omitted.'],
            ];
        }
        $categoryIndex = $state['category'] + ($next === null ? 1 : 0);
        $complete = $categoryIndex >= count($categories);
        return ['items' => $items, 'scanned' => count($pages), 'cursor' => $complete ? null : json_encode(['category' => $categoryIndex, 'continue' => $next], JSON_THROW_ON_ERROR), 'complete' => $complete];
    }

    /** Require a name sense in the same etymology group; do not mix languages/homonyms. */
    private function extract(string $html, string $language, string $kind): ?string
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        if (!$loaded) { throw new SyncException('invalid_etymology_html'); }
        $xp = new \DOMXPath($dom);
        $roots = $xp->query('//div[contains(concat(" ",normalize-space(@class)," ")," mw-parser-output ")]');
        if ($roots->length !== 1) { throw new SyncException('etymology_markup_changed'); }
        $root = $roots->item(0);
        foreach (iterator_to_array($xp->query('.//script|.//style|.//sup|.//span[contains(@class,"mw-editsection")]', $root)) as $node) { $node->parentNode?->removeChild($node); }
        $active = false; $found = false; $section = ''; $paragraphs = []; $validName = false; $groups = [];
        $flush = static function () use (&$paragraphs, &$validName, &$groups): void {
            if ($validName && $paragraphs !== []) { $groups[] = implode("\n\n", $paragraphs); }
            $paragraphs = []; $validName = false;
        };
        foreach ($root->childNodes as $node) {
            if (!$node instanceof \DOMElement) { continue; }
            $heads = $xp->query('self::h2|self::h3|self::h4|./h2|./h3|./h4', $node);
            if ($heads->length) {
                $head = $heads->item(0); $label = trim($head->textContent);
                if ($head->tagName === 'h2') {
                    if ($active) { $flush(); }
                    $active = $label === $language; $found = $found || $active; $section = '';
                } elseif ($active) {
                    if (preg_match('/^Etymology(?: [0-9]+)?$/D', $label)) { $flush(); $section = 'etymology'; }
                    else { $section = $label; }
                }
                continue;
            }
            if (!$active) { continue; }
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
            if ($section === 'etymology' && $node->tagName === 'p' && $text !== '') { $paragraphs[] = $text; }
            if ($section === 'Proper noun' && $node->tagName === 'ol') {
                $pattern = $kind === 'surname' ? '/\bsurname\b/i' : '/\bgiven name\b/i';
                if (preg_match($pattern, $text)) { $validName = true; }
            }
        }
        if ($active) { $flush(); }
        if (!$found) { throw new SyncException('etymology_language_missing'); }
        if ($groups === []) { return null; }
        $text = implode("\n\n", array_unique($groups));
        if (strlen($text) > 60000 || str_contains($text, "\0")) { throw new SyncException('etymology_body_out_of_bounds'); }
        return $text;
    }
}
