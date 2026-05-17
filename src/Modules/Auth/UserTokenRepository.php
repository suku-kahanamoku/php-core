<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;

class UserTokenRepository
{
    private Database $db;

    /**
     * Konstruktor tridy UserTokenRepository.
     * 
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Najde data uzivatele podle platneho a nevyprseleho tokenu pro danou franchizu.
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
     * Ulozi novy token pro daneho uzivatele.
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
        ]);
    }

    /**
     * Smaze token (odhlaseni).
     *
     * @param string $token
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(string $token): int
    {
        return $this->db->delete('user_token', 'token = ?', [$token]);
    }
}
