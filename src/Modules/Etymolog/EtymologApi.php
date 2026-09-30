<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\Router\{Request, Response, Router};

/**
 * Interní (administrátorská) HTTP vrstva modulu Etymolog.
 *
 * Routy se generují pro každý zdroj z `ResourceRegistry`, takze novy zdroj
 * dostane CRUD automaticky. Seznamy používají standardní dotazový kontrakt
 * (`q`, `sort`, `projection`, `page`, `limit`, radky v klici `data`) a vsechny
 * vstupy se prevadeji a validuji, nez se dostanou do sluzby.
 *
 * Odpovědi jsou odesílány přímo z handleru přes `Response::*`, takze radek
 * vyhozujici chybu (`EtymologException`) preklada `respond()` na JSON chybu
 * se správným HTTP stavem.
 */
final class EtymologApi
{
    /**
     * @param  EtymologService $service Aplikační služby modulu Etymolog.
     * @return void
     */
    public function __construct(private readonly EtymologService $service) {}

    /**
     * Zaregistruje všechny routy modulu: CRUD generovaný nad `ResourceRegistry`,
     * hromadné publikování, spuštění a stav synchronizace, importy a běhy.
     *
     * @param  Router $router Router s base path modulu.
     * @return void           Vedlejší efekt: přidá routy do routeru.
     */
    public function registerRoutes(Router $router): void
    {
        $router->post('/publish-all', fn (Request $r) => $this->respond(fn () => Response::success($this->service->publishAll($r->body))));
        $router->post('/sync/start', fn (Request $r) => $this->respond(fn () => Response::success($this->service->startSync($r->body), 'Accepted', 202)));
        $router->post('/sync/stop', fn (Request $r) => $this->respond(fn () => Response::success($this->service->stopSync($r->body))));
        $router->get('/sync/status', fn (Request $r) => $this->respond(fn () => Response::success($this->service->syncStatus())));
        foreach (array_keys(ResourceRegistry::all()) as $resource) {
            $path = '/'.$resource;
            $router->get($path, fn (Request $r) => $this->respond(
                /**
                 * Seznam zdroje podle standardního dotazového kontraktu (`page`,
                 * `limit`, `sort`, `q`, `projection`).
                 *
                 * @return void Vedlejší efekt: `Response::successList()`.
                 * @throws EtymologException 400 při neplatném dotazu.
                 */
                function () use ($r, $resource) {
                $result = $this->service->list($resource, $this->number($r->get('page', 1)), $this->number($r->get('limit', 20)), $this->text($r, 'sort'), $this->text($r, 'q'), $this->projection($r));
                Response::successList($result, $r);
            }));
            $router->get($path.'/:id', fn (Request $r, array $p) => $this->respond(fn () => Response::successItem($this->service->get($resource, $this->number($p['id']), $this->projection($r)), $r)));
            $router->post($path, fn (Request $r) => $this->respond(fn () => Response::created($this->service->save($resource, null, $r->body))));
            $router->patch($path.'/:id', fn (Request $r, array $p) => $this->respond(fn () => Response::success($this->service->save($resource, $this->number($p['id']), $r->body))));
            $router->put($path.'/:id', fn (Request $r, array $p) => $this->respond(fn () => Response::success($this->service->save($resource, $this->number($p['id']), $r->body, true))));
            $router->delete($path.'/:id', fn (Request $r, array $p) => $this->respond(
                /**
                 * Logické smazání záznamu; `force=1` smí i zrušený záznam.
                 *
                 * @return void Vedlejší efekt: `Response::success()` se stavem 'Deleted'.
                 * @throws EtymologException 400, 403, 404 nebo 409.
                 */
                function () use ($r, $p, $resource) {
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
        $router->get('/names/:id/imports', fn (Request $r, array $p) => $this->respond(
            /**
             * Importní záznamy jednoho jména.
             *
             * @return void Vedlejší efekt: `Response::success()`.
             * @throws EtymologException 400, 404 nebo 409.
             */
            function () use ($p) {
            $id = $this->number($p['id']);
            Response::success($this->service->imports($id));
        }));
        $router->get('/sync-jobs/:id/runs', fn (Request $r, array $p) => $this->respond(
            /**
             * Běhy jedné úlohy synchronizace, stránkované jako standardní seznam.
             *
             * @return void Vedlejší efekt: `Response::successList()`.
             * @throws EtymologException 400, 404 nebo 409.
             */
            function () use ($r, $p) {
            $id = $this->number($p['id']);
            Response::successList($this->service->runs($id, $this->number($r->get('page', 1)), $this->number($r->get('limit', 20))), $r);
        }));
    }

    /**
     * Převede hodnotu na kladné celé číslo v rozsahu sloupce INT.
     *
     * @param  mixed $value Hodnota z path segmentu nebo query parametru.
     * @return int          Číselná hodnota.
     * @throws EtymologException 'Expected a positive integer' (400), pokud hodnota
     *                           neni cislo nebo neni kladna.
     */
    private function number(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1 || (int)$value > 2147483647) {
            throw new EtymologException('Expected a positive integer');
        }
        return (int)$value;
    }

    /**
     * Načte řetězec z query parametru s limitem délky.
     *
     * @param  Request $r    Aktuální požadavek.
     * @param  string  $key  Název parametru.
     * @return string         Hodnota parametru, při chybějící hodnotě prázdný řetězec.
     * @throws EtymologException 'Invalid query parameter: <key>' (400), pokud hodnota
     *                           neni retezec nebo prekracuje 16000 znaku.
     */
    private function text(Request $r, string $key): string
    {
        $value = $r->get($key, '');
        if (!is_string($value) || strlen($value) > 16000) {
            throw new EtymologException('Invalid query parameter: '.$key);
        }
        return $value;
    }

    /**
     * Normalizuje požadovanou projekci na pole názvu polí.
     *
     * @param  Request $r Aktuální požadavek.
     * @return array<int, string>|null  Seznam polí, nebo null pro plný záznam.
     * @throws EtymologException 'Invalid projection' (400), pokud hodnota neni
     *                           pole retezcu ani JSON pole retezcu.
     */
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

    /**
     * Spustí handler, který musí odeslat odpověď, a přeloží chyby na JSON.
     *
     * Handlery končí přímo přes `Response::*`, takže po jeho návratu nelze pokračovat;
     * pokud by handler neposlal žádnou odpověď, vyhodí se `LogicException`.
     *
     * @param  callable $action Handler bez parametrů.
     * @return never            Vždy ukončí požadavek (`Response::error`).
     * @throws \LogicException Pokud handler neodeslal zadnou odpoved.
     * @throws \RuntimeException Ostatní chyby, které nejde vztáhnout na
     *                                    unikátní klíč, se propadnou.
     */
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
