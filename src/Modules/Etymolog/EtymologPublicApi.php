<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;
use App\Modules\Router\{Request, Response, Router};

/**
 * Veřejná (neautentizovaná) HTTP vrstva pro vyhledávání v etymologii.
 *
 * Všechny odpovědi jsou označeny `Cache-Control: no-store`, aby se vyhledávací
 * dotazy s libovolným filtrem neukládaly do mezipaměti.
 */
final class EtymologPublicApi
{
    /**
     * @param  EtymologPublicService $service Aplikační služby veřejného vyhledávání.
     * @return void
     */
    public function __construct(private readonly EtymologPublicService $service) {}

    /**
     * Zaregistruje veřejné routy: vyhledávání jmen a detail jednoho jména.
     *
     * @param  Router $router Router s base path modulu.
     * @return void           Vedlejší efekt: přidá routy do routeru.
     */
    public function registerRoutes(Router $router): void
    {
        $router->get('/public/today', fn (Request $r) => $this->respond(fn () => $this->service->today()));
        $router->get('/public/names', fn (Request $r) => $this->respond(fn () => $this->service->search($r->get('q', ''), $r->get('kind', ''), $r->get('page', 1))));
        $router->get('/public/names/:id', fn (Request $r, array $p) => $this->respond(fn () => $this->service->detail($p['id'])));
    }
    /**
     * Provede handler a přeloží chyby na JSON odpověď.
     *
     * @param  callable $action Handler bez parametrů, který odešle odpověď.
     * @return never            Vždy ukončí požadavek přes `Response::success()` nebo `Response::error()`.
     * @throws EtymologException Neočekávané chyby se propadnou dál.
     */
    private function respond(callable $action): never
    {
        header('Cache-Control: no-store');
        try { Response::success($action()); }
        catch (EtymologException $e) { Response::error($e->getMessage(), $e->status); }
    }
}
