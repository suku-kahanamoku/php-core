<?php

declare(strict_types=1);

namespace App\Modules\FannCatalog;

use App\Modules\Http\{HttpModule, HttpRequest, HttpResponse};
use App\Modules\Http\Contracts\HttpClient;
use RuntimeException;

/**
 * URL a obsahová politika specifická pro FAnn; veškerý síťový I/O patří
 * do `HttpModule`.
 *
 * Povolena je výhradně adresa `www.fann.cz` přes HTTPS na portu 443, bez
 * přihlašovacích údajů v URL; přesměrování smí zůstat jen na stejném hostu.
 * Odpověď musí být HTML a nepřekročit 16 MB.
 */
final class FannCatalogProvider
{
    /** Jediný povolený host. */
    private const ALLOWED_HOST = 'www.fann.cz';

    /**
     * @param  int            $timeoutSeconds Timeout požadavku v sekundách.
     * @param  string         $userAgent      User-Agent hlavička.
     * @param  HttpClient|null $http          Klient pro testy; v produkci sdílený klient z `HttpModule::client()`.
     * @return void
     */
    public function __construct(
        private readonly int $timeoutSeconds = 30,
        private readonly string $userAgent = 'FAnnCatalogImporter/1.0 (+https://www.charter-agency.com/)',
        private readonly ?HttpClient $http = null,
    ) {
    }

    /**
     * Stáhne jednu stránku katalogu.
     *
     * @param  string $url Adresa stránky na povoleném hostu.
     * @return string       Tělo stránky jako HTML.
     * @throws RuntimeException 'Unsupported FAnn URL.', 'FAnn request failed (<stav>).'
     *                         nebo 'Unexpected FAnn content type.'
     */
    public function get(string $url): string
    {
        return $this->content(($this->http ?? HttpModule::client())->send($this->request($url)));
    }

    /**
     * Stáhne více stránek souběžně přes `HttpModule::client()->sendAll()`;
     * duplicitní adresy se stahují jednou.
     *
     * @param  list<string> $urls         Adresy stránek.
     * @param  int          $concurrency  Počet souběžných přenosů (1–6).
     * @return array<string, string>    Těla stránek podle adresy.
     * @throws RuntimeException          Stejné chyby jako u `get()`.
     */
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

    /**
     * Sestaví požadavek po ověření povolené adresy.
     *
     * @param  string $url Adresa stránky.
     * @return HttpRequest  Požadavek pro sdílený HTTP klient.
     * @throws RuntimeException 'Unsupported FAnn URL.', pokud adresa nesplňuje HTTPS,
     *                          povolený host, port 443 a nemá přihlašovací údaje.
     */
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

    /**
     * Ověří odpověď a vrátí její tělo.
     *
     * @param  HttpResponse $response Odpověď z HTTP klienta.
     * @return string                 Tělo stránky.
     * @throws RuntimeException       Při chybě sítě, jiném stavu než 200, prázdném těle
     *                               nebo jiném Content-Type než `text/html`.
     */
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
