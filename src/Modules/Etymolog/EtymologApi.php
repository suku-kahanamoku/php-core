<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\Router\{Request, Response, Router};

final class EtymologApi
{
    public function __construct(private readonly EtymologService $service) {}

    public function registerRoutes(Router $router): void
    {
        $router->post('/publish-all', fn (Request $r) => $this->respond(fn () => Response::success($this->service->publishAll($r->body))));
        $router->post('/sync/start', fn (Request $r) => $this->respond(fn () => Response::success($this->service->startSync($r->body), 'Accepted', 202)));
        $router->get('/sync/status', fn (Request $r) => $this->respond(fn () => Response::success($this->service->syncStatus())));
        foreach (array_keys(ResourceRegistry::all()) as $resource) {
            $path = '/'.$resource;
            $router->get($path, fn (Request $r) => $this->respond(function () use ($r, $resource) {
                $result = $this->service->list($resource, $this->number($r->get('page', 1)), $this->number($r->get('limit', 20)), $this->text($r, 'sort'), $this->text($r, 'q'), $this->projection($r));
                Response::successList($result, $r);
            }));
            $router->get($path.'/:id', fn (Request $r, array $p) => $this->respond(fn () => Response::successItem($this->service->get($resource, $this->number($p['id']), $this->projection($r)), $r)));
            $router->post($path, fn (Request $r) => $this->respond(fn () => Response::created($this->service->save($resource, null, $r->body))));
            $router->patch($path.'/:id', fn (Request $r, array $p) => $this->respond(fn () => Response::success($this->service->save($resource, $this->number($p['id']), $r->body))));
            $router->put($path.'/:id', fn (Request $r, array $p) => $this->respond(fn () => Response::success($this->service->save($resource, $this->number($p['id']), $r->body, true))));
            $router->delete($path.'/:id', fn (Request $r, array $p) => $this->respond(function () use ($r, $p, $resource) {
                $force = filter_var($r->query['force'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($force === null) {
                    throw new EtymologException('Invalid force flag');
                }
                $this->service->remove($resource, $this->number($p['id']), $force);
                Response::success(null, 'Deleted');
            }));
        }
        $router->post('/sync-jobs/:id/reset', fn (Request $r, array $p) => $this->respond(fn () => Response::success($this->service->resetJob($this->number($p['id'])))));
        $router->get('/names/:id/external-records', fn (Request $r, array $p) => $this->respond(fn () => Response::success($this->service->externalImports('names', $this->number($p['id'])))));
        $router->get('/calendar-days/:id/imports', fn (Request $r, array $p) => $this->respond(fn () => Response::success($this->service->externalImports('calendar-days', $this->number($p['id'])))));
        $router->get('/occurrences/:id/imports', fn (Request $r, array $p) => $this->respond(fn () => Response::success($this->service->externalImports('occurrences', $this->number($p['id'])))));
        $router->get('/entries/:id/imports', fn (Request $r, array $p) => $this->respond(fn () => Response::success($this->service->storyImports($this->number($p['id'])))));
        $router->get('/names/:id/imports', fn (Request $r, array $p) => $this->respond(function () use ($p) {
            $id = $this->number($p['id']);
            Response::success($this->service->imports($id));
        }));
        $router->get('/sync-jobs/:id/runs', fn (Request $r, array $p) => $this->respond(function () use ($r, $p) {
            $id = $this->number($p['id']);
            Response::successList($this->service->runs($id, $this->number($r->get('page', 1)), $this->number($r->get('limit', 20))), $r);
        }));
    }

    private function number(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1 || (int)$value > 2147483647) {
            throw new EtymologException('Expected a positive integer');
        }
        return (int)$value;
    }

    private function text(Request $r, string $key): string
    {
        $value = $r->get($key, '');
        if (!is_string($value) || strlen($value) > 16000) {
            throw new EtymologException('Invalid query parameter: '.$key);
        }
        return $value;
    }

    private function projection(Request $r): ?array
    {
        $value = $r->get('projection');
        if (is_string($value) && str_starts_with(ltrim($value), '[')) {
            $value = json_decode($value, true);
            if (!is_array($value)) {
                throw new EtymologException('Invalid projection');
            }
        }
        if (is_array($value) && count(array_filter($value, 'is_string')) !== count($value)) {
            throw new EtymologException('Invalid projection');
        }
        if ($value !== null && !is_string($value) && !is_array($value)) {
            throw new EtymologException('Invalid projection');
        }
        return $r->projection();
    }

    private function respond(callable $action): never
    {
        try {
            $action();
            throw new \LogicException('API action did not send a response');
        } catch (EtymologException $e) {
            Response::error($e->getMessage(), $e->status);
        } catch (\RuntimeException $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof \PDOException && (string)$previous->getCode() === '23000') {
                Response::error('Record conflicts with an existing record or reference', 409);
            }
            throw $e;
        }
    }
}
