<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\{ResourceRegistry, SyncException};
use App\Modules\Etymolog\Contracts\NameProvider;
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest, HttpException};

/**
 * Jména a jejich tvrzení z Wikidat (CC0), objevená přes CirrusSearch.
 *
 * Zásadní pravidlo: z popisku entity se nikdy nevymýšlí etymologie. Uloží se jen
 * to, co entita skutečně tvrdí, včetně referencí a kvalifikátorů, takže
 * původ jména musí dohledat člověk. Vyhledávací index může zaostávat, proto se
 * každá nalezená entita znovu ověří proti jejím vlastním tvrzením o typu a
 * jazyce.
 *
 * Vyhledávání má omezené okno výsledků; když se dosáhne jeho hranice, dávka
 * skončí chybou s doporučeným odstupem, nikoli se označí za dokončenou.
 */
final class WikidataProvider implements NameProvider
{
    /** Kód licence CC0-1.0 podle databáze zdrojů. */
    public const LICENSE = 'CC0-1.0';

    /** Mapování jazyků na Q-ID položky jazyka ve Wikidatech. */
    private const LANGUAGE_ITEMS = ['cs' => 'Q9056', 'sk' => 'Q9058', 'pl' => 'Q809', 'uk' => 'Q8798', 'de' => 'Q188', 'en' => 'Q1860'];

    /** Mapování druhů jmen na povolené třídy entit. */
    private const TYPES = ['surname' => ['Q101352'], 'given' => ['Q202444', 'Q12308941', 'Q11879590', 'Q3409032']];

    /** Adresa textu licence CC0-1.0. */
    public const LICENSE_URL = 'https://creativecommons.org/publicdomain/zero/1.0/';

    /** Uvedení autora zdroje. */
    public const ATTRIBUTION = 'Wikidata contributors';

    /**
     * @param  HttpClient $http      Sdílený HTTP klient.
     * @param  string     $userAgent Identifikace klienta pro Wikidata API.
     * @return void
     * @throws \InvalidArgumentException Pokud User-Agent obsahuje zalomení řádku, je prázdný nebo delší než 512 znaků.
     */
    public function __construct(private readonly HttpClient $http, private readonly string $userAgent = 'Etymolog/1.0 (https://etymolog.prasentace.cz; name history research)')
    {
        if (preg_match('/[\r\n]/', $userAgent) || strlen($userAgent) > 512 || trim($userAgent) === '') {
            throw new \InvalidArgumentException('Invalid Wikidata User-Agent');
        }
    }

    /**
     * Stáhne jednu dávku entit podle offsetu vyhledávání.
     *
     * @param  string     $language Jazyk, který musí entita mít v `P407`.
     * @param  string     $kind     'given' nebo 'surname'.
     * @param  string|null $cursor  Offset jako číselný řetězec, nebo null pro začátek.
     * @param  int        $limit    Maximální počet entit v dávce (1–50).
     * @return array{items:list<array<string, mixed>>, cursor:?string, complete:bool} Dávka položek.
     * @throws SyncException           'invalid_provider_configuration', 'search_window_exceeded',
     *                                'invalid_discovery_response', 'invalid_discovery_cursor',
     *                                'invalid_entity_response', 'invalid_entity_label'
     *                                nebo chyba upstreamu.
     */
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if (!in_array($language, ResourceRegistry::LANGUAGES, true) || !in_array($kind, ['given', 'surname'], true) || $limit < 1 || $limit > 50 ||
            ($cursor !== null && !preg_match('/^(0|[1-9][0-9]{0,8})$/D', $cursor))) {
            throw new SyncException('invalid_provider_configuration');
        }
        $offset = (int)($cursor ?? '0');
        // CirrusSearch has a finite result window; never report a truncated pass as complete.
        if ($offset >= 10000) {
            throw new SyncException('search_window_exceeded', 86400);
        }
        $limit = min($limit, 10000 - $offset);
        $types = self::TYPES[$kind];
        $query = 'haswbstatement:'.implode('|', array_map(static fn ($type) => 'P31='.$type, $types)).' haswbstatement:P407='.self::LANGUAGE_ITEMS[$language];
        $discovery = $this->json('https://www.wikidata.org/w/api.php?'.http_build_query([
            'action' => 'query', 'list' => 'search', 'srsearch' => $query, 'srnamespace' => 0,
            'srprop' => '', 'srsort' => 'create_timestamp_asc', 'sroffset' => $offset,
            'srlimit' => $limit, 'format' => 'json', 'maxlag' => 5,
        ]));
        $results = $discovery['query']['search'] ?? null;
        if (!is_array($results) || !array_is_list($results) || count($results) > $limit) {
            throw new SyncException('invalid_discovery_response');
        }
        $next = $discovery['continue']['sroffset'] ?? null;
        if ($next !== null && (!is_int($next) || $next <= $offset || $next > $offset + $limit)) {
            throw new SyncException('invalid_discovery_cursor');
        }
        $ids = [];
        foreach ($results as $result) {
            $id = $result['title'] ?? null;
            if (!is_string($id) || !preg_match('/^Q[1-9][0-9]{0,18}$/D', $id) || in_array($id, $ids, true)) {
                throw new SyncException('invalid_discovery_response');
            }
            $ids[] = $id;
        }
        if ($ids === []) {
            if ($next !== null) {
                throw new SyncException('invalid_discovery_cursor');
            }
            return ['items' => [], 'cursor' => null, 'complete' => true];
        }
        $response = $this->json('https://www.wikidata.org/w/api.php?'.http_build_query([
            'action' => 'wbgetentities', 'ids' => implode('|', $ids), 'props' => 'info|labels|descriptions|aliases|claims',
            'format' => 'json', 'maxlag' => 5,
        ]));
        $items = [];
        foreach ($ids as $id) {
            $entity = $response['entities'][$id] ?? null;
            if (!is_array($entity) || isset($entity['missing']) || ($entity['id'] ?? null) !== $id || !isset($entity['lastrevid'])) {
                throw new SyncException('invalid_entity_response');
            }
            // Validate the current entity as the search index can lag behind edits.
            $classes = $this->itemClaims($entity, 'P31');
            $languages = $this->itemClaims($entity, 'P407');
            if (array_intersect($classes, $types) === [] || !in_array(self::LANGUAGE_ITEMS[$language], $languages, true)) {
                continue;
            }
            $name = $entity['labels'][$language]['value'] ?? $entity['labels']['mul']['value'] ?? $entity['labels']['en']['value'] ?? null;
            if (!is_string($name) || trim($name) === '' || strlen($name) > 255) {
                throw new SyncException('invalid_entity_label');
            }
            // Preserve references and qualifiers. Do not fabricate an etymology from a description.
            $items[] = ['external_id' => $id, 'name' => trim($name), 'revision' => (string)$entity['lastrevid'],
                'source_url' => 'https://www.wikidata.org/wiki/'.$id,
                'payload' => array_intersect_key($entity, array_flip(['id', 'lastrevid', 'modified', 'labels', 'descriptions', 'aliases', 'claims']))];
        }
        return ['items' => $items, 'cursor' => $next === null ? null : (string)$next, 'complete' => $next === null];
    }

    /**
     * Vrátí hodnoty tvrzení, která odkazují na konkrétní entitu.
     *
     * Zastaralá tvrzení a tvrzení s jiným typem hodnoty se přeskakují, aby se do
     * databáze nedostaly nejednoznačné vazby.
     *
     * @param  array<string, mixed> $entity   Entita z `wbgetentities`.
     * @param  string               $property ID vlastnosti (např. 'P31', 'P407').
     * @return list<string>                  Q-ID hodnot vlastnosti.
     */
    private function itemClaims(array $entity, string $property): array
    {
        $ids = [];
        foreach ($entity['claims'][$property] ?? [] as $claim) {
            if (($claim['rank'] ?? '') === 'deprecated' || ($claim['mainsnak']['snaktype'] ?? '') !== 'value') {
                continue;
            }
            $id = $claim['mainsnak']['datavalue']['value']['id'] ?? null;
            if (is_string($id)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Provede dotaz do Wikidat API a vrátí dekódovanou odpověď.
     *
     * Adresy se skládají výhradně zde, nikoli z adres ze zdrojů nebo od požadavku,
     * a přesměrování se nepovoluje.
     *
     * @param  string $url    Sestavená adresa API.
     * @param  string $accept Hodnota hlavičky `Accept`.
     * @return array<string, mixed> Dekódovaná odpověď.
     * @throws SyncException  'upstream_rate_limited', 'upstream_unavailable',
     *                         'invalid_upstream_json' nebo 'upstream_api_error'.
     */
    private function json(string $url, string $accept = 'application/json'): array
    {
        // Endpoints are built here, never from source URLs or user supplied URLs. No redirects.
        $response = $this->http->send(new HttpRequest($url, headers: ['Accept' => $accept, 'User-Agent' => $this->userAgent],
            timeoutMs: 25000, connectTimeoutMs: 5000, maxBytes: 8000000));
        if (!$response->successful()) {
            throw new SyncException($response->status === 429 ? 'upstream_rate_limited' : 'upstream_unavailable', max(300, min(604800, $response->retryAfter ?? 300)));
        }
        try {
            $data = $response->json();
        } catch (HttpException) {
            throw new SyncException('invalid_upstream_json');
        }
        if (in_array($data['error']['code'] ?? '', ['ratelimited', 'maxlag'], true)) {
            throw new SyncException('upstream_rate_limited', max(300, min(604800, $response->retryAfter ?? 300)));
        }
        if (isset($data['error']) || isset($data['errors'])) {
            throw new SyncException('upstream_api_error', max(300, min(604800, $response->retryAfter ?? 300)));
        }
        return $data;
    }
}
