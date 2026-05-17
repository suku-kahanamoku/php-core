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
 *   GET    /files/:id/download → download()  prihlaseny – Content-Disposition: attachment
 *   GET    /files/:id/preview  → preview()   prihlaseny – Content-Disposition: inline
 *   POST   /files/upload       → upload()    prihlaseny
 *   POST   /files/commit       → commit()    prihlaseny
 *   DELETE /files/:id          → delete()    admin
 */
class FileApi
{
    private FileService $service;
    private Auth $auth;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new FileService($db, $franchiseCode, $auth);
        $this->auth    = $auth;
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/',                 [$this, 'list']);
        $router->get('/upload',           [$this, 'methodNotAllowed']); // ochrana pred GET /upload
        $router->get('/:id/download',     [$this, 'download']);
        $router->get('/:id/preview',      [$this, 'preview']);
        $router->get('/:id',              [$this, 'get']);
        $router->post('/upload',          [$this, 'upload']);
        $router->post('/commit',          [$this, 'commit']);
        $router->delete('/:id',           [$this, 'delete']);
    }

    // ── GET /files ────────────────────────────────────────────────────────

    public function list(Request $request): void
    {
        $result = $this->service->list(
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
        $item = $this->service->get((int) $params['id'], $request->projection());
        Response::successItem($item, $request);
    }

    // ── GET /files/:id/download ───────────────────────────────────────────

    public function download(Request $request, array $params): void
    {
        VALIDATOR(['id' => $params['id'] ?? ''])
            ->required('id')
            ->numeric('id')
            ->validate();
        $resolved = $this->service->resolve((int) $params['id']);
        Response::stream(
            $resolved['path'],
            $resolved['name'],
            $resolved['mime_type'],
            'attachment'
        );
    }

    // ── GET /files/:id/preview ────────────────────────────────────────────

    public function preview(Request $request, array $params): void
    {
        VALIDATOR(['id' => $params['id'] ?? ''])
            ->required('id')
            ->numeric('id')
            ->validate();
        $resolved = $this->service->resolve((int) $params['id']);
        Response::stream(
            $resolved['path'],
            $resolved['name'],
            $resolved['mime_type'],
            'inline'
        );
    }

    // ── POST /files/upload ────────────────────────────────────────────────

    public function upload(Request $request): void
    {
        VALIDATOR($_FILES)->required('file')->validate();

        $userId = $this->auth->id();
        $result = $this->service->upload($_FILES['file'], $userId);
        Response::created($result);
    }

    // ── POST /files/commit ────────────────────────────────────────────────

    public function commit(Request $request): void
    {
        $body = $request->body;

        VALIDATOR([
            'temp_token' => $body['temp_token'] ?? '',
            'name'       => $body['name'] ?? '',
        ])
            ->required('temp_token')
            ->required('name')
            ->validate();

        $result = $this->service->commit(
            (string) $body['temp_token'],
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
        $this->service->delete((int) $params['id']);
        Response::success(['message' => 'File deleted']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function methodNotAllowed(Request $request): void
    {
        Response::error('Method not allowed', 405);
    }
}
