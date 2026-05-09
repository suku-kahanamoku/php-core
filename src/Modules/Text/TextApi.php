<?php

declare(strict_types=1);

namespace App\Modules\Text;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class TextApi
{
    private TextService $service;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new TextService($db, $franchiseCode, $auth);
    }

    /**
     * GET /texts — Vrati strankovany seznam textu. Verejne dostupne.
     *
     * @param Request $request  query: language, is_active, search, sort, page, limit, filter, projection
     * @return void
     */
    public function list(Request $request): void
    {
        $isActive = $request->get('is_active');
        Response::success($this->service->list(
            (string) $request->get('language', 'cs'),
            $isActive !== null ? (bool)(int) $isActive : null,
            $request->get('search'),
            (string) $request->get('sort', ''),
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            (string) $request->get('filter', ''),
            $request->projection(),
        ));
    }

    /**
     * GET /texts/:id — Vrati text dle ID. Verejne dostupne.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id'], $request->projection()));
    }

    /**
     * GET /texts/by-key/:key — Vrati text dle syscode klic a jazyka. Verejne dostupne.
     *
     * @param Request $request  query: language (default cs)
     * @param array{key: string} $params
     * @return void
     */
    public function getByKey(Request $request, array $params): void
    {
        Response::success($this->service->getByKey(
            $params['key'],
            (string) $request->get('language', 'cs'),
        ));
    }

    /**
     * POST /texts — Vytvori novy text. Vyzaduje roli admin.
     *
     * @param Request $request  body: syscode (required), title (required), language, content, is_active
     * @return void
     */
    public function create(Request $request): void
    {
        $text = $this->service->create(
            trim((string) $request->get('syscode', '')),
            trim((string) $request->get('title', '')),
            trim((string) $request->get('language', 'cs')),
            [
                'content'   => $request->get('content'),
                'is_active' => $request->get('is_active', 1),
            ],
            $request->projection(),
        );
        Response::created($text, 'Text created');
    }

    /**
     * PATCH /texts/:id — Castecna aktualizace textu. Vyzaduje roli admin.
     *
     * @param Request $request  body: libovolna podmnozina sloupcu
     * @param array{id: string} $params
     * @return void
     */
    public function update(Request $request, array $params): void
    {
        $text = $this->service->update((int) $params['id'], [
            'syscode'   => $request->get('syscode'),
            'title'     => $request->get('title'),
            'content'   => $request->get('content'),
            'language'  => $request->get('language'),
            'is_active' => $request->get('is_active'),
        ], $request->projection());
        Response::success($text, 'Text updated');
    }

    /**
     * PUT /texts/:id — Uplna nahrada textu. Vyzaduje roli admin.
     *
     * @param Request $request  body: syscode, title (required), language, content, is_active
     * @param array{id: string} $params
     * @return void
     */
    public function replace(Request $request, array $params): void
    {
        $text = $this->service->replace(
            (int) $params['id'],
            trim((string) $request->get('syscode', '')),
            trim((string) $request->get('title', '')),
            [
                'content'   => $request->get('content'),
                'language'  => $request->get('language', 'cs'),
                'is_active' => $request->get('is_active', 1),
            ],
            $request->projection(),
        );
        Response::success($text, 'Text replaced');
    }

    /**
     * DELETE /texts/:id — Smaze text. Vyzaduje roli admin.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Text deleted');
    }

    /**
     * Zaregistruje vsechny routy tohoto modulu do routeru.
     */
    public function registerRoutes(Router $router): void
    {
        $router->get('/', [$this, 'list']);
        $router->post('/', [$this, 'create']);
        $router->get('/by-key/:key', [$this, 'getByKey']);
        $router->get('/:id', [$this, 'get']);
        $router->put('/:id', [$this, 'replace']);
        $router->patch('/:id', [$this, 'update']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
