<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\SyncException;
use App\Modules\Http\Contracts\HttpClient;

/**
 * Kalendář jmenin z tabulky české Wikipedie; žádná odvozená data, generovaný
 * text ani náhradní zdroj.
 *
 * Poskytovatel je záměrně velmi přísný: kontroluje licenci, ID stránky a revizi
 * a vyžaduje přesně 366 unikátních dnů. Adnotace připojené redaktory Wikipedie
 * se z označení svátků odstraňují, takže ze svátku se nestane jméno. Změní-li se
 * struktura tabulky, dávka skončí chybou místo neúplného importu.
 */
final class CzechNamedaysProvider implements BatchProvider
{
    /** Klíč zdroje pro importní záznam. */
    public const SOURCE_KEY = 'wikipedia-calendar:cs:Jmeniny';

    /** Titulek zdroje s uvedením stránky a revize. */
    public const TITLE = 'Wikipedie: Jmeniny – český jmenný kalendář';

    /**
     * @param  HttpClient $http Sdílený HTTP klient z `HttpModule::client()`.
     * @return void
     */
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Provede dotaz do Wikipedie API včetně ochrany proti zpoždění replik.
     *
     * @param  array<string, mixed> $params Parametry akce.
     * @return array<string, mixed>          Dekódovaná odpověď API.
     * @throws SyncException                Při chybě upstreamu nebo neplatné odpovědi.
     */
    private function api(array $params): array
    {
        return ProviderHttp::json($this->http, 'https://cs.wikipedia.org/w/api.php?'.http_build_query($params + ['format'=>'json', 'maxlag'=>5]));
    }

    /**
     * Stáhne jednu dávku jmenin podle kurzoru.
     *
     * @param  string     $language Musí být 'cs'.
     * @param  string     $kind     Musí být 'calendar'.
     * @param  string|null $cursor  Kurzor z předchozí dávky; starý kurzor z GitHubu se zahazuje.
     * @param  int        $limit    Maximální počet dní v dávce (1–500).
     * @return array{items:list<array<string, mixed>>, cursor:?string, complete:bool} Dávka jmenin.
     * @throws SyncException           'invalid_provider_configuration', 'invalid_provider_cursor',
     *                                'upstream_license_changed', 'invalid_calendar_revision',
     *                                'calendar_schema_changed', 'calendar_coverage_changed',
     *                                'calendar_label_needs_review' nebo 'invalid_calendar_date'.
     */
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if ($language !== 'cs' || $kind !== 'calendar' || $limit < 1 || $limit > 500) { throw new SyncException('invalid_provider_configuration'); }
        $state = $cursor === null ? null : json_decode($cursor, true);
        // A retired GitHub cursor starts a fresh Wikipedia pass; never reuse its offset.
        if (is_array($state) && is_string($state['revision'] ?? null) && preg_match('/^[a-f0-9]{40}$/D', $state['revision'])) { $state = null; $cursor = null; }
        if ($cursor !== null && (!is_array($state) || ($state['source'] ?? '') !== 'wikipedia' || !is_int($state['revision'] ?? null) || $state['revision'] < 1 || !is_int($state['offset'] ?? null) || $state['offset'] < 1 || $state['offset'] > 2000)) { throw new SyncException('invalid_provider_cursor'); }
        $rights = $this->api(['action'=>'query', 'meta'=>'siteinfo', 'siprop'=>'rightsinfo']);
        $license = WikipediaNamesProvider::LICENSE_URL;
        if (!in_array($rights['query']['rightsinfo']['url'] ?? '', [$license, $license.'deed.cs', $license.'deed.en'], true)) { throw new SyncException('upstream_license_changed'); }
        $response = $this->api(['action'=>'parse', 'prop'=>'text|revid'] + ($state ? ['oldid'=>$state['revision']] : ['page'=>'Jmeniny']));
        $page = $response['parse'] ?? [];
        if (($page['title'] ?? '') !== 'Jmeniny' || ($page['pageid'] ?? null) !== 14885 || !is_int($page['revid'] ?? null) || $page['revid'] < 1 || !is_string($page['text']['*'] ?? null) || ($state && $page['revid'] !== $state['revision'])) { throw new SyncException('invalid_calendar_revision'); }
        $rows = $this->extract($page['text']['*']);
        $offset = $state['offset'] ?? 0;
        if ($offset >= count($rows)) { throw new SyncException('invalid_provider_cursor'); }
        $revision = $page['revid'];
        $url = 'https://cs.wikipedia.org/w/index.php?oldid='.$revision;
        $items = [];
        foreach (array_slice($rows, $offset, $limit) as $row) {
            $items[] = $row + ['external_id'=>'wikipedia:cs:'.$row['month'].':'.$row['day'].':'.hash('sha256', $row['title']),
                'revision'=>(string)$revision, 'source_key'=>self::SOURCE_KEY, 'source_title'=>self::TITLE,
                'source_url'=>$url, 'license'=>'CC-BY-SA-4.0', 'license_url'=>$license,
                'locator'=>'Jmeniny / Kalendář jmenin a (státních) svátků / '.$row['day'].'. '.$row['month'].'.; revize '.$revision,
                'attribution'=>'Přispěvatelé české Wikipedie; https://cs.wikipedia.org/w/index.php?title=Jmeniny&action=history; výběr jmen a dat z tabulky, rozdělení společných jmenin a odstranění HTML. CC BY-SA 4.0.',
                'payload'=>$row + ['page_id'=>14885, 'revision'=>$revision, 'license_evidence'=>$rights['query']['rightsinfo'], 'scope'=>'Český občanský kalendář podle Wikipedie, nikoli závazný či liturgický kalendář. Státní svátky se nepřebírají.']];
        }
        $next = $offset + count($items); $complete = $next >= count($rows);
        return ['items'=>$items, 'cursor'=>$complete ? null : json_encode(['source'=>'wikipedia', 'revision'=>$revision, 'offset'=>$next], JSON_THROW_ON_ERROR), 'complete'=>$complete];
    }

    /**
     * Vytáhne dny a jména z HTML tabulky české Wikipedie.
     *
     * @param  string $html HTML stránky z Wikipedie API.
     * @return list<array<string, mixed>>  Řádky se jménem, názvem dne, měsícem a dnem.
     * @throws SyncException               Při změně struktury, neplatném datu, duplicitě,
     *                                    neočekávaném počtu dnů nebo tvaru, který vyžaduje kontrolu.
     */
    private function extract(string $html): array
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        if (!$loaded) { throw new SyncException('calendar_schema_changed'); }
        $xp = new \DOMXPath($dom);
        $tables = [];
        foreach ($xp->query('//table[contains(concat(" ",normalize-space(@class)," ")," wikitable ")]') as $table) {
            $heads = $xp->query('./tr/th|./tbody/tr/th', $table);
            if ($heads->length === 4 && trim($heads->item(1)->textContent) === 'datum' && trim($heads->item(2)->textContent) === 'svátek (jmeniny)') { $tables[] = $table; }
        }
        if (count($tables) !== 1) { throw new SyncException('calendar_schema_changed'); }
        $rows = []; $dates = [];
        foreach ($xp->query('./tr|./tbody/tr', $tables[0]) as $tr) {
            $cells = $xp->query('./td', $tr);
            if ($cells->length === 0) { continue; }
            if (!in_array($cells->length, [3,4], true)) { throw new SyncException('calendar_schema_changed'); }
            $dateIndex = $cells->length === 4 ? 1 : 0;
            if (!preg_match('/^\s*(\d{1,2})\.\s*(\d{1,2})\.\s*$/uD', $cells->item($dateIndex)->textContent, $match)) { throw new SyncException('invalid_calendar_date'); }
            $day = (int)$match[1]; $month = (int)$match[2]; $date = "$month:$day";
            if (!checkdate($month, $day, 2000) || isset($dates[$date])) { throw new SyncException('invalid_calendar_date'); }
            $dates[$date] = true;
            $cell = $cells->item($dateIndex + 1);
            foreach (iterator_to_array($xp->query('.//sup|.//script|.//style', $cell)) as $node) { $node->parentNode->removeChild($node); }
            $original = trim(preg_replace('/\s+/u', ' ', $cell->textContent));
            $label = $original;
            // Reviewed annotations in the current article: never create names from observances.
            if ($month === 1 && $day === 6) { $label = str_replace(' (Tři králové)', '', $label); }
            if ($month === 11 && $day === 2) { $label = str_replace(' / Památka zesnulých', '', $label); }
            if ($label === '' && $month === 12 && $day === 25) { continue; }
            $names = preg_split('/\s*,\s*|\s+a\s+/u', $label);
            if (!$names || count($names) > 10 || count(array_unique($names)) !== count($names)) { throw new SyncException('calendar_label_needs_review'); }
            foreach ($names as $name) {
                if (!preg_match('/^[\p{L}\p{M}]+(?:-[\p{L}\p{M}]+)*$/uD', $name) || strlen($name) > 255) { throw new SyncException('calendar_label_needs_review'); }
                $rows[] = ['name'=>$name, 'title'=>$name, 'kind'=>'name_day', 'month'=>$month, 'day'=>$day, 'original_label'=>$original];
            }
        }
        if (count($dates) !== 366) { throw new SyncException('calendar_coverage_changed'); }
        usort($rows, static fn($a,$b) => [$a['month'],$a['day'],$a['title']] <=> [$b['month'],$b['day'],$b['title']]);
        return $rows;
    }
}
