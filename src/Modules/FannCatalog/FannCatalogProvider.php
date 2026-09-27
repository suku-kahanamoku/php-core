<?php

declare(strict_types=1);

namespace App\Modules\FannCatalog;

use App\Modules\Http\{HttpModule, HttpRequest, HttpResponse};
use App\Modules\Http\Contracts\HttpClient;
use RuntimeException;

/** FAnn-specific URL/content policy; all network I/O belongs to HttpModule. */
final class FannCatalogProvider
{
    private const ALLOWED_HOST = 'www.fann.cz';

    public function __construct(
        private readonly int $timeoutSeconds = 30,
        private readonly string $userAgent = 'FAnnCatalogImporter/1.0 (+https://www.charter-agency.com/)',
        private readonly ?HttpClient $http = null,
    ) {
    }

    public function get(string $url): string
    {
        return $this->content(($this->http ?? HttpModule::client())->send($this->request($url)));
    }

    /** @param list<string> $urls @return array<string,string> */
    public function getMany(array $urls, int $concurrency = 4): array
    {
        $requests = [];
        foreach (array_unique($urls) as $url) {
            $requests[$url] = $this->request($url);
        }
        if ($requests === []) {
            return [];
        }
        $concurrency = max(1, min(6, $concurrency));
        $budget = (int)ceil(count($requests) / $concurrency) * $this->timeoutSeconds * 1000;
        $responses = ($this->http ?? HttpModule::client())->sendAll($requests, $budget, $concurrency);
        return array_map(fn (HttpResponse $response) => $this->content($response), $responses);
    }

    private function request(string $url): HttpRequest
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== self::ALLOWED_HOST || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new RuntimeException('Unsupported FAnn URL.');
        }
        return new HttpRequest(
            $url,
            headers: ['Accept' => 'text/html,application/xhtml+xml', 'User-Agent' => $this->userAgent],
            timeoutMs: $this->timeoutSeconds * 1000,
            maxBytes: 16000000,
            connectTimeoutMs: 10000,
            redirectHosts: [self::ALLOWED_HOST]
        );
    }

    private function content(HttpResponse $response): string
    {
        if ($response->error !== null || $response->status !== 200 || $response->body === '') {
            throw new RuntimeException('FAnn request failed ('.$response->status.').');
        }
        $type = $response->header('Content-Type');
        if ($type !== '' && !str_contains(strtolower($type), 'text/html')) {
            throw new RuntimeException('Unexpected FAnn content type.');
        }
        return $response->body;
    }
}
