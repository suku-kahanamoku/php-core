<?php

declare(strict_types=1);

namespace App\Modules\Transport\Api;

use App\Modules\Transport\Core\JourneyService;
use App\Modules\Transport\Core\ResourceService;
use App\Modules\Transport\Core\ProviderRegistry;
use App\Modules\Transport\Model\TransportException;

use App\Modules\Router\{Request, Response, Router};
use App\Modules\Transport\Model\JourneyQuery;
use App\Modules\Transport\Persistence\TransportRepository;

/**
 * HTTP API dopravy: vyhledávání spojů, místa, cached spojení, geometrie a zdroje.
 *
 * Všechny odpovědi jsou jednotné (`Response::success`/`Response::error`) a
 * chyby `TransportException` se překládají do tvaru `{code, ...details}` se
 * stavem z výjimky. Identifikátory v cestě se předávají službám beze změny;
 * okrsek se odvozuje z přihlášeného uživatele.
 */
final class TransportApi
{
    /**
     * @param  JourneyService        $journeys   Vyhledávání a řazení spojů.
     * @param  ResourceService       $resources  Místa a jednotlivé zdroje.
     * @param  ProviderRegistry      $registry   Poskytovatelé okurku pro `/v1/coverage`.
     * @param  TransportRepository   $repository Cached spojení a jejich geometrie.
     * @return void
     */
    public function __construct(private readonly JourneyService $journeys, private readonly ResourceService $resources, private readonly ProviderRegistry $registry, private readonly TransportRepository $repository) {}

    /**
     * Zaregistruje routy dopravy.
     *
     * - `POST /v1/journeys/search` – tělo dotazu dle `JourneyQuery::fromArray()`.
     * - `GET /v1/coverage` – veřejné definice poskytovatelů s kapacitami.
     * - `GET /v1/places?query=&limit=&state=` – vyhledání míst.
     * - `GET /v1/journeys/:id` – uložené spojení.
     * - `GET /v1/journeys/:id/geometry` – GeoJSON `FeatureCollection` úseků.
     * - `GET /v1/stops/:id`, `/v1/stops/:id/departures`, `/v1/trips/:id`,
     *   `/v1/trips/:id/realtime` – zdroje; odjezdy a poloha berou `at` a `limit`.
     *
     * @param  Router $router Router, do kterého se routy přidají.
     * @return void
     */
    public function registerRoutes(Router $router): void
    {
        $router->post('/v1/journeys/search', fn(Request $r) => $this->respond(fn() => $this->journeys->search(JourneyQuery::fromArray($r->body))));
        $router->post('/v1/cities/search', fn(Request $r) => $this->respond(fn() => $this->resources->cities(\App\Modules\Transport\Model\CityQuery::parse($r->body))));
        $router->post('/v1/places/search', fn(Request $r) => $this->respond(function () use ($r) {
            $q = \App\Modules\Transport\Model\PlaceQuery::parse($r->body);
            $result = $this->resources->places($q['query'], $q['limit'], $q['country'], $q['city'], $q['location']);
            $rows = $result['places'];
            if ($q['sort'] !== '') {
                $descending = str_contains($q['sort'], '-1') || str_contains($q['sort'], 'DESC');
                usort($rows, static fn($a, $b) => ($descending ? -1 : 1) * strcmp($a['name'], $b['name']));
            }
            return [
                'data' => array_map(static fn($row) => \App\Utils\QueryPolicy::fields($row, $q['projection']), $rows),
                'partial' => $result['partial'],
                'sources' => $result['sources']
            ];
        }));
        $router->get('/v1/coverage', fn() => $this->respond(fn() => ['providers' => array_values(array_map(fn($p) => $p->definition()->publicData($p->capabilities()), $this->registry->all()))]));
        $router->get('/v1/places', fn(Request $r) => $this->respond(fn() => $this->resources->places($this->text($r, 'query'), JourneyQuery::integer($r->get('limit', 10), 1, 50), $r->get('state') !== null ? $this->text($r, 'state') : null, $r->get('city') !== null ? $this->text($r, 'city') : null)));
        $router->get('/v1/journeys/:id', fn(Request $r, array $p) => $this->respond(fn() => $this->repository->journey($p['id'])));
        $router->get('/v1/journeys/:id/geometry', fn(Request $r, array $p) => $this->respond(
            /**
             * Poskládá geometrie všech úseků spojení do GeoJSON kolekce.
             *
             * @return array<string, mixed> Kolekce `Feature` s indexem úseku a jeho dopravním módem.
             * @throws TransportException   Pokud spojení neexistuje nebo vypršelo.
             */
            function () use ($p) {
                $journey = $this->repository->journey($p['id']);
                $features = [];
                foreach ($journey['legs'] as $i => $leg) {
                    if ($leg['geometry'] !== null) {
                        $features[] = ['type' => 'Feature', 'geometry' => $leg['geometry'], 'properties' => ['leg' => $i, 'mode' => $leg['mode']]];
                    }
                }
                return ['type' => 'FeatureCollection', 'features' => $features];
            }
        ));
        foreach (['/v1/stops/:id' => 'stop', '/v1/stops/:id/departures' => 'departures', '/v1/trips/:id' => 'trip', '/v1/trips/:id/realtime' => 'realtime'] as $path => $operation) {
            $router->get($path, fn(Request $r, array $p) => $this->respond(fn() => $this->resources->resource($operation, $p['id'], [
                'at' => JourneyQuery::date($r->get('at', gmdate('Y-m-d\TH:i:s\Z')))->format(DATE_RFC3339),
                'limit' => JourneyQuery::integer($r->get('limit', 20), 1, 50)
            ])));
        }
    }
    /**
     * Načte povinný textový parametr dotazu.
     *
     * @param  Request $r    Aktuální požadavek.
     * @param  string  $key  Klíč parametru.
     * @return string        Hodnota parametru, prázdný řetězec, pokud chybí.
     * @throws TransportException 'invalid_query', pokud hodnota není řetězec.
     */
    private function text(Request $r, string $key): string
    {
        $v = $r->get($key, '');
        if (!is_string($v)) {
            throw new TransportException('invalid_query', 'Expected string: ' . $key);
        }
        return $v;
    }
    /**
     * Provede akci a vrátí jednotnou odpověď; výjimka se přeloží na chybu API.
     *
     * @param  callable $action Akce vracící data pro úspěšnou odpověď.
     * @return never            Volání končí odpovědí (úspěch nebo chyba).
     */
    private function respond(callable $action): never
    {
        try {
            Response::success($action());
        } catch (TransportException $e) {
            Response::error($e->getMessage(), $e->status, ['code' => $e->reason] + $e->details);
        }
    }
}
