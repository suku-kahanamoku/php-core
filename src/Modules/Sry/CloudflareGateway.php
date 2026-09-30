<?php

declare(strict_types=1);

namespace App\Modules\Sry;

use App\Modules\Http\{HttpModule, HttpRequest};
use App\Modules\Http\Contracts\HttpClient;

/**
 * Brána ke Cloudflare Workeru: podepsané tikety pro R2 (souborové úložiště)
 * a realtime kanál.
 *
 * Tajné kódy pro R2 a realtime nikdy neopouštějí tento server — klient dostane
 * jen krátce platný, okruhem (`tenant = sry`) omezený tiket. Odchozí volání jde
 * výhradně přes `HttpModule::client()`, nikoli přímým cURLem.
 */
class CloudflareGateway
{
    /**
     * @param  string            $url   Základní HTTPS URL workera.
     * @param  string            $secret Podepisovací klíč pro tikety.
     * @param  HttpClient|null   $http  Klient pro odchozí volání; prázdná hodnota znamená `HttpModule::client()`.
     * @return void
     */
    public function __construct(private string $url, private string $secret, private readonly ?HttpClient $http = null) {}

    /**
     * Vytvoří krátce platný podepsaný tiket pro workera.
     *
     * @param  array<string, mixed> $claims Claimy vložené do tiketu (např. `key`, `op`).
     * @return string                   Tiket ve formátu `payload.signatura` (base64url).
     * @throws SryError                  'notConfigured' (503), pokud URL není HTTPS nebo je klíč příliš krátký.
     */
    public function ticket(array $claims): string
    {
        if (
            !str_starts_with($this->url, "https://") ||
            strlen($this->secret) < 32
        ) {
            throw new SryError("notConfigured", 503);
        }
        $claims["exp"] = time() + 120;
        $claims["tenant"] = "sry";
        $payload = rtrim(
            strtr(
                base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)),
                "+/",
                "-_",
            ),
            "=",
        );
        return $payload .
            "." .
            rtrim(
                strtr(
                    base64_encode(
                        hash_hmac("sha256", $payload, $this->secret, true),
                    ),
                    "+/",
                    "-_",
                ),
                "=",
            );
    }
    /**
     * Sestaví podepsanou URL koncového bodu workera.
     *
     * @param  string               $path   Cesta koncového bodu (např. '/media').
     * @param  array<string, mixed> $claims Claimy pro tiket.
     * @return string                      Absolutní URL s parametrem `ticket`.
     * @throws SryError                    'notConfigured' (503), pokud je konfigurace neúplná.
     */
    public function url(string $path, array $claims): string
    {
        return rtrim($this->url, "/") .
            $path .
            "?ticket=" .
            rawurlencode($this->ticket($claims));
    }
    /**
     * Zavolá koncový bod workera přes sdílený HTTP klient a vrátí dekódovanou odpověď.
     *
     * @param  string                $path   Cesta koncového bodu.
     * @param  array<string, mixed>  $claims Claimy pro tiket.
     * @param  array|null            $body   Tělo JSON pro POST, nebo null pro GET.
     * @return array<string, mixed>         Dekódovaná odpověď workera.
     * @throws SryError                    'cloudUnavailable' (503) při chybě sítě nebo stavu mimo 2xx,
     *                                    a při neplatné JSON odpovědi (`\JsonException`).
     */
    public function call(
        string $path,
        array $claims,
        ?array $body = null,
    ): array {
        $response = ($this->http ?? HttpModule::client())->send(new HttpRequest(
            $this->url($path, $claims), $body === null ? 'GET' : 'POST',
            ['Content-Type' => 'application/json'], $body, timeoutMs: 20000,
        ));
        $raw = $response->body;
        $status = $response->status;
        if ($response->error !== null || $status < 200 || $status >= 300) {
            throw new SryError("cloudUnavailable", 503);
        }
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
