<?php

declare(strict_types=1);

namespace App\Modules\Transport\Providers;

use App\Modules\Http\{HttpException, HttpResponse};
use App\Modules\Transport\TransportException;

/**
 * Převod odpovědí dopravních zdrojů na data našeho tvaru.
 *
 * Chyba sítě a nedostupnost zdroje se rozliší od neplatného obsahu, aby se
 * klientovi vrátil jiný stav; odpověď s chybami GraphQL se považuje za
 * neplatnou.
 */
final class UpstreamResponseMapper
{
    /**
     * Dekóduje JSON odpovědi a ověří, že neobsahuje chyby.
     *
     * @param  HttpResponse $response Odpověď sdíleného HTTP klienta.
     * @return array<string, mixed>   Dekódovaná data odpovědi.
     * @throws TransportException 'invalid_upstream' (502) při neplatném JSON
     *                            nebo chybách ve zdroji, 'upstream_unavailable'
     *                            (503) při nedostupnosti zdroje.
     */
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
