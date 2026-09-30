<?php

declare(strict_types=1);

namespace App\Modules\Address;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

/**
 * HTTP vrstva modulu Address — endpointy pro správu adres uživatelů.
 *
 * Handler pouze překládá požadavek na volání `AddressService` a převádí výsledek
 * na odpověď přes `Response`. Kontrola přihlášení a vlastnictví zůstává ve službě.
 */
class AddressApi
{
    private AddressService $_service;

    /**
     * Konstruktor AddressApi.
     *
     * @param Database $db           Připojení k databázi předané službě a repozitáři.
     * @param string   $franchiseCode Kód okurku (tenanta); určuje, které řádky jsou viditelné.
     * @param Auth     $auth         Kontext autentizace pro kontrolu rolí a vlastnictví.
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_service = new AddressService($db, $franchiseCode, $auth);
    }

    /**
     * GET /address — Vrátí stránkovaný seznam adres (pouze pro admina).
     *
     * @param Request $request Aktuální požadavek; query: sort, page, limit, q, projection.
     * @param array   $params  Parametry routy, zde jen případný `userId` pro filtrování na uživatele.
     * @return void           Odpověď se stránkovacími metadaty (`Response::successList`).
     */
    public function list(Request $request, array $params = []): void
    {
        $result = $this->_service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            (string) $request->get('sort', ''),
            (string) $request->get('q', ''),
            $request->projection(),
            isset($params['userId']) ? (int) $params['userId'] : null,
            $request->internalAuthenticated,
        );
        Response::successList($result, $request);
    }

    /**
     * GET /addresses/:id — Vrátí adresu dle ID.
     *
     * @param Request           $request Aktuální požadavek; volitelná query `projection`.
     * @param array{id: string} $params  Parametry routy s `id` adresy.
     * @return void                      Odpověď s jedním záznamem (`Response::successItem`).
     */
    public function get(Request $request, array $params): void
    {
        $item = $this->_service->get(
            (int) $params['id'],
            $request->projection(),
            $request->internalAuthenticated,
        );
        Response::successItem($item, $request);
    }

    /**
     * POST /addresses — Vytvoří novou adresu. Vyžaduje přihlášení.
     *
     * @param Request $request Tělo požadavku: type, name, street, city, zip, country, company, is_default.
     * @return void            Odpověď 201 s vytvořenou adresou.
     * @throws \Throwable       Při chybějícím street, city nebo zip je request ukončen s 422.
     */
    public function create(Request $request): void
    {
        VALIDATOR([
            'street' => $request->get('street', ''),
            'city'   => $request->get('city', ''),
            'zip'    => $request->get('zip', ''),
        ])->required(['street', 'city', 'zip'])->validate();

        $address = $this->_service->create(
            [
                'type'       => $request->get('type', 'billing'),
                'company'    => $request->get('company', ''),
                'name'       => $request->get('name'),
                'street'     => trim((string) $request->get('street', '')),
                'city'       => trim((string) $request->get('city', '')),
                'zip'        => trim((string) $request->get('zip', '')),
                'country'    => trim((string) $request->get('country', 'CZ')),
                'is_default' => $request->get('is_default', 0),
            ],
            $request->projection()
        );

        Response::created($address, 'Address created');
    }

    /**
     * PATCH /addresses/:id — Částečná aktualizace adresy. Vyžaduje přihlášení; vlastník nebo admin.
     *
     * @param Request           $request Tělo požadavku: libovolná podmnožina sloupců adresy.
     * @param array{id: string} $params  Parametry routy s `id` adresy.
     * @return void                      Odpověď s aktualizovanou adresou.
     */
    public function update(Request $request, array $params): void
    {
        $address = $this->_service->update((int) $params['id'], [
            'type'       => $request->get('type'),
            'company'    => $request->get('company'),
            'name'       => $request->get('name'),
            'street'     => $request->get('street'),
            'city'       => $request->get('city'),
            'zip'        => $request->get('zip'),
            'country'    => $request->get('country'),
            'is_default' => $request->get('is_default'),
        ], $request->projection());
        Response::success($address, 'Address updated');
    }

    /**
     * PUT /addresses/:id — Úplná náhrada adresy. Vyžaduje přihlášení; vlastník nebo admin.
     *
     * @param Request           $request Tělo požadavku: type, name, street, city, zip, country (všechna povinná).
     * @param array{id: string} $params  Parametry routy s `id` adresy.
     * @return void                      Odpověď s nahrazenou adresou.
     * @throws \Throwable                Při chybějícím street, city, zip nebo country je request ukončen s 422.
     */
    public function replace(Request $request, array $params): void
    {
        VALIDATOR([
            'street'  => $request->get('street', ''),
            'city'    => $request->get('city', ''),
            'zip'     => $request->get('zip', ''),
            'country' => $request->get('country', ''),
        ])->required(['street', 'city', 'zip', 'country'])->validate();

        $address = $this->_service->replace((int) $params['id'], [
            'type'       => $request->get('type', 'billing'),
            'company'    => $request->get('company', ''),
            'name'       => $request->get('name'),
            'street'     => trim((string) $request->get('street', '')),
            'city'       => trim((string) $request->get('city', '')),
            'zip'        => trim((string) $request->get('zip', '')),
            'country'    => trim((string) $request->get('country', '')),
            'is_default' => $request->get('is_default', 0),
        ], $request->projection());
        Response::success($address, 'Address replaced');
    }

    /**
     * DELETE /addresses/:id — Smaže adresu. Vyžaduje přihlášení; vlastník nebo admin.
     *
     * @param Request           $request Aktuální požadavek; query `force=1` volí tvrdé smazání místo soft-delete.
     * @param array{id: string} $params  Parametry routy s `id` adresy.
     * @return void                      Odpověď bez těla; chybí-li `id`, request končí s 422.
     * @throws \Throwable                Při chybějícím `id` je request ukončen s 422.
     */
    public function delete(Request $request, array $params): void
    {
        VALIDATOR(['id' => $params['id'] ?? ''])->required('id')->validate();
        $force = filter_var($request->query['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($force) {
            $this->_service->delete((int) $params['id']);
        } else {
            $this->_service->remove((int) $params['id']);
        }
        Response::success(null, 'Address deleted');
    }

    /**
     * Zaregistruje všechny routy tohoto modulu do routeru.
     *
     * Zaregistruje routy modulu Address: GET/POST `/`, GET/PUT/PATCH/DELETE `/:id`.
     *
     * @param  Router $router Router, do kterého se routy zapisují (relativní cesty vůči vstupnímu bodu API).
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
