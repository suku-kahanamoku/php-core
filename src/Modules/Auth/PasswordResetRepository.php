<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;

/**
 * Repozitar pozadavku na reset hesla.
 *
 * Ulozuji se pouze hashe tokenu, nikoli tokeny v plain textu. Kazdy novy
 * pozadavek nejprve zneplatni predchozi nevyužite tokeny, takže uzivatel
 * nemuze pouzit starsi e-mail.
 */
final class PasswordResetRepository
{
    /**
     * @param  Database $db            Databazove pripojeni.
     * @param  string   $franchiseCode Kod okurku, kteremu tokeny patri.
     * @return void
     */
    public function __construct(private readonly Database $db, private readonly string $franchiseCode) {}

    /**
     * Vytvoří nový požadavek na reset hesla a zneplatní staré tokeny.
     *
     * @param  int    $userId    ID uživatele, kterému se reset obnovuje.
     * @param  string $tokenHash SHA-256 hash tokenu, ne token v plain textu.
     * @param  string $expiresAt Čas vypršení ve formátu `Y-m-d H:i:s`.
     * @return void              Vedlejší efekt: zneplatnění starých a vložení nového tokenu.
     */
    public function create(int $userId, string $tokenHash, string $expiresAt): void
    {
        $this->db->query(
            'UPDATE password_reset_token SET used_at = NOW() WHERE franchise_code = ? AND user_id = ? AND used_at IS NULL',
            [$this->franchiseCode, $userId],
        );
        $this->db->insert('password_reset_token', [
            'franchise_code' => $this->franchiseCode, 'user_id' => $userId,
            'token_hash' => $tokenHash, 'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Spotrebuje token, nastavi nove heslo a odhlasi vsechny relace uzivatele.
     *
     * Cely prubeh bezi v jedne transakci s rademkovym zámkem, takže token
     * nelze pouzit dvakrat soucasne.
     *
     * @param  string $tokenHash    SHA-256 hash tokenu z e-mailu.
     * @param  string $passwordHash Novy hash hesla.
     * @return bool                true pri uspesnem resetu, false pri neplatnem/nevyprselem tokenu
     *                             nebo kdyz uzivatel uz neexistuje.
     * @throws \Throwable          Chyba databaze se po rollbacku znovu vyhodi.
     */
    public function consumeAndChangePassword(string $tokenHash, string $passwordHash): bool
    {
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();
        try {
            $row = $this->db->fetchOne(
                'SELECT id, user_id FROM password_reset_token WHERE franchise_code = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE',
                [$this->franchiseCode, $tokenHash],
            );
            if (!$row) {
                $pdo->rollBack();
                return false;
            }
            $updated = $this->db->update(
                'user',
                ['password' => $passwordHash],
                'id = ? AND franchise_code = ? AND deleted = 0',
                [(int) $row['user_id'], $this->franchiseCode],
            );
            if ($updated !== 1) {
                $pdo->rollBack();
                return false;
            }
            $this->db->update('password_reset_token', ['used_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
            $this->db->delete('user_token', 'user_id = ?', [(int) $row['user_id']]);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
