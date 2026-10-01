<?php

declare(strict_types=1);

namespace App\Modules\Transport\Core;

use App\Modules\Transport\Model\TransportException;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Contracts\JourneySearchProvider;
use App\Modules\Transport\Contracts\OnlineJourneySearchProvider;
use App\Modules\Transport\Model\JourneyQuery;
use App\Modules\Transport\Persistence\TransportRepository;

/**
 * Vyhledávání spojů napříč nakonfigurovanými poskytovateli.
 *
 * Nejdřív se dotazy vyřeší na místa a vyberou se jen poskytovatelé pokrývající
 * oba konce trasy. Probihá se ve dvou fázích: primární zdroje a poté záložní
 * pro ty, které selhaly nebo nemají platný jízdní řád. Odpovědi se deduplikují
 * konzervativně (identita spoje, úsek a časy), výsledky se řadí podle času
 * odjezdu nebo příjezdu a celé vyhledávání má časový rozpočet.
 */
final class JourneyService
{
    /**
     * @param  ProviderRegistry      $registry    Poskytovatelé okurku.
     * @param  HttpClient            $http        Sdílený HTTP klient.
     * @param  TransportRepository   $repository  Uložení mezipaměti a stavu poskytovatelů.
     * @param  ResourceService       $resources   Řešení míst na zastávky nebo souřadnice.
     * @return void
     */
    public function __construct(private readonly ProviderRegistry $registry, private readonly HttpClient $http, private readonly TransportRepository $repository, private readonly ResourceService $resources)
    {
    }

    /**
     * Vyhledá spojení pro dotaz a vrátí nejlepší výsledky i stav zdrojů.
     *
     * @param  JourneyQuery $query Normalizovaný dotaz.
     * @return array{journeys: list<array<string, mixed>>, partial: bool, sources: list<array<string, mixed>>, warnings: list<string>}
     *         Seřazená spojení, příznak částečných výsledků, stav zdrojů a varování.
     * @throws TransportException 'unsupported_coverage' (422), pokud trasu nepokrývá
     *                            žádný zdroj, nebo 'sources_unavailable' (503),
     *                            pokud selhal všechen zdroj včetně záložních.
     */
    public function search(JourneyQuery $query, ?\App\Modules\Transport\Model\RequestBudget $budget = null): array
    {
        $budget ??= new \App\Modules\Transport\Model\RequestBudget(8000);
        $execution = new ProviderExecutionService($this->http, $this->repository);
        $original = $query;
        $resolvedPlaces = [];
        $locationPartial = false;
        $nearest = new NearestStopService($this->registry, $this->http, $this->repository);
        $resolve = function (string $side, array $place) use ($nearest, $budget, &$resolvedPlaces, &$locationPartial): array {
            if ($place['type'] !== 'current_location') { return $this->resources->resolve($place, $budget); }
            $result = $nearest->resolve($place, $budget);
            $locationPartial = $locationPartial || $result['partial'];
            $resolvedPlaces[$side] = array_intersect_key($result['place'], array_flip(['id','name','lat','lon','source_mode']));
            return $result['place'];
        };
        $query = $query->withPlaces($resolve('from', $query->from), $resolve('to', $query->to));
        $candidates = [];
        $coveredButUnsupported = false;
        foreach ((new ProviderSelectionService($this->registry))->journeys($query) as $code => $provider) {
            if (!$provider->definition()->covers($query)) {
                continue;
            }
            if ($provider instanceof OnlineJourneySearchProvider && !$provider->supportsQuery($query)) {
                $coveredButUnsupported = true;
                continue;
            }
            if ($provider instanceof JourneySearchProvider || $provider instanceof OnlineJourneySearchProvider) {
                $candidates[$code] = $provider;
            }
        }
        if (!$candidates || !array_filter($candidates, static fn ($provider) => $provider->definition()->roleFor('journeys') === 'primary')) {
            throw new TransportException($coveredButUnsupported ? 'unsupported_capability' : 'unsupported_coverage',
                'No online journey source supports both destinations.', 422);
        }
        $sources = [];
        $journeys = [];
        $failed = [];
        $success = [];
        $limited = false;
        foreach (['primary','fallback'] as $phase) {
            $selected = [];
            foreach ($candidates as $code => $provider) {
                $definition = $provider->definition();
                if ($definition->roleFor('journeys') !== $phase || ($phase === 'fallback' && !array_intersect($definition->fallbackFor('journeys'), $failed))) { continue; }
                $config = $definition->config;
                $date = $query->time->setTimezone(new \DateTimeZone($config['timezone'] ?? 'UTC'))->format('Y-m-d');
                $ready = ($config['graph_ready'] ?? true) && (!isset($config['valid_from']) || $date >= $config['valid_from']) && (!isset($config['valid_until']) || $date <= $config['valid_until']);
                if (!$ready) {
                    $failed[] = $code;
                    $sources[] = ['provider'=>$code,'status'=>'schedule_unavailable'];
                    continue;
                }
                $selected[$code] = $provider;
            }
            $consume = function (string $code, array $data, bool $sourceLimited) use ($selected, $phase, $query, &$journeys, &$success, &$sources, &$limited): void {
                $candidateJourneys = [];
                $config = $selected[$code]->definition()->config;
                $mode = $phase === 'fallback' ? 'fallback' : ($selected[$code] instanceof \App\Modules\Transport\Contracts\ScheduleProvider ? 'schedule' : 'live');
                $source = ['provider' => $code,'status' => 'ok','mode' => $mode,'fetched_at' => gmdate(DATE_RFC3339),
                    'graph_version' => $config['graph_version'] ?? null,'snapshot_at' => $config['snapshot_at'] ?? null,
                    'valid_until' => $config['valid_until'] ?? null,
                    'attribution' => $config['attribution'] ?? null];
                if ($sourceLimited) {
                    $source['limited'] = true;
                }
                foreach ($data as $journey) {
                    $legs = $journey['legs'];
                    $first = $legs[0]['expected_departure'] ?? $legs[0]['scheduled_departure'];
                    $last = $legs[count($legs) - 1]['expected_arrival'] ?? $legs[count($legs) - 1]['scheduled_arrival'];
                    if (($query->arriveBy && strtotime($last) > $query->time->getTimestamp()) || (!$query->arriveBy && strtotime($first) < $query->time->getTimestamp()) || $journey['transfers'] > $query->maxTransfers) {
                        continue;
                    }
                    if (array_filter($legs, fn ($leg) => $leg['mode'] !== 'walk' && !in_array($leg['mode'], $query->modes, true))) {
                        continue;
                    }
                    $journey['source'] = $source;
                    $signature = hash('sha256', json_encode(array_map(fn ($leg) => [
                        $leg['trip_id'],$leg['mode'],$leg['from']['lat'],$leg['from']['lon'],
                        $leg['to']['lat'],$leg['to']['lon'],$leg['scheduled_departure'],$leg['scheduled_arrival'],
                    ], $legs), JSON_THROW_ON_ERROR));
                    $candidateJourneys[$signature] ??= $journey;
                }
                $journeys += $candidateJourneys;
                $this->repository->providerSuccess($code);
                $success[] = $code;
                $sources[] = $source;
                $limited = $limited || $sourceLimited;
            };
            $phaseBudget = $budget->child($phase === 'primary' ? 5000 : 3000);
            $results = $execution->run($selected, function ($provider, HttpClient $http) use ($query, $phaseBudget): array {
                if ($provider instanceof OnlineJourneySearchProvider) {
                    return $provider->searchOnline($query, $http, max(1, $phaseBudget->remainingMs()));
                }
                $code = $provider->definition()->code;
                $response = $http->sendAll([$code=>$provider->searchRequest($query)], max(1,$phaseBudget->remainingMs()))[$code];
                return ['journeys'=>$provider->searchResult($response, $query),'limited'=>false];
            }, $phaseBudget);
            foreach ($results as $code=>$result) {
                if ($result->status === 'configuration_error') { throw $result->error; }
                if ($result->succeeded()) {
                    try { $consume($code, $result->data['journeys'], $result->data['limited']); }
                    catch (\Throwable) {
                        $this->repository->providerFailure($code);
                        $failed[] = $code;
                        $sources[] = ['provider'=>$code,'status'=>'unavailable'];
                    }
                } else {
                    if ($result->allowsFallback()) { $failed[] = $code; }
                    $sources[] = ['provider'=>$code,'status'=>$result->status];
                }
            }
        }

        if (!$success) {
            throw new TransportException('sources_unavailable', 'Journey search is unavailable; no valid fallback is ready.', 503, ['sources' => $sources]);
        }
        $journeys = array_values($journeys);
        usort($journeys, static function ($a, $b) use ($query): int {
            if ($query->arriveBy) {
                return strtotime($b['legs'][0]['expected_departure'] ?? $b['legs'][0]['scheduled_departure']) <=> strtotime($a['legs'][0]['expected_departure'] ?? $a['legs'][0]['scheduled_departure']);
            }
            $al = $a['legs'][count($a['legs']) - 1];
            $bl = $b['legs'][count($b['legs']) - 1];
            return strtotime($al['expected_arrival'] ?? $al['scheduled_arrival']) <=> strtotime($bl['expected_arrival'] ?? $bl['scheduled_arrival']);
        });
        $selectedJourneys = array_slice($journeys, 0, $query->limit);
        foreach ($this->registry->all() as $code=>$provider) {
            if (!$selectedJourneys || !$provider instanceof \App\Modules\Transport\Contracts\JourneyEnrichmentProvider || !$provider->definition()->enabled('enrich_journeys') || $budget->remainingMs() === 0) { continue; }
            $result = $execution->run([$code=>$provider], fn ($p, HttpClient $http)=>$p->enrichJourneys($selectedJourneys, $this->registry, $http), $budget->child(1500))[$code];
            if ($result->status === 'configuration_error') { throw $result->error; }
            if ($result->succeeded()) { $selectedJourneys = $result->data; }
        }
        $warnings = [];
        $unavailable = (bool)array_filter($sources, static fn ($source) => $source['status'] !== 'ok');
        if ($unavailable) {
            $warnings[] = 'Some sources were unavailable; coverage may be incomplete.';
        }
        if ($limited) {
            $warnings[] = 'Some online journey sources use a bounded search window; additional trips may be missing.';
        }
        JourneyQuery::assertFreshLocation($original->from);
        JourneyQuery::assertFreshLocation($original->to);
        if ($query->location !== null) { JourneyQuery::assertFreshLocation($query->location); }
        return ['journeys' => array_map(fn ($journey) => $this->repository->cacheJourney($journey,
            publicStopsOnly: $original->from['type'] === 'stop' && $original->to['type'] === 'stop'), $selectedJourneys),
            'area' => ['city'=>JourneyAreaService::city($query, $selectedJourneys), 'intercity'=>JourneyAreaService::isIntercity($query, $selectedJourneys)], 'resolved_places' => $resolvedPlaces, 'partial' => $unavailable || $limited || $locationPartial,'sources' => $sources,'warnings' => $warnings];
    }

}
