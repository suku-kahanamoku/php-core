<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest,HttpResponse};
use App\Modules\Etymolog\SyncException;

/**
 * Omezuje celkové čekání na upstream v jednom kroku HTTP workera;
 * samotný přenos zůstává ve sdíleném klientovi.
 *
 * Dekorátor jen zkracuje timeouty na zbývající rozpočet a vyhodí výjimku, jakmile
 * je vyčerpán, takže krok nemůže viset déle než povolený rozpočet. Paralelní
 * požadavky jsou záměrně nepovolené.
 */
final class SyncBudgetProvider implements HttpClient
{
    /** Okamžik vypršení rozpočtu v milisekundách od `hrtime()`. */
    private readonly float $deadline;

    /**
     * @param  HttpClient $client   Sdílený HTTP klient, jemuž se předají zkrácené timeouty.
     * @param  int        $budgetMs Rozpočet čekání na upstream v celém kroku.
     * @return void
     */
    public function __construct(private readonly HttpClient $client, int $budgetMs = 20000)
    {
        $this->deadline = hrtime(true) / 1000000 + $budgetMs;
    }
    /**
     * Odešle požadavek se zkrácenými timeouty podle zbývajícího rozpočtu.
     *
     * @param  HttpRequest $r Původní požadavek.
     * @return HttpResponse    Odpověď sdíleného klienta.
     * @throws SyncException   'worker_time_budget_exceeded', pokud rozpočet vypršel.
     */
    public function send(HttpRequest $r): HttpResponse
    {
        $left = (int)floor($this->deadline - hrtime(true) / 1000000);
        if ($left < 1) { throw new SyncException('worker_time_budget_exceeded'); }
        return $this->client->send(new HttpRequest($r->url, $r->method, $r->headers, $r->body,
            min($r->timeoutMs, $left), $r->maxBytes, min($r->connectTimeoutMs, $left),
            $r->multipart, $r->sink, $r->redirectHosts, $r->maxRedirects));
    }
    /**
     * Paralelní požadavky nejsou v kroku workera podporovány.
     *
     * @param  list<HttpRequest> $requests  Požadavky k odeslání.
     * @param  int                $budgetMs  Rozpočet v milisekundách.
     * @param  int                $concurrency Požadovaná souběžnost.
     * @return list<HttpResponse>            Nikdy nevrací.
     * @throws SyncException                 'parallel_worker_requests_unsupported'.
     */
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        throw new SyncException('parallel_worker_requests_unsupported');
    }
}
