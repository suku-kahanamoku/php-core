<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Contracts\{JourneySearchProvider,OnlineJourneySearchProvider};
use App\Modules\Transport\DTO\JourneyQuery;
use App\Modules\Transport\Repositories\TransportRepository;

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
    public function search(JourneyQuery $query): array
    {
        $query = $query->withPlaces($this->resources->resolve($query->from), $this->resources->resolve($query->to));
        $candidates = [];
        $coveredButUnsupported = false;
        foreach ($this->registry->all() as $code => $provider) {
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
        if (!$candidates || !array_filter($candidates, static fn ($provider) => $provider->definition()->role === 'primary')) {
            throw new TransportException($coveredButUnsupported ? 'unsupported_capability' : 'unsupported_coverage',
                'No online journey source supports both destinations.', 422);
        }
        $deadline = microtime(true) + 8;
        $sources = [];
        $journeys = [];
        $failed = [];
        $success = [];
        $limited = false;
        foreach (['primary','fallback'] as $phase) {
            $selected = [];
            $online = [];
            $requests = [];
            foreach ($candidates as $code => $provider) {
                $definition = $provider->definition();
                if ($definition->role !== $phase || ($phase === 'fallback' && !array_intersect($definition->fallbackFor, $failed))) {
                    continue;
                }
                $config = $definition->config;
                $date = $query->time->setTimezone(new \DateTimeZone($config['timezone'] ?? 'UTC'))->format('Y-m-d');
                $ready = ($config['graph_ready'] ?? true) && (!isset($config['valid_from']) || $date >= $config['valid_from']) && (!isset($config['valid_until']) || $date <= $config['valid_until']);
                if (!$ready || !$this->repository->acquireProviderAfterInterval($code, (int)($config['min_interval_ms'] ?? 0))) {
                    if (!$ready || $this->repository->providerOutage($code)) {
                        $failed[] = $code;
                    }
                    $sources[] = ['provider' => $code,'status' => $ready ? 'temporarily_unavailable' : 'schedule_unavailable'];
                    continue;
                }
                $selected[$code] = $provider;
                if ($provider instanceof OnlineJourneySearchProvider) {
                    $online[$code] = $provider;
                } else {
                    $requests[$code] = $provider->searchRequest($query);
                }
            }
            $consume = function (string $code, array $data, bool $sourceLimited) use ($selected, $phase, $query, &$journeys, &$success, &$sources, &$limited): void {
                $candidateJourneys = [];
                $config = $selected[$code]->definition()->config;
                $mode = $phase === 'fallback' ? 'fallback' : ($selected[$code]->definition()->adapter === 'otp_transmodel' ? 'schedule' : 'live');
                $source = ['provider' => $code,'status' => 'ok','mode' => $mode,'fetched_at' => gmdate(DATE_RFC3339),
                    'graph_version' => $config['graph_version'] ?? null,'valid_until' => $config['valid_until'] ?? null,
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
            $budget = max(1, min($phase === 'primary' ? 5000 : 3000, (int)(($deadline - microtime(true)) * 1000)));
            foreach ($this->http->sendAll($requests, $budget) as $code => $response) {
                try {
                    $consume($code, $selected[$code]->searchResult($response), false);
                } catch (\Throwable) {
                    $this->repository->providerFailure($code, $response->retryAfter);
                    $failed[] = $code;
                    $sources[] = ['provider' => $code,'status' => 'unavailable'];
                }
            }
            foreach ($online as $code => $provider) {
                try {
                    $budget = max(1, min(5000, (int)(($deadline - microtime(true)) * 1000)));
                    $result = $provider->searchOnline($query, $this->http, $budget);
                    $consume($code, $result['journeys'], $result['limited']);
                } catch (\Throwable) {
                    $this->repository->providerFailure($code);
                    $failed[] = $code;
                    $sources[] = ['provider' => $code,'status' => 'unavailable'];
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
        $selectedJourneys = (new PidJourneyEnrichmentService($this->registry, $this->http, $this->repository))->enrich(array_slice($journeys, 0, $query->limit));
        $warnings = [];
        $unavailable = (bool)array_filter($sources, static fn ($source) => $source['status'] !== 'ok');
        if ($unavailable) {
            $warnings[] = 'Some sources were unavailable; coverage may be incomplete.';
        }
        if ($limited) {
            $warnings[] = 'PID online search currently covers direct trips within three hours; transfers and later trips may be missing.';
        }
        return ['journeys' => array_map(fn ($journey) => $this->repository->cacheJourney($journey,
            publicStopsOnly: $query->from['type'] === 'stop' && $query->to['type'] === 'stop'), $selectedJourneys),
            'partial' => $unavailable || $limited,'sources' => $sources,'warnings' => $warnings];
    }

}
