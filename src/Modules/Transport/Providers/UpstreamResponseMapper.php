<?php

declare(strict_types=1);

namespace App\Modules\Transport\Providers;

use App\Modules\Http\{HttpException, HttpResponse};
use App\Modules\Transport\TransportException;

final class UpstreamResponseMapper
{
    public static function json(HttpResponse $response): array
    {
        try {
            $data = $response->json();
        } catch (HttpException $e) {
            if ($e->reason === 'invalid_json') {
                throw new TransportException('invalid_upstream', 'Invalid transport source response.', 502);
            }
            throw new TransportException('upstream_unavailable', 'Transport source is unavailable.', 503);
        }
        if (!empty($data['errors'])) {
            throw new TransportException('invalid_upstream', 'Invalid transport source response.', 502);
        }
        return $data;
    }
}
