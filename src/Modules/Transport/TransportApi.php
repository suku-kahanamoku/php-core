<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Router\{Request,Response,Router};
use App\Modules\Transport\DTO\JourneyQuery;
use App\Modules\Transport\Repositories\TransportRepository;

final class TransportApi
{
    public function __construct(private readonly JourneyService $journeys, private readonly ResourceService $resources, private readonly ProviderRegistry $registry, private readonly TransportRepository $repository)
    {
    }
    public function registerRoutes(Router $router): void
    {
        $router->post('/v1/journeys/search', fn (Request $r) => $this->respond(fn () => $this->journeys->search(JourneyQuery::fromArray($r->body))));
        $router->get('/v1/coverage', fn () => $this->respond(fn () => ['providers' => array_values(array_map(fn ($p) => $p->definition()->publicData($p->capabilities()), $this->registry->all()))]));
        $router->get('/v1/places', fn (Request $r) => $this->respond(fn () => $this->resources->places($this->text($r, 'query'), JourneyQuery::integer($r->get('limit', 10), 1, 50), $r->get('state') !== null ? $this->text($r, 'state') : null)));
        $router->get('/v1/journeys/:id', fn (Request $r, array $p) => $this->respond(fn () => $this->repository->journey($p['id'])));
        $router->get('/v1/journeys/:id/geometry', fn (Request $r, array $p) => $this->respond(function () use ($p) {
            $journey = $this->repository->journey($p['id']);
            $features = [];
            foreach ($journey['legs'] as $i => $leg) {
                if ($leg['geometry'] !== null) {
                    $features[] = ['type' => 'Feature','geometry' => $leg['geometry'],'properties' => ['leg' => $i,'mode' => $leg['mode']]];
                }
            }
            return ['type' => 'FeatureCollection','features' => $features];
        }));
        foreach (['/v1/stops/:id' => 'stop','/v1/stops/:id/departures' => 'departures','/v1/trips/:id' => 'trip','/v1/trips/:id/realtime' => 'realtime'] as $path => $operation) {
            $router->get($path, fn (Request $r, array $p) => $this->respond(fn () => $this->resources->resource($operation, $p['id'], [
                'at' => JourneyQuery::date($r->get('at', gmdate('Y-m-d\TH:i:s\Z')))->format(DATE_RFC3339),'limit' => JourneyQuery::integer($r->get('limit', 20), 1, 50)])));
        }
    }
    private function text(Request $r, string $key): string
    {
        $v = $r->get($key, '');
        if (!is_string($v)) {
            throw new TransportException('invalid_query', 'Expected string: '.$key);
        } return $v;
    }
    private function respond(callable $action): never
    {
        try {
            Response::success($action());
        } catch (TransportException $e) {
            Response::error($e->getMessage(), $e->status, ['code' => $e->reason] + $e->details);
        }
    }
}
