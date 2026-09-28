<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest,HttpResponse};
use App\Modules\Etymolog\SyncException;

/** Pace all Wikimedia editions together; actual transport stays in the shared HttpClient. */
final class WikimediaHttpProvider implements HttpClient
{
    private float $nextRequest = 0;
    private readonly \Closure $clock;
    private readonly \Closure $sleep;
    public function __construct(private readonly HttpClient $client, ?\Closure $clock = null, ?\Closure $sleep = null)
    {
        $this->clock = $clock ?? static fn (): float => hrtime(true) / 1000000;
        $this->sleep = $sleep ?? static fn (int $microseconds) => usleep($microseconds);
    }
    public function send(HttpRequest $request): HttpResponse
    {
        if (preg_match('/^(?:[a-z-]+\.)?(?:wikidata|wikipedia|wiktionary|wikisource)\.org$/D', (string)parse_url($request->url, PHP_URL_HOST))) {
            $wait = $this->nextRequest - ($this->clock)();
            if ($wait > 0) { ($this->sleep)((int)ceil($wait * 1000)); }
            $this->nextRequest = ($this->clock)() + 1000;
        }
        return $this->client->send($request);
    }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        throw new SyncException('parallel_wikimedia_requests_unsupported');
    }
}
