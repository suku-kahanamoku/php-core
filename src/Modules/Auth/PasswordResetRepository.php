<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;

final class PasswordResetRepository
{
    public function __construct(private readonly Database $db, private readonly string $franchiseCode) {}

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
