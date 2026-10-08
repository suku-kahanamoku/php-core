<?php

declare(strict_types=1);

namespace App\Modules\Transport\Admin;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\HttpRequest;
use App\Modules\Transport\Gateway\JavaTransportException;

/** Server-only fixed admin routes for global online planner policy. */
final class OnlinePlannerService
{
    public function __construct(private readonly HttpClient $http, private readonly string $url, private readonly string $token)
    {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true) || strlen($token) < 24 || preg_match('/[\r\n]/', $token)) {
            throw new JavaTransportException('invalid_configuration', 'Invalid online planner admin configuration.', 503);
        }
    }

    public function onlinePlanners(): array
    {
        return $this->request('GET', path: '/admin/online-planners');
    }

    public function setOnlinePlanners(bool $enabled): array
    {
        return $this->request('POST', ['enabled' => $enabled], '/admin/online-planners');
    }

    private function request(string $method, ?array $body = null, string $path = '/admin/online-planners'): array
    {
        $response = $this->http->send(new HttpRequest(rtrim($this->url, '/') . $path, $method,
            ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'], $body,
            timeoutMs: 9000, maxBytes: 16384, connectTimeoutMs: 1500));
        if ($response->error !== null || $response->status === 0) {
            throw new JavaTransportException('source_unavailable', 'Online planner control is unavailable.', 503);
        }
        try {
            $payload = json_decode($response->body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new JavaTransportException('invalid_upstream', 'Invalid online planner response.', 502);
        }
        if (!is_array($payload) || $response->status < 200 || $response->status > 599) {
            throw new JavaTransportException('invalid_upstream', 'Invalid online planner response.', 502);
        }
        if ($response->status >= 400) {
            throw new JavaTransportException('source_unavailable', 'Online planner operation was rejected.', $response->status);
        }
        if (!isset($payload['enabled']) || !is_bool($payload['enabled'])) {
            throw new JavaTransportException('invalid_upstream', 'Invalid planner setting.', 502);
        }
        return ['enabled' => $payload['enabled']];
    }
}
