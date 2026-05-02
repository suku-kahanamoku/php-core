<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;

class UserToken
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /** Find user data by a valid, non-expired token for the given franchise. */
    public function findUserByToken(string $token, string $franchiseCode): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT u.id, u.email, r.name AS role, u.first_name, u.last_name
             FROM user_token t
             JOIN `user` u ON u.id = t.user_id
             JOIN `role` r ON r.id = u.role_id
             WHERE t.token = ? AND t.expires_at > NOW()
               AND u.status = "active" AND u.franchise_code = ?
             LIMIT 1',
            [$token, $franchiseCode],
        );

        return $row ?: null;
    }

    /** Persist a new token for the given user. */
    public function create(int $userId, string $token, string $expiresAt): void
    {
        $this->db->insert('user_token', [
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Delete a token (logout). */
    public function delete(string $token): void
    {
        $this->db->query('DELETE FROM user_token WHERE token = ?', [$token]);
    }
}
