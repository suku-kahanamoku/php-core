<?php

declare(strict_types=1);

namespace App\Modules\File;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

/**
 * FileApi – HTTP vrstva pro správu souborů.
 *
 * Routy (oprávnění kontroluje `Auth` uvnitř služby, ne zde):
 *   GET    /files              → list()      admin
 *   GET    /files/:id          → get()       přihlášený
 *   GET    /files/content      → download()  přihlášený
 *   GET    /files/temp         → downloadTemp() přihlášený
 *   POST   /files/upload       → upload()    přihlášený
 *   POST   /files/commit       → commit()    přihlášený
 *   DELETE /files/:id          → delete()    admin
 *
 * Parametry se validují přes `VALIDATOR()` ještě před zásahem služby, takže do
 * databázové vrstvy se dostanou jen celá čísla a vyplněné řetězce. Seznamy
 * používají standardní dotazový kontrakt (`page`, `limit`, `sort`, `q`,
 * `projection`, řádky v klici `data`).
 */
class FileApi
{
    /** Aplikační služby souborů vázané na okrsek. */
    private FileService $_service;

    /**
     * @param  Database $db            Databázová vrstva pro daný okrsek.
     * @param  string   $franchiseCode Kód okurku.
     * @param  Auth     $auth          Ověření identity a rolí.
     * @return void
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_service = new FileService($db, $franchiseCode, $auth);
    }

    /**
     * Zaregistruje routy modulu souborů.
     *
     * `GET /upload` je záměrně odmítnut, aby se nahrávání nevykonalo metodou GET.
     *
     * @param  Router $router Router s base path modulu.
     * @return void           Vedlejší efekt: přidá routy do routeru.
     */
    public function registerRoutes(Router $router): void
    {
        $router->get('/',        [$this, 'list']);
        $router->get('/content', [$this, 'download']);
        $router->get('/temp',    [$this, 'downloadTemp']);
        $router->get('/upload',  [$this, 'methodNotAllowed']); // ochrana pred GET /upload
        $router->get('/:id',     [$this, 'get']);
        $router->post('/upload', [$this, 'upload']);
        $router->post('/commit', [$this, 'commit']);
        $router->delete('/:id',  [$this, 'delete']);
    }

    // ── GET /files ────────────────────────────────────────────────────────

    /**
     * Vrátí stránkovaný seznam souborů okurku.
     *
     * @param  Request $request Aktuální požadavek.
     * @return void             Vedlejší efekt: `Response::successList()`.
     */
    public function list(Request $request): void
    {
        $result = $this->_service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            (string) $request->get('sort', ''),
            (string) $request->get('q', ''),
            $request->projection(),
        );
        Response::successList($result, $request);
    }

    // ── GET /files/:id ────────────────────────────────────────────────────

    /**
     * Vrátí detail jednoho souboru včetně zvolené projekce.
     *
     * @param  Request              $request Aktuální požadavek.
     * @param  array<string, mixed> $params  Parametry routy; `id` musí být číslo.
     * @return void                        Vedlejší efekt: `Response::successItem()`.
     */
    public function get(Request $request, array $params): void
    {
        VALIDATOR(['id' => $params['id'] ?? ''])
            ->required('id')
            ->numeric('id')
            ->validate();
        $item = $this->_service->get((int) $params['id'], $request->projection());
        Response::successItem($item, $request);
    }

    /**
     * Stáhne hotový soubor podle jeho cesty.
     *
     * @param  Request $request Aktuální požadavek; dotaz `path`.
     * @return void             Vedlejší efekt: odeslání souboru (případně 404).
     */
    public function download(Request $request): void
    {
        $this->_service->download((string) $request->get('path', ''));
    }

    /**
     * Stáhne dočasný (ještě necommitnutý) soubor.
     *
     * @param  Request $request Aktuální požadavek; dotaz `path`.
     * @return void             Vedlejší efekt: odeslání dočasného souboru.
     */
    public function downloadTemp(Request $request): void
    {
        $this->_service->downloadTemp((string) $request->get('path', ''));
    }

    // ── POST /files/upload ────────────────────────────────────────────────

    /**
     * Nahraje soubor do dočasného úložiště.
     *
     * @param  Request $request Aktuální požadavek s multipart tělem (`$_FILES['file']`).
     * @return void             Vedlejší efekt: `Response::created()` s cestou dočasného souboru.
     */
    public function upload(Request $request): void
    {
        VALIDATOR($_FILES)->required('file')->validate();

        $result = $this->_service->upload($_FILES['file']);
        Response::created($result);
    }

    // ── POST /files/commit ────────────────────────────────────────────────

    /**
     * Uzavře nahrání a zapíše soubor do evidence modulu.
     *
     * @param  Request $request Aktuální požadavek; tělo: `path`, `name`, volitelně
     *                           `visibility`, `entity_type` a `entity_id`.
     * @return void             Vedlejší efekt: `Response::success()` s relací souboru.
     */
    public function commit(Request $request): void
    {
        $body = $request->body;

        VALIDATOR([
            'path' => $body['path'] ?? '',
            'name' => $body['name'] ?? '',
        ])
            ->required('path')
            ->required('name')
            ->validate();

        $result = $this->_service->commit(
            (string) $body['path'],
            (string) $body['name'],
            (string) ($body['visibility'] ?? 'private'),
            isset($body['entity_type']) ? (string) $body['entity_type'] : null,
            isset($body['entity_id'])   ? (int)    $body['entity_id']   : null,
        );
        Response::success($result);
    }

    // ── DELETE /files/:id ─────────────────────────────────────────────────

    /**
     * Odstraní soubor; `force=1` smí i soubor, na který odkazují entity.
     *
     * @param  Request              $request Aktuální požadavek; dotaz `force`.
     * @param  array<string, mixed> $params  Parametry routy; `id` musí být kladné číslo.
     * @return void                        Vedlejší efekt: `Response::success()` se stavem 'File deleted'.
     */
    public function delete(Request $request, array $params): void
    {
        VALIDATOR(['id' => $params['id'] ?? ''])
            ->required('id')
            ->numeric('id', 1)
            ->validate();
        $force = filter_var($request->query['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($force) {
            $this->_service->delete((int) $params['id']);
        } else {
            $this->_service->remove((int) $params['id']);
        }
        Response::success(null, 'File deleted');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Odmítne požadavek, který na danou routu nemá povolenou metodu.
     *
     * @param  Request $request Aktuální požadavek.
     * @return void             Vedlejší efekt: `Response::error()` se stavem 405.
     */
    public function methodNotAllowed(Request $request): void
    {
        Response::error('Method not allowed', 405);
    }
}
