<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\HttpRequest;
use App\Modules\Etymolog\SyncException;

/**
 * Spouštění synchronizačního workera přes HTTP na Cloudflare Workeru.
 *
 * Adresa workera se musí shodovat s přesně daným vzorem `workers.dev`, ID žádosti
 * se ověřuje formátem a klíč musí mít dostatečnou délku — jinak je
 * konfigurace považována za neplatnou a spuštění se vůbec nezkouší.
 */
final class CloudflareDispatchProvider
{
    /**
     * @param  HttpClient $http   Sdílený HTTP klient z `HttpModule::client()`.
     * @param  string     $url    Adresa workera ve tvaru `https://<subdom>.<dom>.workers.dev/dispatch`.
     * @param  string     $secret Klíč workera předávaný v hlavičce požadavku.
     * @return void
     */
    public function __construct(private readonly HttpClient $http, private readonly string $url, private readonly string $secret) {}

    /**
     * Požádá workera, aby dávku spustil.
     *
     * @param  string $requestId `request_id` dávky ve tvaru 32 hex znaků.
     * @return void             Vedlejší efekt: asynchronní spuštění workera.
     * @throws SyncException     'worker_configuration_invalid' při neplatné konfiguraci,
     *                           'worker_launch_failed' při jiném než 202.
     */
    public function launch(string $requestId): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $requestId) || strlen($this->secret) < 32 ||
            !preg_match('~^https://[a-z0-9-]+\.[a-z0-9-]+\.workers\.dev/dispatch$~D', $this->url)) {
            throw new SyncException('worker_configuration_invalid');
        }
        $r = $this->http->send(new HttpRequest($this->url, 'POST', ['X-Etymolog-Key' => $this->secret], ['request_id' => $requestId], timeoutMs: 10000, connectTimeoutMs: 3000, maxBytes: 10000));
        if ($r->status !== 202) { throw new SyncException('worker_launch_failed'); }
    }
}
