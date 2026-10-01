<?php

declare(strict_types=1);

namespace App\Modules\Http\Contracts;

use App\Modules\Http\HttpRequest;

/** Neblokující varianta pro dlouho běžící gateway; callback obdrží HttpResponse. */
interface AsyncHttpClient extends HttpClient
{
    public function sendAsync(HttpRequest $request, callable $complete): void;
}
