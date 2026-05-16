<?php

declare(strict_types=1);

namespace App\Modules\Templater;

use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class TemplaterApi
{
    private TemplaterService $service;

    public function __construct()
    {
        $this->service = new TemplaterService();
    }

    public function registerRoutes(Router $router): void
    {
        // GET /templater?template=mail/test — nahled sablony v prohlizeci
        $router->get('/', fn(Request $req) => $this->preview($req));
    }

    private function preview(Request $request): void
    {
        $data = $request->all();

        VALIDATOR($data)->required('template')->validate();

        $template = trim((string) $data['template']);

        // Sanitace - zabrani path traversal
        $template = str_replace(['..', "\0"], '', $template);
        $template = ltrim($template, '/');

        unset($data['template']);

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo $this->service->render($template, $data);
        exit;
    }
}
