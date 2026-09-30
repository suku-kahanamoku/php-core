<?php
declare(strict_types=1);
namespace App\Modules\Transport\Core;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest,HttpResponse};
use App\Modules\Transport\Model\{PendingHttpBatch,RequestBudget,TransportException};

/** Cooperative adapter facade; the underlying network client remains HttpModule's client. */
final class ProviderHttpService implements HttpClient
{
    public ?int $retryAfter = null;
    private int $requests = 0;
    public function __construct(private readonly RequestBudget $budget) {}

    public function send(HttpRequest $request): HttpResponse
    {
        return $this->sendAll([$request], $request->timeoutMs, 1)[0];
    }

    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        if ($budgetMs < 1 || $concurrency < 1 || $concurrency > 32 || array_filter($requests, fn ($r)=>!$r instanceof HttpRequest)) {
            throw new \InvalidArgumentException('Invalid provider HTTP batch.');
        }
        if (!$requests) { return []; }
        $this->requests += count($requests);
        if ($this->requests > 128) { throw new TransportException('request_limit_exceeded', 'Provider request limit reached.', 503); }
        if ($this->budget->remainingMs() === 0) { throw new TransportException('deadline_exceeded', 'Request budget exhausted.', 503); }
        $responses = \Fiber::suspend(new PendingHttpBatch($requests, $this->budget->child($budgetMs), $concurrency));
        foreach ($responses as $response) {
            $this->retryAfter = $response->retryAfter ?? $this->retryAfter;
            if (in_array($response->error, ['provider_throttled','request_budget_exceeded'], true)) {
                throw new TransportException($response->error === 'request_budget_exceeded' ? 'deadline_exceeded' : $response->error, 'Local execution budget or quota exhausted.', 503);
            }
        }
        return $responses;
    }
}
