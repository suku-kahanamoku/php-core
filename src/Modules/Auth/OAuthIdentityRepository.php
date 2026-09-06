<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;

final class OAuthIdentityRepository
{
    public function __construct(
        private readonly Database $db,
        private readonly string $franchiseCode,
    ) {}

    public function findUserId(string $provider, string $subject): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT user_id FROM oauth_identity WHERE franchise_code = ? AND provider = ? AND provider_subject = ?',
            [$this->franchiseCode, $provider, $subject],
        );
        return $row ? (int) $row['user_id'] : null;
    }

    public function create(int $userId, string $provider, string $subject, string $email): void
    {
        $this->db->insert('oauth_identity', [
            'franchise_code' => $this->franchiseCode, 'user_id' => $userId,
            'provider' => $provider, 'provider_subject' => $subject, 'email' => $email,
        ]);
    }
}
