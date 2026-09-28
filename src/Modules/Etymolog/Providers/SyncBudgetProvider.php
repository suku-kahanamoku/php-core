<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest,HttpResponse};
use App\Modules\Etymolog\SyncException;

/** Bound total upstream waiting in one HTTP worker step; transport stays shared. */
final class SyncBudgetProvider implements HttpClient
{
    private readonly float $deadline;
    public function __construct(private readonly HttpClient $client, int $budgetMs = 20000)
    {
        $this->deadline = hrtime(true) / 1000000 + $budgetMs;
    }
    public function send(HttpRequest $r): HttpResponse
    {
        $left = (int)floor($this->deadline - hrtime(true) / 1000000);
        if ($left < 1) { throw new SyncException('worker_time_budget_exceeded'); }
        return $this->client->send(new HttpRequest($r->url, $r->method, $r->headers, $r->body,
            min($r->timeoutMs, $left), $r->maxBytes, min($r->connectTimeoutMs, $left),
            $r->multipart, $r->sink, $r->redirectHosts, $r->maxRedirects));
    }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        throw new SyncException('parallel_worker_requests_unsupported');
    }
}
