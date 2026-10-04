<?php

declare(strict_types=1);

namespace App\Modules\Transport\Admin;

use App\Modules\Router\Request;
use App\Modules\Transport\Gateway\JavaTransportException;

/** Separate administrator boundary; the public transport gateway never registers this API. */
final class LocalPipelineApi
{
    public function __construct(private readonly \Closure $authorize, private readonly \Closure $service) {}

    public function execute(Request $request): array
    {
        if (!$request->internalAuthenticated) {
            throw new JavaTransportException('unauthorized', 'Application authentication required.', 401);
        }
        ($this->authorize)();
        if ($request->uri !== '/local-pipeline') {
            throw new JavaTransportException('not_found', 'Unknown administrator endpoint.', 404);
        }
        if (!in_array($request->method, ['GET', 'POST'], true)) {
            throw new JavaTransportException('invalid_method', 'Method not allowed.', 405);
        }
        if ($request->query !== [] || ($request->method === 'GET' && $request->body !== [])
            || ($request->method === 'POST' && (array_keys($request->body) !== ['action']
                || !in_array($request->body['action'], ['sync_build', 'deploy'], true)))) {
            throw new JavaTransportException('invalid_query', 'Invalid pipeline operation.', 422);
        }
        $service = ($this->service)($request->franchiseCode);
        return $request->method === 'GET' ? $service->status() : $service->submit($request->body['action']);
    }
}
