<?php

declare(strict_types=1);

namespace App\Utils;

use App\Modules\Database\Database;
use App\Modules\Router\Response;

/**
 * Pomocná třída pro omezení počtu požadavků v pevném časovém okně.
 *
 * Stav ukládá do tabulky `api_rate_limit` v rámci jednoho okurku (franchise),
 * aby limit platil i napříč více PHP procesy. Subjekt (např. e-mail) se do
 * databáze ukládá pouze jako SHA-256 hash.
 */
final class RateLimiter
{
    /**
     * @param  Database $db           Připojení k databázi (jediné PDO v projektu).
     * @param  string   $franchiseCode Kód okurku, pro který limit platí.
     * @return void
     */
    public function __construct(
        private readonly Database $db,
        private readonly string $franchiseCode,
    ) {}

    /**
     * Zaznamená jeden pokus a při překročení limitu ukončí request s 429.
     *
     * @param  string $action        Název chráněné akce (např. 'login').
     * @param  string $subject       Identifikátor omezovaného subjektu (IP, e-mail); hashuje se.
     * @param  int    $limit         Maximální počet pokusů v okně.
     * @param  int    $windowSeconds Délka časového okna v sekundách.
     * @return void                  Vedlejší efekt: zvýší počítadlo a při překročení pošle hlavičku
     *                              Retry-After a ukončí request přes Response::error(…, 429).
     */
    public function hit(string $action, string $subject, int $limit, int $windowSeconds): void
    {
        $window = (int) (floor(time() / $windowSeconds) * $windowSeconds);
        $windowStart = date('Y-m-d H:i:s', $window);
        $expiresAt = date('Y-m-d H:i:s', $window + $windowSeconds + 60);
        $subjectHash = hash('sha256', strtolower(trim($subject)));
        $this->db->query(
            'INSERT INTO api_rate_limit
                (franchise_code, action, subject_hash, window_started_at, attempts, expires_at)
             VALUES (?, ?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, expires_at = VALUES(expires_at)',
            [$this->franchiseCode, $action, $subjectHash, $windowStart, $expiresAt],
        );
        $row = $this->db->fetchOne(
            'SELECT attempts FROM api_rate_limit
             WHERE franchise_code = ? AND action = ? AND subject_hash = ? AND window_started_at = ?',
            [$this->franchiseCode, $action, $subjectHash, $windowStart],
        );
        if ((int) ($row['attempts'] ?? 0) > $limit) {
            header('Retry-After: ' . max(1, $window + $windowSeconds - time()));
            Response::error('Too many requests. Please try again later.', 429);
        }
    }
}
