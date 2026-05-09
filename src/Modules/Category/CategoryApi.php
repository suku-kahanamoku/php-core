<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class CategoryApi
{
    private CategoryService $service;

    /**
     * CategoryApi constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new CategoryService($db, $franchiseCode, $auth);
    }

    /**
     * GET /categories — Vrati strankovany seznam kategorii. Verejne dostupne.
     *
     * @param Request $request  query: page, limit, sort, filter, projection
     * @return void
     */
    public function list(Request $request): void
    {
        Response::success($this->service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            (string) $request->get('sort', ''),
            (string) $request->get('filter', ''),
            $request->projection(),
        ));
    }

    /**
     * GET /categories/:id — Vrati kategorii dle ID vcetne produktu. Verejne dostupne.
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
     * POST /categories — Vytvori novou kategorii. Vyzaduje roli admin.
     *
     * @param Request $request  body: name (required), description, parent_id, position
     * @return void
     */
    public function create(Request $request): void
    {
        $category = $this->service->create(
            trim((string) $request->get('name', '')),
            [
                'description' => $request->get('description', ''),
                'parent_id'   => $request->get('parent_id'),
                'position'    => $request->get('position', 0),
            ],
            $request->projection(),
        );
        Response::created($category, 'Category created');
    }

    /**
     * PATCH /categories/:id — Castecna aktualizace kategorie. Vyzaduje roli admin.
     *
     * @param Request $request  body: name, description, parent_id, position
     * @param array{id: string} $params
     * @return void
     */
    public function update(Request $request, array $params): void
    {
        $category = $this->service->update((int) $params['id'], [
            'name'        => $request->get('name'),
            'description' => $request->get('description'),
            'parent_id'   => $request->get('parent_id'),
            'position'    => $request->get('position'),
        ], $request->projection());
        Response::success($category, 'Category updated');
    }

    /**
     * PUT /categories/:id — Uplna nahrada kategorie. Vyzaduje roli admin.
     *
     * @param Request $request  body: name (required), description, parent_id, position
     * @param array{id: string} $params
     * @return void
     */
    public function replace(Request $request, array $params): void
    {
        $category = $this->service->replace(
            (int) $params['id'],
            trim((string) $request->get('name', '')),
            [
                'description' => $request->get('description'),
                'parent_id'   => $request->get('parent_id'),
                'position'    => $request->get('position', 0),
            ],
            $request->projection(),
        );
        Response::success($category, 'Category replaced');
    }

    /**
     * DELETE /categories/:id — Smaze kategorii. Vyzaduje roli admin.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Category deleted');
    }

    /**
     * Zaregistruje vsechny routy tohoto modulu do routeru.
     *
     * @param  Router $router
     * @return void
     */
    public function registerRoutes(Router $router): void
    {
        $router->get('/', [$this, 'list']);
        $router->post('/', [$this, 'create']);
        $router->get('/:id', [$this, 'get']);
        $router->put('/:id', [$this, 'replace']);
        $router->patch('/:id', [$this, 'update']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
