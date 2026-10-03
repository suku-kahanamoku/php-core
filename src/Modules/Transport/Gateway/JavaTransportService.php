<?php

declare(strict_types=1);

namespace App\Modules\Transport\Gateway;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\HttpRequest;

/** Transparent server-to-server gateway; routing, catalogues and realtime belong to Java. */
final class JavaTransportService
{
    public function __construct(private readonly HttpClient $http, private readonly string $url, private readonly string $token)
    {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])
            || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true) || strlen($token) < 24 || preg_match('/[\r\n]/', $token)) {
            throw new JavaTransportException('invalid_configuration', 'Invalid Java transport gateway configuration.', 503);
        }
    }

    /** @return array{status:int,payload:array<string,mixed>,retry_after:?int} */
    public function forward(string $method, string $path, array $body = [], array $query = []): array
    {
        if (!preg_match('~^/v1/(?:coverage|attributions|(?:cities|places|journeys)/search|(?:stops|trips|journeys)/[A-Za-z0-9_-]{1,2048}(?:/(?:departures|realtime|observation|tracking|geometry))?)$~D', $path)
            || !in_array($method, ['GET', 'POST'], true) || array_diff(array_keys($query), ['at', 'limit', 'stop_coordinates'])
            || ($path === '/v1/attributions' && ($method !== 'GET' || $query !== []))) {
            throw new JavaTransportException('invalid_query', 'Unsupported Java transport route.', 422);
        }
        $target = rtrim($this->url, '/') . '/transport' . $path;
        if ($query !== []) {
            $target .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $response = $this->http->send(new HttpRequest($target, $method,
            ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'],
            $method === 'POST' ? ($body === [] ? '{}' : $body) : null,
            timeoutMs: $path === '/v1/journeys/search' ? 24000 : 9000, maxBytes: 16000000, connectTimeoutMs: 1500));
        if ($response->error !== null || $response->status === 0) {
            throw new JavaTransportException('source_unavailable', 'Java transport is unavailable.', 503);
        }
        try {
            $payload = json_decode($response->body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new JavaTransportException('invalid_upstream', 'Invalid Java transport response.', 502);
        }
        if (!is_array($payload) || !is_bool($payload['success'] ?? null)
            || ($payload['success'] && !array_key_exists('data', $payload))
            || $response->status < 200 || $response->status > 599) {
            throw new JavaTransportException('invalid_upstream', 'Invalid Java transport response.', 502);
        }
        return ['status' => $response->status, 'payload' => $payload, 'retry_after' => $response->retryAfter];
    }
}
