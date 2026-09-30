<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest,HttpResponse};
use App\Modules\Etymolog\SyncException;

/**
 * Rytmuje požadavky na všechna Wikimedia vydání společně; samotný přenos zůstává
 * ve sdíleném `HttpClient`.
 *
 * Limity Wikipedie jsou počítané napříč celou Wikipedí, takže čekání se
 * vztahuje i na požadavky na Wikisource, Wiktionary a Wikidata. Ostatní
 * hostitelé se neredukují.
 */
final class WikimediaHttpProvider implements HttpClient
{
    /** Nejbližší povolený čas příštího požadavku (v milisekundách od `hrtime()`). */
    private float $nextRequest = 0;

    /** Zdroj času (nahrazitelný v testech). */
    private readonly \Closure $clock;

    /** Čekání v mikrosekundách (nahrazitelné v testech). */
    private readonly \Closure $sleep;

    /**
     * @param  HttpClient    $client  Sdílený HTTP klient.
     * @param  \Closure|null $clock   Zdroj času v milisekundách; prázdná hodnota znamená `hrtime()`.
     * @param  \Closure|null $sleep   Čekání v mikrosekundách; prázdná hodnota znamená `usleep()`.
     * @return void
     */
    public function __construct(private readonly HttpClient $client, ?\Closure $clock = null, ?\Closure $sleep = null)
    {
        $this->clock = $clock ?? static fn (): float => hrtime(true) / 1000000;
        $this->sleep = $sleep ?? static fn (int $microseconds) => usleep($microseconds);
    }
    /**
     * Odešle požadavek, případně počká na volný slot pro hostitele Wikimedia.
     *
     * @param  HttpRequest $request Požadavek k odeslání.
     * @return HttpResponse         Odpověď sdíleného klienta.
     */
    public function send(HttpRequest $request): HttpResponse
    {
        if (preg_match('/^(?:[a-z-]+\.)?(?:wikidata|wikipedia|wiktionary|wikisource)\.org$/D', (string)parse_url($request->url, PHP_URL_HOST))) {
            $wait = $this->nextRequest - ($this->clock)();
            if ($wait > 0) { ($this->sleep)((int)ceil($wait * 1000)); }
            $this->nextRequest = ($this->clock)() + 1000;
        }
        return $this->client->send($request);
    }
    /**
     * Paralelní požadavky na Wikimedia nejsou podporovány (chybí rytmování).
     *
     * @param  list<HttpRequest> $requests  Požadavky k odeslání.
     * @param  int                $budgetMs  Rozpočet v milisekundách.
     * @param  int                $concurrency Požadovaná souběžnost.
     * @return list<HttpResponse>            Nikdy nevrací.
     * @throws SyncException                 'parallel_wikimedia_requests_unsupported'.
     */
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        throw new SyncException('parallel_wikimedia_requests_unsupported');
    }
}
