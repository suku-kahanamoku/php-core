<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Middleware\CorsMiddleware;
use App\Modules\Address\AddressApi;
use App\Modules\Auth\AuthApi;
use App\Modules\Category\CategoryApi;
use App\Modules\Enumeration\EnumerationApi;
use App\Modules\Invoice\InvoiceApi;
use App\Modules\Order\OrderApi;
use App\Modules\Product\ProductApi;
use App\Modules\Role\RoleApi;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;
use App\Modules\Text\TextApi;
use App\Modules\User\UserApi;

$request = new Request();
$router  = new Router();

// ── Global middleware ────────────────────────────────────────────────────────
$router->addGlobalMiddleware(new CorsMiddleware());

// ────────────────────────────────────────────────────────────────────────────
// AUTH ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$auth = new AuthApi();

$router->post('/auth/login', [$auth, 'login']);
$router->post('/auth/logout', [$auth, 'logout']);
$router->get('/auth/me', [$auth, 'me']);
$router->post('/auth/register', [$auth, 'register']);
$router->post('/auth/change-password', [$auth, 'changePassword']);

// ────────────────────────────────────────────────────────────────────────────
// ROLE ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$roles = new RoleApi();

$router->get('/roles', [$roles, 'list']);
$router->post('/roles', [$roles, 'create']);
$router->get('/roles/:id', [$roles, 'get']);
$router->put('/roles/:id', [$roles, 'replace']);
$router->patch('/roles/:id', [$roles, 'update']);
$router->delete('/roles/:id', [$roles, 'delete']);

// ────────────────────────────────────────────────────────────────────────────
// USER ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$users = new UserApi();

$router->get('/users', [$users, 'list']);
$router->post('/users', [$users, 'create']);
$router->get('/users/:id', [$users, 'get']);
$router->put('/users/:id', [$users, 'replace']);
$router->patch('/users/:id', [$users, 'update']);
$router->delete('/users/:id', [$users, 'delete']);

// ────────────────────────────────────────────────────────────────────────────
// ADDRESS ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$addresses = new AddressApi();

$router->get('/users/:userId/addresses', [$addresses, 'list']);
$router->post('/addresses', [$addresses, 'create']);
$router->get('/addresses/:id', [$addresses, 'get']);
$router->put('/addresses/:id', [$addresses, 'replace']);
$router->patch('/addresses/:id', [$addresses, 'update']);
$router->delete('/addresses/:id', [$addresses, 'delete']);

// ────────────────────────────────────────────────────────────────────────────
// CATEGORY ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$categories = new CategoryApi();

$router->get('/categories', [$categories, 'list']);
$router->post('/categories', [$categories, 'create']);
$router->get('/categories/:id', [$categories, 'get']);
$router->put('/categories/:id', [$categories, 'replace']);
$router->patch('/categories/:id', [$categories, 'update']);
$router->delete('/categories/:id', [$categories, 'delete']);

// ────────────────────────────────────────────────────────────────────────────
// PRODUCT ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$products = new ProductApi();

$router->get('/products', [$products, 'list']);
$router->post('/products', [$products, 'create']);
$router->get('/products/:id', [$products, 'get']);
$router->put('/products/:id', [$products, 'replace']);
$router->patch('/products/:id', [$products, 'update']);
$router->delete('/products/:id', [$products, 'delete']);
$router->patch('/products/:id/stock', [$products, 'adjustStock']);

// ────────────────────────────────────────────────────────────────────────────
// TEXT / CMS ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$texts = new TextApi();

$router->get('/texts', [$texts, 'list']);
$router->post('/texts', [$texts, 'create']);
$router->get('/texts/by-key/:key', [$texts, 'getByKey']);
$router->get('/texts/:id', [$texts, 'get']);
$router->put('/texts/:id', [$texts, 'replace']);
$router->patch('/texts/:id', [$texts, 'update']);
$router->delete('/texts/:id', [$texts, 'delete']);

// ────────────────────────────────────────────────────────────────────────────
// ENUMERATION / CODEBOOK ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$enums = new EnumerationApi();

$router->get('/enumerations', [$enums, 'list']);
$router->get('/enumerations/types', [$enums, 'types']);
$router->post('/enumerations', [$enums, 'create']);
$router->get('/enumerations/:id', [$enums, 'get']);
$router->put('/enumerations/:id', [$enums, 'replace']);
$router->patch('/enumerations/:id', [$enums, 'update']);
$router->delete('/enumerations/:id', [$enums, 'delete']);

// ────────────────────────────────────────────────────────────────────────────
// ORDER ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$orders = new OrderApi();

$router->get('/orders', [$orders, 'list']);
$router->post('/orders', [$orders, 'create']);
$router->get('/orders/:id', [$orders, 'get']);
$router->patch('/orders/:id/status', [$orders, 'updateStatus']);
$router->delete('/orders/:id', [$orders, 'delete']);

// ────────────────────────────────────────────────────────────────────────────
// INVOICE ENDPOINTS
// ────────────────────────────────────────────────────────────────────────────
$invoices = new InvoiceApi();

$router->get('/invoices', [$invoices, 'list']);
$router->post('/invoices', [$invoices, 'create']);
$router->get('/invoices/:id', [$invoices, 'get']);
$router->patch('/invoices/:id/status', [$invoices, 'updateStatus']);
$router->delete('/invoices/:id', [$invoices, 'delete']);

// ────────────────────────────────────────────────────────────────────────────
// INDEX – API info (always JSON)
// ────────────────────────────────────────────────────────────────────────────
$router->get('/', function (Request $request) use ($router) {
    $routes  = $router->getRoutes();
    $grouped = [];
    foreach ($routes as $route) {
        if ($route['path'] === '/') {
            continue;
        }
        $parts                = explode('/', ltrim($route['path'], '/'));
        $resource             = $parts[0] ?? 'other';
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
