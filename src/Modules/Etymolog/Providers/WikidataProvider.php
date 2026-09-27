<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\{ResourceRegistry, SyncException};
use App\Modules\Etymolog\Contracts\NameProvider;
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest, HttpException};

final class WikidataProvider implements NameProvider
{
    public const LICENSE = 'CC0-1.0';
    public const LICENSE_URL = 'https://creativecommons.org/publicdomain/zero/1.0/';
    public const ATTRIBUTION = 'Wikidata contributors';

    public function __construct(private readonly HttpClient $http, private readonly string $userAgent = 'Etymolog/1.0 (php-core; Wikidata name catalog)')
    {
        if (preg_match('/[\r\n]/', $userAgent) || strlen($userAgent) > 512 || trim($userAgent) === '') {
            throw new \InvalidArgumentException('Invalid Wikidata User-Agent');
        }
    }

    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if (!in_array($language, ResourceRegistry::LANGUAGES, true) || !in_array($kind, ['given', 'surname'], true) || $limit < 1 || $limit > 50 ||
            ($cursor !== null && !preg_match('/^Q[1-9][0-9]{0,18}$/D', $cursor))) {
            throw new SyncException('invalid_provider_configuration');
        }
        $class = $kind === 'given' ? 'Q202444' : 'Q101352';
        $after = $cursor === null ? '' : 'FILTER(STR(?item) > "http://www.wikidata.org/entity/'.$cursor.'")';
        // A label in Czech does NOT establish Czech nationality or Czech name origin.
        $query = 'SELECT DISTINCT ?item WHERE { ?item <http://www.wikidata.org/prop/direct/P31>/<http://www.wikidata.org/prop/direct/P279>* <http://www.wikidata.org/entity/'.$class.'> . '
            .'?item <http://www.w3.org/2000/01/rdf-schema#label> ?label . FILTER(LANG(?label) = "'.$language.'") '.$after.' } ORDER BY STR(?item) LIMIT '.$limit;
        $discovery = $this->json('https://query.wikidata.org/sparql?'.http_build_query(['query' => $query, 'format' => 'json']), 'application/sparql-results+json');
        $bindings = $discovery['results']['bindings'] ?? null;
        if (!is_array($bindings) || !array_is_list($bindings) || count($bindings) > $limit) {
            throw new SyncException('invalid_discovery_response');
        }
        $ids = [];
        foreach ($bindings as $binding) {
            $uri = $binding['item']['value'] ?? null;
            if (!is_string($uri) || !preg_match('~^https?://www\.wikidata\.org/entity/(Q[1-9][0-9]{0,18})$~D', $uri, $m)) {
                throw new SyncException('invalid_discovery_response');
            }
            if (($cursor !== null && strcmp($m[1], $cursor) <= 0) || in_array($m[1], $ids, true)) {
                throw new SyncException('invalid_discovery_cursor');
            }
            $ids[] = $m[1];
        }
        $sorted = $ids;
        sort($sorted, SORT_STRING);
        if ($ids !== $sorted) {
            throw new SyncException('invalid_discovery_order');
        }
        if ($ids === []) {
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
            $name = $entity['labels'][$language]['value'] ?? null;
            if (!is_string($name) || trim($name) === '' || strlen($name) > 255) {
                throw new SyncException('invalid_entity_label');
            }
            // Preserve references and qualifiers. Do not fabricate an etymology from a description.
            $items[] = ['external_id' => $id, 'name' => trim($name), 'revision' => (string)$entity['lastrevid'],
                'source_url' => 'https://www.wikidata.org/wiki/'.$id,
                'payload' => array_intersect_key($entity, array_flip(['id', 'lastrevid', 'modified', 'labels', 'descriptions', 'aliases', 'claims']))];
        }
        return ['items' => $items, 'cursor' => end($ids), 'complete' => count($ids) < $limit];
    }

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
        if (isset($data['error']) || isset($data['errors'])) {
            throw new SyncException('upstream_api_error', max(300, min(604800, $response->retryAfter ?? 300)));
        }
        return $data;
    }
}
