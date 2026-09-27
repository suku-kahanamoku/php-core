<?php

declare(strict_types=1);

namespace App\Modules\Http;

use App\Modules\Http\Contracts\HttpClient;
use GuzzleHttp\{Client, ClientInterface, Pool};
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\{Create, PromiseInterface};
use GuzzleHttp\Psr7\{Uri, UriResolver, Utils};
use Psr\Http\Message\ResponseInterface;

/** The sole HTTP transport: bounded concurrency, deadlines, TLS and safe error results. */
final class HttpService implements HttpClient
{
    private readonly ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        // Explicit async-capable handler: a synchronous stream fallback would break batch deadlines.
        $this->client = $client ?? new Client(['handler' => HandlerStack::create(new CurlMultiHandler(['select_timeout' => 0.02]))]);
    }

    public function send(HttpRequest $request): HttpResponse
    {
        return $this->sendAll([$request], $request->timeoutMs, 1)[0];
    }

    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        if ($budgetMs < 1 || $concurrency < 1 || $concurrency > 32) {
            throw new \InvalidArgumentException('Invalid HTTP batch limits.');
        }
        foreach ($requests as $request) {
            if (!$request instanceof HttpRequest) {
                throw new \InvalidArgumentException('Expected HttpRequest.');
            }
        }
        $deadline = hrtime(true) / 1e9 + $budgetMs / 1000;
        $results = [];
        $jobs = function () use ($requests, $deadline): \Generator {
            foreach ($requests as $key => $request) {
                yield $key => fn () => $this->transfer($request, $request->url, min($deadline, hrtime(true) / 1e9 + $request->timeoutMs / 1000));
            }
        };
        $pool = new Pool($this->client, $jobs(), [
            'concurrency' => $concurrency,
            'fulfilled' => static function (HttpResponse $response, $key) use (&$results): void {
                $results[$key] = $response;
            },
            'rejected' => static function ($reason, $key) use (&$results): void {
                $results[$key] = new HttpResponse(0, '', 'network_error');
            },
        ]);
        $pool->promise()->wait();
        // Stable keys/order even when requests complete in a different order.
        return array_replace(array_fill_keys(array_keys($requests), null), $results);
    }

    private function transfer(HttpRequest $request, string $url, float $deadline, int $redirects = 0): PromiseInterface
    {
        $sink = null;
        try {
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) {
                return Create::promiseFor(new HttpResponse(0, '', 'deadline_exceeded'));
            }
            $parts = parse_url($url);
            if (!in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                return Create::promiseFor(new HttpResponse(0, '', 'invalid_endpoint'));
            }
            $headers = [];
            foreach ($request->headers as $key => $value) {
                if (is_int($key)) {
                    [$key, $value] = array_pad(explode(':', (string)$value, 2), 2, '');
                    $value = trim($value);
                }
                $headers[$key] = $value;
            }
            $sink = new LimitedStream(Utils::streamFor(Utils::tryFopen($request->sink ?? 'php://temp', 'w+b')), $request->maxBytes);
            $options = [
                'headers' => $headers, 'timeout' => $remaining,
                'connect_timeout' => min($remaining, $request->connectTimeoutMs / 1000),
                'http_errors' => false, 'allow_redirects' => false, 'verify' => true,
                'cookies' => false, 'decode_content' => true, 'sink' => $sink,
            ];
            if (is_array($request->body)) {
                $options['json'] = $request->body;
            } elseif ($request->body !== null) {
                $options['body'] = $request->body;
            }
            if ($request->multipart !== null) {
                $options['multipart'] = $request->multipart;
            }
            return $this->client->requestAsync($request->method, $url, $options)->then(
                function (ResponseInterface $response) use ($request, $url, $deadline, $redirects, $sink): HttpResponse|PromiseInterface {
                    try {
                        $status = $response->getStatusCode();
                        if ($sink->exceeded) {
                            return new HttpResponse($status, '', 'response_too_large');
                        }
                        if ($request->redirectHosts !== [] && in_array($status, [301,302,303,307,308], true) && $response->hasHeader('Location')) {
                            $next = UriResolver::resolve(new Uri($url), new Uri($response->getHeaderLine('Location')));
                            // Keep credentials on the original origin, even with a broader hostname allowlist.
                            $origin = new Uri($url);
                            if ($redirects >= $request->maxRedirects || $next->getScheme() !== 'https' || $next->getUserInfo() !== '' || $next->getAuthority() !== $origin->getAuthority() || !in_array($next->getHost(), $request->redirectHosts, true)) {
                                return new HttpResponse($status, '', 'redirect_rejected');
                            }
                            $sink->close();
                            return $this->transfer($request, (string)$next, $deadline, $redirects + 1);
                        }
                        $body = $request->sink === null ? (string)$response->getBody() : '';
                        if (strlen($body) > $request->maxBytes) {
                            return new HttpResponse($status, '', 'response_too_large');
                        }
                        $retry = $response->getHeaderLine('Retry-After');
                        $retryAfter = $retry === '' ? null : (ctype_digit($retry) ? min(3600, (int)$retry) : (($at = strtotime($retry)) === false ? null : min(3600, max(0, $at - time()))));
                        return new HttpResponse($status, $body, null, $retryAfter, $response->getHeaders());
                    } finally {
                        $sink->close();
                    }
                },
                static function ($reason) use ($sink, $deadline): HttpResponse {
                    $error = $sink->exceeded ? 'response_too_large' : (hrtime(true) / 1e9 >= $deadline ? 'deadline_exceeded' : 'network_error');
                    $sink->close();
                    return new HttpResponse(0, '', $error);
                },
            );
        } catch (\Throwable) {
            $sink?->close();
            return Create::promiseFor(new HttpResponse(0, '', 'request_failed'));
        }
    }
}
