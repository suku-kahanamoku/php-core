<?php

declare(strict_types=1);

namespace App\Modules\Transport\Tracking;

use App\Modules\Transport\Model\ResourceIdCodec;

/** Signed, trip/tenant-bound capability; never put tickets into URLs or logs. */
final class TrackingTicketService
{
    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('Tracking secret requires at least 32 bytes.');
        }
    }
    public function issue(string $tenant, string $trip, int $now): array
    {
        ResourceIdCodec::decode($trip, $tenant, 'trip');
        $claims = ['tenant' => $tenant,'trip' => $trip,'issued' => $now,'expires' => $now + 900];
        $payload = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        return ['ticket' => $payload.'.'.hash_hmac('sha256', $payload, $this->secret),'expires_at' => gmdate(DATE_RFC3339, $claims['expires'])];
    }
    public function verify(string $ticket, int $now): array
    {
        if (strlen($ticket) > 4096) {
            throw new \RuntimeException('Invalid ticket');
        }
        $parts = explode('.', $ticket);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], $this->secret), $parts[1])) {
            throw new \RuntimeException('Invalid ticket');
        }
        $c = json_decode(base64_decode(strtr($parts[0], '-_', '+/'), true) ?: '', true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($c) || !is_string($c['tenant'] ?? null) || !is_string($c['trip'] ?? null) || !is_int($c['expires'] ?? null) || !is_int($c['issued'] ?? null) || $c['expires'] <= $now || $c['issued'] > $now + 5 || $c['expires'] - $c['issued'] !== 900) {
            throw new \RuntimeException('Expired ticket');
        }
        ResourceIdCodec::decode($c['trip'], $c['tenant'], 'trip');
        return $c;
    }
}
