<?php

declare(strict_types=1);

namespace App\Modules\Http\Contracts;

use App\Modules\Http\{HttpRequest, HttpResponse};

interface HttpClient
{
    public function send(HttpRequest $request): HttpResponse;

    /** @param array<array-key,HttpRequest> $requests @return array<array-key,HttpResponse> */
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array;
}
