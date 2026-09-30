<?php

declare(strict_types=1);

namespace App\Modules\Templater;

use App\Modules\Auth\Auth;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

/**
 * HTTP vrstva pro náhled šablon (admin).
 *
 * Routy:
 *   GET /templater?template=test  HTML náhled šablony se všemi ostatními
 *                                   parametry jako daty šablony
 *
 * Název šablony se omezuje na `[A-Za-z0-9_-]`, takže nelze vykreslit soubor
 * mimo adresář šablon daného okurku.
 */
class TemplaterApi
{
    /** Vykreslování šablon daného okurku. */
    private TemplaterService $_service;

    /** Ověření identity a rolí. */
    private Auth $_auth;

    /**
     * @param  string $franchiseCode Kód okurku (určuje složku šablon).
     * @param  Auth   $auth          Ověření identity a rolí.
     * @return void
     */
    public function __construct(string $franchiseCode, Auth $auth)
    {
        $this->_service = new TemplaterService($franchiseCode);
        $this->_auth = $auth;
    }

    /**
     * Zaregistruje routu náhledu šablony.
     *
     * @param  Router $router Router s base path modulu.
     * @return void           Vedlejší efekt: přidá routu do routeru.
     */
    public function registerRoutes(Router $router): void
    {
        // GET /templater?template=test — nahled sablony v prohlizeci
        $router->get('/', fn(Request $req) => $this->preview($req));
    }

    /**
     * Vykreslí šablonu do HTML odpovědi (vyžaduje roli `admin`).
     *
     * @param  Request $request Aktuální požadavek; parametr `template` a data šablony.
     * @return void             Vedlejší efekt: `Response::html()`; jinak 401, 403, 400 nebo 422.
     */
    private function preview(Request $request): void
    {
        $this->_auth->requireRole('admin');
        $data = $request->all();

        VALIDATOR($data)->required('template')->validate();

        $template = trim((string) $data['template']);

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $template)) {
            Response::error('Invalid template.', 422);
        }

        unset($data['template']);

        Response::html($this->_service->render($template, $data));
    }
}
