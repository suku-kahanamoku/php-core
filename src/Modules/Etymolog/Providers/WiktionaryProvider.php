<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\SyncException;
use App\Modules\Etymolog\EtymologDiscoveryRepository;
use App\Modules\Etymolog\NameNormalizer;
use App\Modules\Http\Contracts\HttpClient;

/**
 * Pevná vydání slovníků; původní jazyk a uvedení zdroje se zachovávají.
 *
 * Etymologie se přebírá jen ze sekce jazyka odpovídajícího jménu, jinak by se
 * smíchaly cizí jazyky a homony. Pokud slovník etymologii neuvádí nebo ji uvádí
 * vícekrát, položka se přeskočí — nikdy se nedoplní odhadem. Text se nechává
 * v jazyce zdroje bez překladu a zůstává nepublikovaný.
 */
final class WiktionaryProvider implements BatchProvider
{
    /** Názvy jazyků v anglickém wydání. */
    private const LANGUAGES = ['cs' => 'Czech', 'sk' => 'Slovak', 'pl' => 'Polish', 'uk' => 'Ukrainian', 'de' => 'German', 'en' => 'English'];

    /** Adresa textu licence CC BY-SA 4.0. */
    public const LICENSE_URL = 'https://creativecommons.org/licenses/by-sa/4.0/';

    /**
     * @param  HttpClient $http     Sdílený HTTP klient.
     * @param  EtymologDiscoveryRepository $names Zdroj kandidátních jmen z databáze.
     * @param  string $edition    Vydání slovníku: 'en', 'cs' nebo 'fr'.
     * @return void
     * @throws SyncException 'unsupported_dictionary_edition' pro jiné vydání.
     */
    public function __construct(private readonly HttpClient $http, private readonly EtymologDiscoveryRepository $names, private readonly string $edition = 'en')
    {
        if (!in_array($edition, ['en', 'cs', 'fr'], true)) { throw new SyncException('unsupported_dictionary_edition'); }
    }

    /**
     * Vrátí názvy jazyků používané v nadpisech daného vydání.
     *
     * @return array<string, string> Jazyk podle kódu a jeho název v daném wydání.
     */
    private function languages(): array
    {
        return match ($this->edition) { 'cs' => ['cs' => 'čeština'], 'fr' => ['cs' => 'Tchèque'], default => self::LANGUAGES };
    }

    /**
     * Vrátí kategorie, podle kterých se ověřuje, že stránka opravdu pojednává o jmeně.
     *
     * @param  string $language Jazyk jména.
     * @param  string $kind     'given' nebo 'surname'.
     * @return list<string>       Názvy kategorií v daném wydání.
     */
    private function categories(string $language, string $kind): array
    {
        if ($this->edition === 'cs') {
            return $kind === 'surname' ? ['Česká příjmení'] : ['Česká propria'];
        }
        if ($this->edition === 'fr') { return $kind === 'surname' ? ['Noms de famille en tchèque'] : ['Prénoms masculins en tchèque', 'Prénoms féminins en tchèque', 'Prénoms en tchèque']; }
        return array_map(fn ($suffix) => self::LANGUAGES[$language].' '.$suffix,
            $kind === 'surname' ? ['surnames'] : ['male given names', 'female given names', 'unisex given names', 'given names']);
    }

    /**
     * Provede dotaz do API daného wydání Wiktionary.
     *
     * @param  array<string, mixed> $params Parametry akce.
     * @return array<string, mixed>          Dekódovaná odpověď API.
     * @throws SyncException                Při chybě upstreamu nebo neplatné odpovědi.
     */
    private function api(array $params): array
    {
        return ProviderHttp::json($this->http, 'https://'.$this->edition.'.wiktionary.org/w/api.php?'.http_build_query($params + ['format' => 'json', 'maxlag' => 5]));
    }

    /**
     * Stáhne jednu dávku etymologií podle kurzoru.
     *
     * @param  string     $language Jazyk jména.
     * @param  string     $kind     'given' nebo 'surname', případně s příponou '_priority' pro české přednosti.
     * @param  string|null $cursor  Kurzor s pozicí v katalogu jmen.
     * @param  int        $limit    Maximální počet jmen na dávku (1–3).
     * @return array{items:list<array<string, mixed>>, scanned:int, cursor:?string, complete:bool} Dávka etymologií.
     * @throws SyncException           'invalid_provider_configuration', 'invalid_provider_cursor',
     *                                'upstream_license_changed', 'invalid_discovery_response',
     *                                'invalid_etymology_response', 'etymology_language_missing',
     *                                'etymology_markup_changed', 'etymology_body_out_of_bounds'
     *                                nebo chyba upstreamu.
     */
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        $priority = str_ends_with($kind, '_priority');
        $kind = $priority ? substr($kind, 0, -9) : $kind;
        $languages = $this->languages();
        if (!isset($languages[$language]) || !in_array($kind, ['given', 'surname'], true) || $limit < 1 || $limit > 3 || ($priority && $language !== 'cs')) { throw new SyncException('invalid_provider_configuration'); }
        $categories = $this->categories($language, $kind);
        // Legacy priority and category cursors are retired; every task now follows DB IDs.
        $state=$cursor===null ? ['after'=>0] : json_decode($cursor,true);
        if ((is_int($state) && $state>=0) || (is_array($state) && isset($state['category']))) { $state=['after'=>0]; }
        if (!is_array($state) || !is_int($state['after'] ?? null) || $state['after']<0) { throw new SyncException('invalid_provider_cursor'); }
        $selected=[]; $scanned=0; $after=$state['after'];
        for ($i=0;$i<$limit;++$i) {
            $record=$this->names->nextIncomplete($kind,$after);
            if (!$record) { break; }
            $after=(int)$record['id']; ++$scanned;
            if (!preg_match('/[|:#\x00-\x1f]/u', $record['name'])) { $selected[]=NameNormalizer::display($record['name']); }
        }
        $complete=$this->names->nextIncomplete($kind,$after)===null;
        $nextCursor=$complete ? null : json_encode(['after'=>$after],JSON_THROW_ON_ERROR);
        if (!$selected) { return ['items'=>[],'scanned'=>$scanned,'cursor'=>$nextCursor,'complete'=>$complete]; }
        $rights = $this->api(['action' => 'query', 'meta' => 'siteinfo', 'siprop' => 'rightsinfo']);
        if (!in_array($rights['query']['rightsinfo']['url'] ?? '', [self::LICENSE_URL, self::LICENSE_URL.'deed.en', self::LICENSE_URL.'deed.cs', self::LICENSE_URL.'deed.fr'], true)) { throw new SyncException('upstream_license_changed'); }
        $discovery=$this->api(['action'=>'query','titles'=>implode('|',$selected)]);
        if (!is_array($discovery['query']['pages'] ?? null) || count($discovery['query']['pages'])>count($selected)) { throw new SyncException('invalid_discovery_response'); }
        $pages=array_values(array_filter($discovery['query']['pages'],static fn($p)=>is_array($p) && !isset($p['missing']) && !isset($p['invalid'])));
        $items = [];
        foreach ($pages as $page) {
            if (!is_int($page['pageid'] ?? null) || $page['pageid'] < 1 || ($page['ns'] ?? null) !== 0 || !is_string($page['title'] ?? null) || strlen($page['title']) > 255) { throw new SyncException('invalid_discovery_response'); }
            if (!in_array($page['title'],$selected,true)) { throw new SyncException('invalid_discovery_response'); }
            $response = $this->api(['action' => 'parse', 'pageid' => $page['pageid'], 'prop' => 'text|revid|categories']);
            $p = $response['parse'] ?? null;
            if (!is_array($p) || ($p['pageid'] ?? null) !== $page['pageid'] || ($p['title'] ?? null) !== $page['title'] || !is_string($p['title'] ?? null) || strlen($p['title']) > 255 || !is_int($p['revid'] ?? null) || $p['revid'] < 1 || !is_string($p['text']['*'] ?? null)) { throw new SyncException('invalid_etymology_response'); }
            $currentCategories = array_map(static fn ($v) => str_replace('_', ' ', (string)($v['*'] ?? '')), $p['categories'] ?? []);
            if (array_intersect($categories, $currentCategories) === []) { continue; } // stale index membership
            $text = $this->extract($p['text']['*'], $languages[$language], $kind);
            if ($text === null) { continue; } // Explicitly absent or ambiguous etymology; never invent one.
            $url = 'https://'.$this->edition.'.wiktionary.org/w/index.php?oldid='.$p['revid'].'#'.rawurlencode($languages[$language]);
            $history = 'https://'.$this->edition.'.wiktionary.org/w/index.php?title='.rawurlencode($p['title']).'&action=history';
            $items[] = [
                'external_id' => $this->edition.':'.$p['pageid'].':'.$language.':'.$kind, 'revision' => (string)$p['revid'],
                'name' => $p['title'], 'kind' => $kind, 'language' => $language, 'country_code' => null,
                'source_key' => $this->edition.':'.$p['pageid'].':'.$language, 'source_title' => 'Wiktionary: '.$p['title'].' ('.$languages[$language].')',
                'source_url' => $url, 'license' => 'CC-BY-SA-4.0', 'license_url' => self::LICENSE_URL,
                'attribution' => 'Wiktionary contributors; '.$history,
                'notes' => $this->edition.' Wiktionary; extracted etymology paragraphs as plain text, no translation. Attribution and CC BY-SA 4.0 must accompany republication and adaptations.',
                'entry' => ['type' => 'etymology', 'title' => $p['title'].' – etymology (Wiktionary)', 'body' => $text, 'language' => $this->edition, 'certainty' => 'unverified', 'published' => 0],
                'payload' => ['name_language' => $language, 'text_language' => $this->edition, 'page_id' => $p['pageid'], 'revision' => $p['revid'], 'etymology' => $text, 'history_url' => $history, 'license_evidence' => $rights['query']['rightsinfo'], 'changes' => 'Etymology section extracted to plain text; other sections and markup omitted.'],
            ];
        }
        return ['items' => $items, 'scanned' => $scanned, 'cursor' => $nextCursor, 'complete' => $complete];
    }

    /**
     * Vyžaduje význam jména ve stejné skupině etymologie; jazyky a homony se nemíchají.
     *
     * @param  string      $html     HTML stránky z Wiktionary.
     * @param  string      $language Název jazyka v daném vydání.
     * @param  string      $kind     'given' nebo 'surname'.
     * @return string|null           Etymologie jako čistý text, nebo null pokud není jednoznačná.
     * @throws SyncException         'invalid_etymology_html', 'etymology_markup_changed',
     *                               'etymology_language_missing' nebo 'etymology_body_out_of_bounds'.
     */
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
        $flush = static         /**
         * Uloží právě přečtenou skupinu odstavců, pokud patří k hledanému významu.
         *
         * @return void Bez návratu; skupina se přidá do `$groups`.
         */
function () use (&$paragraphs, &$validName, &$groups): void {
            if ($validName && $paragraphs !== []) { $groups[] = implode("\n\n", $paragraphs); }
            $paragraphs = []; $validName = false;
        };
        foreach ($xp->query('.//h2|.//h3|.//h4|.//h5|.//h6|.//dd[not(ancestor::dd) and not(ancestor::li) and not(ancestor::table)]|.//p[not(ancestor::li) and not(ancestor::table)]|.//ol[not(ancestor::li)]', $root) as $node) {
            if (!$node instanceof \DOMElement) { continue; }
            $heads = $xp->query('self::h2|self::h3|self::h4|self::h5|self::h6', $node);
            if ($heads->length) {
                $head = $heads->item(0); $label = trim($head->textContent);
                if ($head->tagName === 'h2') {
                    if ($active) { $flush(); }
                    $active = $label === $language; $found = $found || $active; $section = '';
                } elseif ($active) {
                    if (preg_match(match ($this->edition) { 'cs' => '/^etymologie(?: [0-9]+)?$/Du', 'fr' => '/^Étymologie(?: [0-9]+)?$/Du', default => '/^Etymology(?: [0-9]+)?$/D' }, $label)) { $flush(); $section = 'etymology'; }
                    else { $section = $label; }
                }
                continue;
            }
            if (!$active) { continue; }
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
            if ($section === 'etymology' && ($node->tagName === 'p' || ($this->edition === 'fr' && $node->tagName === 'dd')) && $text !== '') { $paragraphs[] = $text; }
            if (($section === match ($this->edition) { 'cs' => 'význam', 'fr' => $kind === 'surname' ? 'Nom de famille' : 'Prénom', default => 'Proper noun' }) && $node->tagName === 'ol') {
                $pattern = $this->edition === 'cs' ? ($kind === 'surname' ? '/příjmení/u' : '/(?:rodné|křestní|osobní|mužské|ženské) jméno/u') : ($kind === 'surname' ? '/\bsurname\b/i' : '/\bgiven name\b/i');
                if ($this->edition === 'fr' || preg_match($pattern, $text)) { $validName = true; }
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
