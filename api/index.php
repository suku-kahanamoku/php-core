<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\CorsMiddleware;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\ProductController;
use App\Controllers\CategoryController;
use App\Controllers\TextController;
use App\Controllers\EnumerationController;
use App\Controllers\OrderController;
use App\Controllers\InvoiceController;
use App\Controllers\AddressController;

$request = new Request();
$router  = new Router();

// ── Global middleware ────────────────────────────────────────────────────────
$router->addGlobalMiddleware(new CorsMiddleware());

// ────────────────────────────────────────────────────────────────────────────
// AUTH ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$auth = new AuthController();

$router->post('/auth/login',           [$auth, 'login']);
$router->post('/auth/logout',          [$auth, 'logout']);
$router->get('/auth/me',               [$auth, 'me']);
$router->post('/auth/register',        [$auth, 'register']);
$router->post('/auth/change-password', [$auth, 'changePassword']);

// ────────────────────────────────────────────────────────────────────────────
// USER ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$users = new UserController();

$router->get('/users',          [$users, 'index']);
$router->post('/users',         [$users, 'store']);
$router->get('/users/:id',      [$users, 'show']);
$router->put('/users/:id',      [$users, 'update']);
$router->delete('/users/:id',   [$users, 'destroy']);

// ────────────────────────────────────────────────────────────────────────────
// ADDRESS ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$addresses = new AddressController();

$router->get('/users/:userId/addresses', [$addresses, 'index']);
$router->post('/addresses',              [$addresses, 'store']);
$router->get('/addresses/:id',           [$addresses, 'show']);
$router->put('/addresses/:id',           [$addresses, 'update']);
$router->delete('/addresses/:id',        [$addresses, 'destroy']);

// ────────────────────────────────────────────────────────────────────────────
// CATEGORY ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$categories = new CategoryController();

$router->get('/categories',        [$categories, 'index']);
$router->post('/categories',       [$categories, 'store']);
$router->get('/categories/:id',    [$categories, 'show']);
$router->put('/categories/:id',    [$categories, 'update']);
$router->delete('/categories/:id', [$categories, 'destroy']);

// ────────────────────────────────────────────────────────────────────────────
// PRODUCT ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$products = new ProductController();

$router->get('/products',              [$products, 'index']);
$router->post('/products',             [$products, 'store']);
$router->get('/products/:id',          [$products, 'show']);
$router->put('/products/:id',          [$products, 'update']);
$router->delete('/products/:id',       [$products, 'destroy']);
$router->patch('/products/:id/stock',  [$products, 'adjustStock']);

// ────────────────────────────────────────────────────────────────────────────
// TEXT / CMS ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$texts = new TextController();

$router->get('/texts',                  [$texts, 'index']);
$router->post('/texts',                 [$texts, 'store']);
$router->get('/texts/by-key/:key',      [$texts, 'showByKey']);
$router->get('/texts/:id',              [$texts, 'show']);
$router->put('/texts/:id',              [$texts, 'update']);
$router->delete('/texts/:id',           [$texts, 'destroy']);

// ────────────────────────────────────────────────────────────────────────────
// ENUMERATION / CODEBOOK ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$enums = new EnumerationController();

$router->get('/enumerations',         [$enums, 'index']);
$router->get('/enumerations/types',   [$enums, 'types']);
$router->post('/enumerations',        [$enums, 'store']);
$router->get('/enumerations/:id',     [$enums, 'show']);
$router->put('/enumerations/:id',     [$enums, 'update']);
$router->delete('/enumerations/:id',  [$enums, 'destroy']);

// ────────────────────────────────────────────────────────────────────────────
// ORDER ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$orders = new OrderController();

$router->get('/orders',                    [$orders, 'index']);
$router->post('/orders',                   [$orders, 'store']);
$router->get('/orders/:id',                [$orders, 'show']);
$router->patch('/orders/:id/status',       [$orders, 'updateStatus']);
$router->delete('/orders/:id',             [$orders, 'destroy']);

// ────────────────────────────────────────────────────────────────────────────
// INVOICE ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$invoices = new InvoiceController();

$router->get('/invoices',                  [$invoices, 'index']);
$router->post('/invoices',                 [$invoices, 'store']);
$router->get('/invoices/:id',              [$invoices, 'show']);
$router->patch('/invoices/:id/status',     [$invoices, 'updateStatus']);
$router->delete('/invoices/:id',           [$invoices, 'destroy']);

// ────────────────────────────────────────────────────────────────────────────
// INDEX – API info (always JSON)
// ────────────────────────────────────────────────────────────────────────────
$router->get('/', function (Request $request) use ($router) {
    $routes  = $router->getRoutes();
    $grouped = [];
    foreach ($routes as $route) {
        if ($route['path'] === '/') continue;
        $parts    = explode('/', ltrim($route['path'], '/'));
        $resource = $parts[0] ?? 'other';
        $grouped[$resource][] = $route;
    }
    Response::success([
        'name'      => 'php-core API',
        'version'   => '1.0.0',
        'endpoints' => $grouped,
        'total'     => count($routes),
    ]);
});

// ────────────────────────────────────────────────────────────────────────────
// Dispatch
// ────────────────────────────────────────────────────────────────────────────
$router->dispatch($request);
