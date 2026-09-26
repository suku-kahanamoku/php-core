<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Middleware\InternalAuthMiddleware;
use App\Modules\Router\Request;

$request = new Request();
(new InternalAuthMiddleware())($request);
