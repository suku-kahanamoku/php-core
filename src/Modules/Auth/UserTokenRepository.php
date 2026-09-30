<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;

/**
 * Repozitar relacnich Bearer tokenu (`user_token`).
 *
 * Tokeny jsou ulozene v plain textu, coz je pri zeleznych relacich běžné; jejich
 * platnost je omezena na `expires_at` a kazdy token je navazany na uzivatele
 * i okurkove omezeni (viz `findUserByToken`).
 */
class UserTokenRepository
{
    /** Databazove pripojeni. */
    private Database $_db;

    /**
     * Konstruktor tridy UserTokenRepository.
     * 
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->_db = $db;
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
        $row = $this->_db->fetchOne(
            "SELECT u.id, u.email, r.name AS role, u.first_name, u.last_name
             FROM user_token t
             JOIN `user` u ON u.id = t.user_id AND u.deleted = 0 AND u.status = 'active'
             JOIN `role` r ON r.id = u.role_id AND r.deleted = 0
             WHERE t.token = ? AND t.expires_at > NOW() AND t.deleted = 0
               AND u.franchise_code = ?
             LIMIT 1",
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
        return $this->_db->insert('user_token', [
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
        return $this->_db->delete('user_token', 'token = ?', [$token]);
    }

    /**
     * Smaže všechny tokeny uživatele (odhlášení ze všech zařízení).
     *
     * @param  int $userId ID uživatele.
     * @return int         Počet smazaných záznamů.
     */
    public function deleteByUserId(int $userId): int
    {
        return $this->_db->delete('user_token', 'user_id = ?', [$userId]);
    }
}
