<?php
declare(strict_types=1);
namespace App\Modules\Transport\Core;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest,HttpResponse};
use App\Modules\Transport\Contracts\Provider;
use App\Modules\Transport\Model\{PendingHttpBatch,ProviderResult,RequestBudget,TransportException};
use App\Modules\Transport\Persistence\{TransportRepository,ProviderQuotaRepository};

/** Runs single- and multi-stage integrations in bounded concurrent HTTP rounds. */
final class ProviderExecutionService
{
    private readonly ProviderQuotaRepository $quotas;
    public function __construct(private readonly HttpClient $http, private readonly TransportRepository $repository)
    {
        $this->quotas = new ProviderQuotaRepository($repository->db);
    }

    /** @param array<string,Provider> $providers @param callable(Provider,HttpClient):mixed $operation */
    public function run(array $providers, callable $operation, RequestBudget $budget): array
    {
        $results = $fibers = $clients = $pending = $dispatched = [];
        $advance = function (string $code, ?array $responses = null) use (&$results,&$fibers,&$clients,&$pending,&$dispatched): void {
            try {
                $batch = $fibers[$code]->isStarted() ? $fibers[$code]->resume($responses) : $fibers[$code]->start();
                if ($fibers[$code]->isTerminated()) {
                    $results[$code] = new ProviderResult('ok', $fibers[$code]->getReturn());
                    if (isset($dispatched[$code])) { $this->repository->providerSuccess($code); }
                    unset($pending[$code]);
                } elseif ($batch instanceof PendingHttpBatch) {
                    $pending[$code] = $batch;
                } else { throw new \LogicException('Provider suspended outside an HTTP batch.'); }
            } catch (\Throwable $e) {
                $status = match (true) {
                    $e instanceof TransportException && $e->status === 500 => 'configuration_error',
                    $e instanceof TransportException && $e->reason === 'provider_throttled' => 'throttled',
                    $e instanceof TransportException && in_array($e->reason, ['deadline_exceeded','request_limit_exceeded'], true) => 'deadline_exceeded',
                    $e instanceof TransportException && $e->status === 404 => 'not_found',
                    $e instanceof TransportException && $e->reason === 'unsupported_capability' => 'unsupported_capability',
                    $e instanceof TransportException && in_array($e->reason, ['unsupported_time', 'invalid_id', 'invalid_query', 'invalid_date', 'invalid_limit'], true) => 'invalid_request',
                    default => 'unavailable',
                };
                $results[$code] = new ProviderResult($status, error: $e);
                if ($status === 'unavailable') { $this->repository->providerFailure($code, $clients[$code]->retryAfter); }
                unset($pending[$code]);
            }
        };
        foreach ($providers as $code=>$provider) {
            if ($provider->definition()->tenant !== $this->repository->tenant) { throw new \LogicException('Provider tenant mismatch.'); }
            if ($budget->remainingMs() === 0) { $results[$code] = new ProviderResult('deadline_exceeded'); continue; }
            if (!$this->repository->acquireProvider($code)) {
                $results[$code] = new ProviderResult($this->repository->providerOutage($code) ? 'unavailable' : 'throttled');
                continue;
            }
            $clients[$code] = new ProviderHttpService($budget);
            $fibers[$code] = new \Fiber(fn ()=>$operation($provider, $clients[$code]));
            $advance($code);
        }
        while ($pending) {
            $requests = $routes = [];
            // Admit only requests that can actually start this round. No hidden HTTP queue
            // may outlive a provider batch deadline or consume another provider's budget.
            $perProvider = max(1, intdiv(4, count($pending)));
            foreach ($pending as $code=>$batch) {
                $admitted = 0;
                foreach ($batch->requests as $key=>$request) {
                    if (count($requests) >= 4 || $admitted >= min($perProvider, $batch->concurrency)) { break; }
                    $remaining = min($budget->remainingMs(), $batch->budget->remainingMs());
                    if ($remaining === 0) {
                        $batch->responses[$key] = new HttpResponse(0, '', 'request_budget_exceeded');
                        unset($batch->requests[$key]);
                        continue;
                    }
                    try { $delay = $this->quotas->reserve($providers[$code]->definition()); }
                    catch (\Throwable $e) {
                        // Misconfiguration is explicit, never interpreted as a source outage.
                        $results[$code] = new ProviderResult('configuration_error', error: $e);
                        unset($pending[$code]);
                        break;
                    }
                    if ($delay >= $remaining) {
                        $batch->responses[$key] = new HttpResponse(0, '', 'provider_throttled');
                        unset($batch->requests[$key]);
                        continue;
                    }
                    if ($delay > 0) { continue; }
                    $dispatch = $key;
                    while (array_key_exists($dispatch, $routes)) { $dispatch = $code.'::'.$dispatch; }
                    $requests[$dispatch] = $request;
                    $routes[$dispatch] = [$code,$key];
                    ++$admitted;
                    unset($batch->requests[$key]);
                }
            }
            if ($requests) {
                $responses = [];
                foreach ($requests as $key=>$request) {
                    [$code] = $routes[$key];
                    if (!isset($pending[$code])) { unset($requests[$key]); continue; }
                    $remaining = min($budget->remainingMs(), $pending[$code]->budget->remainingMs());
                    if ($remaining === 0) {
                        $responses[$key] = new HttpResponse(0, '', 'request_budget_exceeded');
                        unset($requests[$key]);
                        continue;
                    }
                    $requests[$key] = new HttpRequest(
                        $request->url, $request->method, $request->headers, $request->body,
                        min($request->timeoutMs,$remaining), $request->maxBytes,
                        min($request->connectTimeoutMs,$remaining), $request->multipart,
                        $request->sink, $request->redirectHosts, $request->maxRedirects,
                    );
                }
                if ($requests) {
                    foreach (array_keys($requests) as $key) { $dispatched[$routes[$key][0]] = true; }
                    $responses += $this->http->sendAll($requests, max(1,$budget->remainingMs()), 4);
                }
                foreach ($responses as $key=>$response) {
                    [$code,$original] = $routes[$key];
                    if (!isset($pending[$code])) { continue; }
                    $pending[$code]->responses[$original] = $response;
                    if ($response->retryAfter !== null) { $this->quotas->block($providers[$code]->definition(), $response->retryAfter); }
                }
            }
            // Rotate admitted providers behind waiting ones, including large unfinished batches.
            foreach (array_unique(array_column($routes, 0)) as $code) {
                if (isset($pending[$code])) {
                    $batch = $pending[$code];
                    unset($pending[$code]);
                    $pending[$code] = $batch;
                }
            }
            $advanced = false;
            foreach (array_keys($pending) as $code) {
                if (!$pending[$code]->requests) {
                    $responses = $pending[$code]->responses;
                    unset($pending[$code]);
                    $advance($code, $responses);
                    $advanced = true;
                }
            }
            if (!$requests && !$advanced && $pending && $budget->remainingMs() > 0) { usleep(min(10000, $budget->remainingMs() * 1000)); }
        }
        return $results;
    }
}
