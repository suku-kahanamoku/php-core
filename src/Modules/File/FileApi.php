<?php

declare(strict_types=1);

namespace App\Modules\File;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

/**
 * FileApi – HTTP vrstva pro spravu souboru.
 *
 * Routy:
 *   GET    /files              → list()      admin
 *   GET    /files/:id          → get()       prihlaseny
 *   POST   /files/upload       → upload()    prihlaseny
 *   POST   /files/commit       → commit()    prihlaseny
 *   DELETE /files/:id          → delete()    admin
 */
class FileApi
{
    private FileService $_service;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_service = new FileService($db, $franchiseCode, $auth);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/',        [$this, 'list']);
        $router->get('/upload',  [$this, 'methodNotAllowed']); // ochrana pred GET /upload
        $router->get('/:id',     [$this, 'get']);
        $router->post('/upload', [$this, 'upload']);
        $router->post('/commit', [$this, 'commit']);
        $router->delete('/:id',  [$this, 'delete']);
    }

    // ── GET /files ────────────────────────────────────────────────────────

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

    public function get(Request $request, array $params): void
    {
        VALIDATOR(['id' => $params['id'] ?? ''])
            ->required('id')
            ->numeric('id')
            ->validate();
        $item = $this->_service->get((int) $params['id'], $request->projection());
        Response::successItem($item, $request);
    }

    // ── POST /files/upload ────────────────────────────────────────────────

    public function upload(Request $request): void
    {
        VALIDATOR($_FILES)->required('file')->validate();

        $result = $this->_service->upload($_FILES['file']);
        Response::created($result);
    }

    // ── POST /files/commit ────────────────────────────────────────────────

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

    public function methodNotAllowed(Request $request): void
    {
        Response::error('Method not allowed', 405);
    }
}
