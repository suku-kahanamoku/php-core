<?php

declare(strict_types=1);

namespace App\Modules\Router;

class Router
{
    /** @var array<string, array<string, callable>> */
    private array $_routes = [];

    /**
     * @var array<array{methods: string[], pattern: string, handler: callable, middleware: callable[]}>
     */
    private array $_compiledRoutes = [];

    private array $_globalMiddleware = [];
    private array $_routeMiddleware  = [];

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
        return $this->_addRoute('GET', $path, $handler);
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
        return $this->_addRoute('POST', $path, $handler);
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
        return $this->_addRoute('PUT', $path, $handler);
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
        return $this->_addRoute('PATCH', $path, $handler);
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
        return $this->_addRoute('DELETE', $path, $handler);
    }

    /**
     * Prida middleware ke zpracovani posledni zaregistrovane routy.
     *
     * @param  callable $middleware
     * @return self
     */
    public function middleware(callable $middleware): self
    {
        // Prida se k posledni zaregistrovane route
        $lastKey = array_key_last($this->_compiledRoutes);
        if ($lastKey !== null) {
            $this->_compiledRoutes[$lastKey]['middleware'][] = $middleware;
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
        $this->_globalMiddleware[] = $middleware;
        return $this;
    }

    private function _addRoute(string $method, string $path, callable $handler): self
    {
        // Preved :param na pojmenovane regex skupiny
        $pattern = preg_replace('/\/:([a-zA-Z_][a-zA-Z0-9_]*)/', '/(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->_compiledRoutes[] = [
            'method'     => $method,
            'path'       => $path,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => [],
        ];

        // Uloz pro prehled dostupnych rout
        $this->_routes[$method][$path] = $handler;

        return $this;
    }

    // ─── Zpracovani ──────────────────────────────────────────────────────────────────

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

        // Zpracuj CORS preflight pozadavek
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        foreach ($this->_compiledRoutes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            // Extrahuj pojmenovane parametry
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Spust globalni a pak routovaci middleware
            $allMiddleware = array_merge($this->_globalMiddleware, $route['middleware']);
            foreach ($allMiddleware as $mw) {
                $mw($request);
            }

            ($route['handler'])($request, $params);
            return;
        }

        // Zkontroluj, zda cesta existuje s jinou metodou → 405
        foreach ($this->_compiledRoutes as $route) {
            if (preg_match($route['pattern'], $uri)) {
                Response::error('Method Not Allowed', 405);
            }
        }

        Response::notFound("Endpoint '{$uri}' not found.");
    }
}
