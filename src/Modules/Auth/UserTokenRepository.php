<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;

class UserTokenRepository
{
    private Database $db;

    /**
     * UserTokenRepository constructor.
     * 
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find user data by a valid, non-expired token for the given franchise.
     *
     * @param string $token
     * @param string $franchiseCode
     * @return array{
     *   id: int,
     *   email: string,
     *   role: string,
     *   first_name: string,
     *   last_name: string
     * }|null
     */
    public function findUserByToken(string $token, string $franchiseCode): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT u.id, u.email, r.name AS role, u.first_name, u.last_name
             FROM user_token t
             JOIN `user` u ON u.id = t.user_id AND u.deleted = 0
             JOIN `role` r ON r.id = u.role_id AND r.deleted = 0
             WHERE t.token = ? AND t.expires_at > NOW()
               AND u.franchise_code = ?
             LIMIT 1',
            [$token, $franchiseCode],
        );

        return $row ?: null;
    }

    /**
     * Persist a new token for the given user.
     *
     * @param int $userId
     * @param string $token
     * @param string $expiresAt
     * @return int|null
     */
    public function create(int $userId, string $token, string $expiresAt): ?int
    {
        return $this->db->insert('user_token', [
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete a token (logout).
     *
     * @param string $token
     * @return int  Number of deleted records (0 or 1)
     */
    public function delete(string $token): int
    {
        return $this->db->delete('user_token', 'token = ?', [$token]);
    }
}
