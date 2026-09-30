<?php

declare(strict_types=1);

namespace App\Modules\Http;

/**
 * Neměnné nastavení pro jeden odchozí HTTP požadavek. Přihlašovací údaje se nikdy neukládají do sdíleného klienta.
 *
 * Objekt se předává do `HttpClient::send()` / `sendAll()`; doménové služby jej
 * sestavují s credentials konkrétního tenanta pro konkrétní volání.
 */
final class HttpRequest
{
    /**
     * @param string                                     $url              Cílová URL (pouze http/https bez user info).
     * @param string                                     $method           HTTP metoda.
     * @param array<string,string|string[]>|list<string> $headers          Hlavičky jako mapa nebo seznam řádků "Nazev: hodnota".
     * @param array|string|null                          $body             Tělo požadavku; pole se pošle jako JSON, řetězec doslova.
     * @param int                                        $timeoutMs        Celkový časový limit v milisekundách.
     * @param int                                        $maxBytes         Limit velikosti stahované odpovědi v bajtech.
     * @param int                                        $connectTimeoutMs  Limit pro navázání spojení v milisekundách.
     * @param array|null                                 $multipart        Multipart části Guzzle (name, contents, filename, headers).
     * @param string|null                                $sink             Soukromá lokální cesta pro stažení; tělo se nebufferuje v paměti PHP.
     * @param list<string>                               $redirectHosts    Explicitní allowlist HTTPS přesměrování; pouze GET/HEAD.
     * @param int                                        $maxRedirects     Maximální počet povolených přesměrování.
     * @throws \InvalidArgumentException Pokud jsou limity nekladné, jsou zadány současně body i multipart,
     *                                   nebo jsou přesměrování povolena pro jinou metodu než GET/HEAD.
     */
    public function __construct(
        public readonly string $url,
        public readonly string $method = 'GET',
        public readonly array $headers = [],
        public readonly array|string|null $body = null,
        public readonly int $timeoutMs = 4000,
        public readonly int $maxBytes = 4000000,
        public readonly int $connectTimeoutMs = 1500,
        public readonly ?array $multipart = null,
        public readonly ?string $sink = null,
        public readonly array $redirectHosts = [],
        public readonly int $maxRedirects = 3,
    ) {
        if ($timeoutMs < 1 || $connectTimeoutMs < 1 || $maxBytes < 1 || $maxRedirects < 0) {
            throw new \InvalidArgumentException('HTTP limits must be positive.');
        }
        if ($body !== null && $multipart !== null) {
            throw new \InvalidArgumentException('Choose body or multipart, not both.');
        }
        if ($redirectHosts !== [] && !in_array($method, ['GET', 'HEAD'], true)) {
            throw new \InvalidArgumentException('Only GET/HEAD requests may follow redirects.');
        }
    }
}
