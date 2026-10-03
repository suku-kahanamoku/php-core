<?php

declare(strict_types=1);

namespace App\Modules\Transport\Gateway;

use App\Modules\Router\{Request, Response, Router};

/** Explicit route allowlist. Existing bootstrap authenticates the caller and resolves the tenant. */
final class JavaTransportApi
{
    public function __construct(private readonly JavaTransportService $service) {}

    public function registerRoutes(Router $router): void
    {
        foreach (['/v1/journeys/search', '/v1/cities/search', '/v1/places/search', '/v1/trips/:id/tracking'] as $path) {
            $router->post($path, fn(Request $r, array $p = []) => $this->respond($r, $path, $p));
        }
        foreach (['/v1/coverage', '/v1/attributions', '/v1/journeys/:id', '/v1/journeys/:id/geometry', '/v1/stops/:id', '/v1/stops/:id/departures', '/v1/trips/:id', '/v1/trips/:id/realtime', '/v1/trips/:id/observation'] as $path) {
            $router->get($path, fn(Request $r, array $p = []) => $this->respond($r, $path, $p));
        }
    }

    private function respond(Request $request, string $path, array $parameters): never
    {
        header('Cache-Control: no-store');
        try {
            $result = $this->service->forward($request->method, str_replace(':id', (string)($parameters['id'] ?? ''), $path), $request->body, $request->query);
            if ($result['retry_after'] !== null) {
                header('Retry-After: ' . max(1, $result['retry_after']));
            }
            Response::json($result['payload'], $result['status']);
        } catch (JavaTransportException $error) {
            Response::error($error->getMessage(), $error->status, ['code' => $error->reason]);
        }
    }
}
