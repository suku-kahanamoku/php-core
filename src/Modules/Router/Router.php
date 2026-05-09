<?php

declare(strict_types=1);

namespace App\Modules\Router;

class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    /**
     * @var array<array{methods: string[], pattern: string, handler: callable, middleware: callable[]}>
     */
    private array $compiledRoutes = [];

    private array $globalMiddleware = [];
    private array $routeMiddleware  = [];

    // ─── Registration ──────────────────────────────────────────────────────────

    /**
     * Registruje GET routu.
     *
     * @param  string   $path     URL vzor (napr. '/:id')
     * @param  callable $handler  Obsluzna funkce
     * @return self
     */
    public function get(string $path, callable $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Registruje POST routu.
     *
     * @param  string   $path
     * @param  callable $handler
     * @return self
     */
    public function post(string $path, callable $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Registruje PUT routu.
     *
     * @param  string   $path
     * @param  callable $handler
     * @return self
     */
    public function put(string $path, callable $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Registruje PATCH routu.
     *
     * @param  string   $path
     * @param  callable $handler
     * @return self
     */
    public function patch(string $path, callable $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Registruje DELETE routu.
     *
     * @param  string   $path
     * @param  callable $handler
     * @return self
     */
    public function delete(string $path, callable $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Prida middleware ke zpracovani posledni zaregistrovane routy.
     *
     * @param  callable $middleware
     * @return self
     */
    public function middleware(callable $middleware): self
    {
        // Applied to the last registered route
        $lastKey = array_key_last($this->compiledRoutes);
        if ($lastKey !== null) {
            $this->compiledRoutes[$lastKey]['middleware'][] = $middleware;
        }
        return $this;
    }

    /**
     * Prida globalni middleware spousteny pred kazdou routou.
     *
     * @param  callable $middleware
     * @return self
     */
    public function addGlobalMiddleware(callable $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    private function addRoute(string $method, string $path, callable $handler): self
    {
        // Convert :param to named regex groups
        $pattern = preg_replace('/\/:([a-zA-Z_][a-zA-Z0-9_]*)/', '/(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->compiledRoutes[] = [
            'method'     => $method,
            'path'       => $path,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => [],
        ];

        // Store for listing
        $this->routes[$method][$path] = $handler;

        return $this;
    }

    // ─── Dispatch ──────────────────────────────────────────────────────────────

    /**
     * Zpracuje prichozi HTTP pozadavek a zavola odpovidajici handler.
     * Pokud zadna routa neodpovida, vraci 404. Pokud metoda nesouhlasi, vraci 405.
     *
     * @param  Request $request
     * @return void
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method;
        $uri    = $request->uri;

        // Handle CORS preflight
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        foreach ($this->compiledRoutes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            // Extract named params
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Run global then route middleware
            $allMiddleware = array_merge($this->globalMiddleware, $route['middleware']);
            foreach ($allMiddleware as $mw) {
                $mw($request);
            }

            ($route['handler'])($request, $params);
            return;
        }

        // Check if path exists with different method → 405
        foreach ($this->compiledRoutes as $route) {
            if (preg_match($route['pattern'], $uri)) {
                Response::error('Method Not Allowed', 405);
            }
        }

        Response::notFound("Endpoint '{$uri}' not found.");
    }
}
