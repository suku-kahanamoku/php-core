<?php

declare(strict_types=1);

namespace App\Modules\Transport;

/** Opaque URL-safe IDs. Every resolution verifies the tenant, provider and kind. */
final class ResourceIdCodec
{
    public static function encode(string $tenant, string $provider, string $kind, string $external, ?string $date = null): string
    {
        return rtrim(strtr(base64_encode(json_encode([$tenant,$provider,$kind,$external,$date], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }
    public static function decode(string $id, string $tenant, string $kind): array
    {
        if (strlen($id) > 2048 || !preg_match('/^[A-Za-z0-9_-]+$/D', $id)) {
            throw new TransportException('invalid_id', 'Invalid resource ID.');
        }
        $data = json_decode(base64_decode(strtr($id, '-_', '+/'), true) ?: '', true);
        if (!is_array($data) || count($data) !== 5 || $data[0] !== $tenant || $data[2] !== $kind || !is_string($data[1]) || !is_string($data[3]) || strlen($data[3]) > 512 || ($data[4] !== null && (!is_string($data[4]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data[4])))) {
            throw new TransportException('not_found', 'Resource not found.', 404);
        }
        return ['provider' => $data[1],'external' => $data[3],'date' => $data[4]];
    }
}
