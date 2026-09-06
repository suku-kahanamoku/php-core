<?php

declare(strict_types=1);

namespace App\Modules\Templater;

use App\Modules\Auth\Auth;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class TemplaterApi
{
    private TemplaterService $_service;
    private Auth $_auth;

    public function __construct(string $franchiseCode, Auth $auth)
    {
        $this->_service = new TemplaterService($franchiseCode);
        $this->_auth = $auth;
    }

    public function registerRoutes(Router $router): void
    {
        // GET /templater?template=test — nahled sablony v prohlizeci
        $router->get('/', fn(Request $req) => $this->preview($req));
    }

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
