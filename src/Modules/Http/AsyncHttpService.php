<?php

declare(strict_types=1);

namespace App\Modules\Http;

use App\Modules\Http\Contracts\{AsyncHttpClient, HttpClient};

/** Workerman event loop HTTP transport. Credentials remain on individual requests. */
final class AsyncHttpService implements AsyncHttpClient
{
    private ?\Workerman\Http\Client $client = null;
    public function __construct(private readonly HttpClient $blocking)
    {
    }
    private function client(): \Workerman\Http\Client
    {
        return $this->client ??= new \Workerman\Http\Client([
            'max_conn_per_addr' => 4, 'connect_timeout' => 2, 'timeout' => 8, 'keepalive_timeout' => 3,
            'context' => ['ssl' => ['verify_peer' => true,'verify_peer_name' => true,'allow_self_signed' => false]],
        ]);
    }
    public function send(HttpRequest $r): HttpResponse
    {
        return $this->blocking->send($r);
    }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        return $this->blocking->sendAll($requests, $budgetMs, $concurrency);
    }
    public function sendAsync(HttpRequest $r, callable $complete): void
    {
        $url = parse_url($r->url);
        if (!$url || !in_array($url['scheme'] ?? '', ['http','https'], true) || isset($url['user']) || isset($url['pass']) || $r->multipart || $r->sink || $r->redirectHosts) {
            $complete(new HttpResponse(0, '', 'invalid_endpoint'));
            return;
        }
        $headers = [];
        foreach ($r->headers as $name => $value) {
            if (is_int($name)) {
                $parts = explode(':', (string)$value, 2);
                if (count($parts) !== 2) {
                    $complete(new HttpResponse(0, '', 'invalid_headers'));
                    return;
                }
                $headers[trim($parts[0])] = trim($parts[1]);
            } else {
                $headers[$name] = $value;
            }
        }
        if (is_array($r->body) && !array_filter(array_keys($headers), static fn ($name) => strcasecmp($name, 'Content-Type') === 0)) {
            $headers['Content-Type'] = 'application/json';
        }
        $done = false;
        $bytes = 0;
        $finish = static function (HttpResponse $response) use (&$done, $complete): void {
            if (!$done) {
                $done = true;
                $complete($response);
            }
        };
        $timer = \Workerman\Timer::add($r->timeoutMs / 1000, fn () => $finish(new HttpResponse(0, '', 'deadline_exceeded')), [], false);
        $this->client()->request($r->url, [
            'method' => $r->method, 'headers' => $headers,
            'data' => is_array($r->body) ? json_encode($r->body, JSON_THROW_ON_ERROR) : ($r->body ?? ''),
            'allow_redirects' => ['max' => 0],
            'response' => static function ($response) use ($r): void {
                if ((int)$response->getHeaderLine('Content-Length') > $r->maxBytes) {
                    throw new \RuntimeException('response_too_large');
                }
            },
            'progress' => static function ($chunk) use (&$bytes, $r): void {
                $bytes += strlen($chunk);
                if ($bytes > $r->maxBytes) {
                    throw new \RuntimeException('response_too_large');
                }
            },
            'success' => static function ($response) use ($r, $finish, $timer): void {
                \Workerman\Timer::del($timer);
                $body = (string)$response->getBody();
                $finish(strlen($body) > $r->maxBytes ? new HttpResponse(0, '', 'response_too_large') : new HttpResponse($response->getStatusCode(), $body, headers:$response->getHeaders()));
            },
            'error' => static function () use ($finish, $timer): void {
                \Workerman\Timer::del($timer);
                $finish(new HttpResponse(0, '', 'network_error'));
            },
        ]);
    }
}
