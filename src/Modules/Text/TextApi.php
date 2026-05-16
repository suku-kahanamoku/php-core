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

    /**
     * TextApi constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new TextService($db, $franchiseCode, $auth);
    }

    /**
     * GET /texts — Vrati strankovany seznam textu. Verejne dostupne.
     *
     * @param Request $request  query: language, published, sort, page, limit, q, projection
     * @return void
     */
    public function list(Request $request): void
    {
        $result = $this->service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            (string) $request->get('sort', ''),
            (string) $request->get('q', ''),
            $request->projection(),
        );
        Response::successWithFactory($result, $request);
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
        $item    = $this->service->get((int) $params['id'], $request->projection());
        Response::successItemWithFactory($item, $request);
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
     * @param Request $request  body: syscode (required), title (required), language, content, published
     * @return void
     */
    public function create(Request $request): void
    {
        $syscode = trim((string) $request->get('syscode', ''));
        $title   = trim((string) $request->get('title', ''));
        VALIDATOR(['syscode' => $syscode, 'title' => $title])
            ->required(['syscode', 'title'])
            ->validate();

        $text = $this->service->create(
            $syscode,
            $title,
            trim((string) $request->get('language', 'cs')),
            [
                'content'   => $request->get('content'),
                'published' => $request->get('published', 1),
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
            'published' => $request->get('published'),
        ], $request->projection());
        Response::success($text, 'Text updated');
    }

    /**
     * PUT /texts/:id — Uplna nahrada textu. Vyzaduje roli admin.
     *
     * @param Request $request  body: syscode, title (required), language, content, published
     * @param array{id: string} $params
     * @return void
     */
    public function replace(Request $request, array $params): void
    {
        $syscode = trim((string) $request->get('syscode', ''));
        $title   = trim((string) $request->get('title', ''));
        VALIDATOR(['syscode' => $syscode, 'title' => $title])
            ->required(['syscode', 'title'])
            ->validate();

        $text = $this->service->replace(
            (int) $params['id'],
            $syscode,
            $title,
            [
                'content'   => $request->get('content'),
                'language'  => $request->get('language', 'cs'),
                'published' => $request->get('published', 1),
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
        VALIDATOR(['id' => $params['id'] ?? ''])->required('id')->validate();
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Text deleted');
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
        $router->get('/by-key/:key', [$this, 'getByKey']);
        $router->get('/:id', [$this, 'get']);
        $router->put('/:id', [$this, 'replace']);
        $router->patch('/:id', [$this, 'update']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
