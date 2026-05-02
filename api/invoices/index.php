<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Invoice\InvoiceApi;
use App\Modules\Router\Request;
use App\Modules\Router\Router;

$request = new Request();
$router  = new Router();
$db      = Database::getInstance();
$code    = $request->franchiseCode;
$auth    = new Auth($db);


$invoice = new InvoiceApi($db, $code, $auth);

$router->get('/', [$invoice, 'list']);
$router->post('/', [$invoice, 'create']);
$router->get('/:id', [$invoice, 'get']);
$router->patch('/:id/status', [$invoice, 'updateStatus']);
$router->delete('/:id', [$invoice, 'delete']);

$router->dispatch($request);
