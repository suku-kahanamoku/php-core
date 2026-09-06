<?php

declare(strict_types=1);

namespace App\Utils;

use App\Modules\Database\Database;
use App\Modules\Router\Response;

final class RateLimiter
{
    public function __construct(
        private readonly Database $db,
        private readonly string $franchiseCode,
    ) {}

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
