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

/**
 * Jediný HTTP transport modulu: omezená souběžnost, deadliny, TLS a bezpečné chybové výsledky.
 *
 * Tato implementace `HttpClient` je jediné místo, kde projekt sestavuje Guzzle
 * klienta. Ostatní moduly získají instanci přes `HttpModule::client()` ve
 * composition rootu a dostávají ji injekcí — nesmějí si klienta vytvářet samy.
 * Přihlašovací údaje zůstávají v `HttpRequest` pro jediný požadavek a nikdy
 * nejsou uloženy na sdíleném klientovi.
 */
final class HttpService implements HttpClient
{
    private readonly ClientInterface $client;

    /**
     * Vytvoří transport s explicitně asynchronním handlerem.
     *
     * @param  ClientInterface|null $client Testovací klient; v produkci se použije interní Guzzle instance.
     * @return void
     */
    public function __construct(?ClientInterface $client = null)
    {
        // Explicit async-capable handler: a synchronous stream fallback would break batch deadlines.
        $this->client = $client ?? new Client(['handler' => HandlerStack::create(new CurlMultiHandler(['select_timeout' => 0.02]))]);
    }

    /**
     * Odešle jeden požadavek s jeho vlastním timeoutem jako limit.
     *
     * @param  HttpRequest   $request Popis požadavku včetně per-request limitů a přihlašovacích údajů.
     * @return HttpResponse            Odpověď; chyby sítě a limitů jsou vráceny jako `error` s HTTP kódem 0.
     */
    public function send(HttpRequest $request): HttpResponse
    {
        return $this->sendAll([$request], $request->timeoutMs, 1)[0];
    }

    /**
     * Odešle dávku požadavků paralelně se společným časovým rozpočtem.
     *
     * Chybějící odpověď se nikdy nevynechá — klíče i pořadí výsledků odpovídají vstupu.
     * Chyby jednotlivých požadavků nemizí do výjimky, ale vracejí se jako `HttpResponse`.
     *
     * @param  array<array-key,HttpRequest> $requests    Požadavky k odeslání.
     * @param  int                          $budgetMs    Společný časový rozpočet dávky v milisekundách.
     * @param  int                          $concurrency Maximální počet souběžných spojení (1–32).
     * @return array<array-key,HttpResponse>            Výsledky se stejnými klíči jako vstup.
     * @throws \InvalidArgumentException Pokud jsou limity neplatné nebo položka není `HttpRequest`.
     */
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
        $jobs =
            /**
             * Zdroj úloh pro pool; každý klíč je jeden požadavek.
             *
             * @return \Generator<string, callable> Dvojice klíč a přenos pro každý požadavek.
             */
            function () use ($requests, $deadline): \Generator {
            foreach ($requests as $key => $request) {
                yield $key => fn () => $this->transfer($request, $request->url, min($deadline, hrtime(true) / 1e9 + $request->timeoutMs / 1000));
            }
        };
        $pool = new Pool($this->client, $jobs(), [
            'concurrency' => $concurrency,
            'fulfilled' =>
                /**
                 * Úspěšný přenos se uloží pod původním klíčem.
                 *
                 * @param  HttpResponse $response Výsledek přenosu.
                 * @param  string|int   $key       Klíč požadavku.
                 * @return void                   Vedlejší efekt: zápis do výsledků.
                 */
                static function (HttpResponse $response, $key) use (&$results): void {
                $results[$key] = $response;
            },
            'rejected' =>
                /**
                 * Odmítnutý přenos se nahradí syntetickou odpovědí, aby výsledek
                 * zachoval stejné pořadí a klíče jako vstup.
                 *
                 * @param  mixed       $reason Důvod odmítnutí.
                 * @param  string|int  $key    Klíč požadavku.
                 * @return void                Vedlejší efekt: zápis `network_error` do výsledků.
                 */
                static function ($reason, $key) use (&$results): void {
                $results[$key] = new HttpResponse(0, '', 'network_error');
            },
        ]);
        $pool->promise()->wait();
        // Stable keys/order even when requests complete in a different order.
        return array_replace(array_fill_keys(array_keys($requests), null), $results);
    }

    /**
     * Provede jeden přenos včetně ručního zpracování povolených přesměrování.
     *
     * @param  HttpRequest      $request   Popis požadavku (limity, hlavičky, případné redirect hosty).
     * @param  string           $url       Aktuální cílová URL (může se změnit při přesměrování).
     * @param  float            $deadline  Absolutní časový okamžik (hrtime) do kdy lze čekat.
     * @param  int              $redirects Kolik přesměrování už proběhlo.
     * @return PromiseInterface            Promise s `HttpResponse`; chyby jsou převedeny na `error` kód 0.
     */
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
                /**
                 * Zpracuje úspěšnou odpověď: limity, povolená přesměrování, tělo a `Retry-After`.
                 *
                 * @param  ResponseInterface $response Odpověď Guzzle.
                 * @return HttpResponse|PromiseInterface  Výsledek, případně slíbení
                 *                                 pro následné povolené přesměrování.
                 */
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
                /**
                 * Převede selhání přenosu na `HttpResponse` s kódem chyby podle
                 * příčiny (limit, deadline, síť).
                 *
                 * @param  mixed         $reason Důvod selhání.
                 * @return HttpResponse           Odpověď se stavem 0 a kódem chyby.
                 */
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
