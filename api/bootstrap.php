<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Middleware\InternalAuthMiddleware;
use App\Modules\Router\Request;
use App\Utils\RequestContext;

RequestContext::begin();
$request = new Request();
(new InternalAuthMiddleware())($request);
