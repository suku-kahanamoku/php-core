<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;
use App\Modules\Router\{Request, Response, Router};

final class EtymologPublicApi
{
    public function __construct(private readonly EtymologPublicService $service) {}
    public function registerRoutes(Router $router): void
    {
        $router->get('/public/names', fn (Request $r) => $this->respond(fn () => $this->service->search($r->get('q', ''), $r->get('kind', ''), $r->get('page', 1))));
        $router->get('/public/names/:id', fn (Request $r, array $p) => $this->respond(fn () => $this->service->detail($p['id'])));
    }
    private function respond(callable $action): never
    {
        header('Cache-Control: no-store');
        try { Response::success($action()); }
        catch (EtymologException $e) { Response::error($e->getMessage(), $e->status); }
    }
}
