<?php

declare(strict_types=1);

namespace App\Modules\Transport\Contracts;

use App\Modules\Http\HttpRequest;
use App\Modules\Http\HttpResponse;

interface ResourceProvider extends Provider
{
    public function resourceRequest(string $operation, array $input): HttpRequest;
    public function resourceResult(string $operation, HttpResponse $result, array $input): array;
}
