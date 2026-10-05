<?php

declare(strict_types=1);

namespace App\Modules\Transport\Admin;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\HttpRequest;
use App\Modules\Transport\Gateway\JavaTransportException;

/** Server-only fixed admin routes for pipeline jobs and global online planner policy. */
final class LocalPipelineService
{
    public function __construct(private readonly HttpClient $http, private readonly string $url, private readonly string $token)
    {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true) || strlen($token) < 24 || preg_match('/[\r\n]/', $token)) {
            throw new JavaTransportException('invalid_configuration', 'Invalid local pipeline admin configuration.', 503);
        }
    }

    /** Enqueues work and returns immediately; never runs a multi-hour build inside PHP HTTP. */
    public function submit(string $action): array
    {
        if (!in_array($action, ['sync_build', 'deploy'], true)) {
            throw new JavaTransportException('invalid_query', 'Unsupported local pipeline operation.', 422);
        }
        return $this->request('POST', ['action' => $action]);
    }

    public function status(): array
    {
        return $this->request('GET');
    }

    public function onlinePlanners(): array
    {
        return $this->request('GET', path: '/admin/online-planners');
    }

    public function setOnlinePlanners(bool $enabled): array
    {
        return $this->request('POST', ['enabled' => $enabled], '/admin/online-planners');
    }

    private function request(string $method, ?array $body = null, string $path = '/admin/local/jobs'): array
    {
        $response = $this->http->send(new HttpRequest(rtrim($this->url, '/') . $path, $method,
            ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'], $body,
            timeoutMs: 9000, maxBytes: 16384, connectTimeoutMs: 1500));
        if ($response->error !== null || $response->status === 0) {
            throw new JavaTransportException('source_unavailable', 'Local pipeline control is unavailable.', 503);
        }
        try {
            $payload = json_decode($response->body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new JavaTransportException('invalid_upstream', 'Invalid local pipeline response.', 502);
        }
        if (!is_array($payload) || $response->status < 200 || $response->status > 599) {
            throw new JavaTransportException('invalid_upstream', 'Invalid local pipeline response.', 502);
        }
        if ($response->status >= 400) {
            throw new JavaTransportException('source_unavailable', 'Local pipeline operation was rejected.', $response->status);
        }
        if ($path === '/admin/online-planners') {
            if (!isset($payload['enabled']) || !is_bool($payload['enabled'])) {
                throw new JavaTransportException('invalid_upstream', 'Invalid planner setting.', 502);
            }
            return ['enabled' => $payload['enabled']];
        }
        if (!in_array($payload['status'] ?? null, ['idle', 'queued', 'running', 'ready', 'failed'], true)
            || isset($payload['lease']) || isset($payload['expires'])
            || (($payload['status'] ?? '') !== 'idle'
                && (!preg_match('/^[a-f0-9-]{36}$/D', (string)($payload['id'] ?? ''))
                    || !in_array($payload['action'] ?? null, ['sync_build', 'deploy'], true)))) {
            throw new JavaTransportException('invalid_upstream', 'Invalid local pipeline state.', 502);
        }
        return $payload;
    }
}
