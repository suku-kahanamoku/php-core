<?php

declare(strict_types=1);

namespace App\Modules\Database\Maintenance;

use PDO;
use RuntimeException;

/** Explicit maintenance only: remove the retired SQL transport storage, preserving Auth. */
final class TramCleanupRepository
{
    public const TABLES = [
        'transport_feed', 'transport_feed_version', 'transport_frequency',
        'transport_journey_cache', 'transport_operator', 'transport_provider',
        'transport_provider_quota', 'transport_provider_quota_usage', 'transport_route',
        'transport_service', 'transport_service_exception', 'transport_shape',
        'transport_stop', 'transport_stop_time', 'transport_sync_run',
        'transport_transfer', 'transport_trip',
    ];
    public const AUTH_TABLES = ['user', 'role', 'user_token', 'oauth_identity', 'password_reset_token'];
    public const AUTH_ACTIONS = ['login', 'register', 'password-reset', 'password-reset-complete'];

    public function __construct(private readonly PDO $db) {}

    /** Inspect every tenant table and SQL object before making any change. */
    public function plan(): array
    {
        $tables = $this->db->query('SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->checkSqlObjects();
        $drop = [];
        $delete = [];
        $preserve = [];
        foreach ($tables as $table => $type) {
            if (preg_match('/^(transport_|tram_|gtfs_|otp_)/i', $table)) {
                if ($type !== 'BASE TABLE' || !in_array($table, self::TABLES, true)) {
                    throw new RuntimeException('Unknown legacy SQL object requires review: ' . $table);
                }
                if ($this->hasTenant($table) && $this->count($table, "franchise_code <> 'tram' OR franchise_code IS NULL") > 0) {
                    throw new RuntimeException('Transport table contains another tenant: ' . $table);
                }
                $drop[$table] = $this->count($table);
                continue;
            }
            if (!$this->hasTenant($table)) {
                continue;
            }
            $rows = $this->count($table, "franchise_code = 'tram'");
            if (in_array($table, self::AUTH_TABLES, true)) {
                $preserve[$table] = $rows;
                continue;
            }
            if ($table === 'api_rate_limit') {
                $delete[$table] = $this->count($table, $this->predicate($table));
                $preserve['auth_rate_limits'] = $rows - $delete[$table];
            } elseif ($table === 'enumeration') {
                $delete[$table] = $rows;
            } elseif ($rows > 0) {
                throw new RuntimeException('Unexpected TRAM rows require relational review: ' . $table);
            }
        }
        if (isset($tables['user'], $tables['user_token'])) {
            $preserve['user_token'] = (int)$this->db->query("SELECT COUNT(*) FROM user_token WHERE user_id IN (SELECT id FROM user WHERE franchise_code='tram')")->fetchColumn();
        }
        // Refuse to break other modules, even when the referencing table is empty.
        $fks = $this->db->query('SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fks as $fk) {
            if (isset($drop[$fk['REFERENCED_TABLE_NAME']]) && !isset($drop[$fk['TABLE_NAME']])) {
                throw new RuntimeException('External foreign key references transport: ' . $fk['TABLE_NAME']);
            }
            if ($fk['REFERENCED_TABLE_NAME'] === 'enumeration' && ($delete['enumeration'] ?? 0) > 0) {
                throw new RuntimeException('Enumeration references require review: ' . $fk['TABLE_NAME']);
            }
        }
        // Drop children before parents without disabling foreign key enforcement.
        $pending = array_keys($drop);
        $ordered = [];
        while ($pending !== []) {
            $ready = array_values(array_filter($pending, static function (string $parent) use ($fks, $pending): bool {
                foreach ($fks as $fk) {
                    if ($fk['REFERENCED_TABLE_NAME'] === $parent && $fk['TABLE_NAME'] !== $parent && in_array($fk['TABLE_NAME'], $pending, true)) {
                        return false;
                    }
                }
                return true;
            }));
            if ($ready === []) {
                throw new RuntimeException('Circular transport foreign keys require review.');
            }
            $ordered = array_merge($ordered, $ready);
            $pending = array_values(array_diff($pending, $ready));
        }
        return ['database' => $this->db->query('SELECT DATABASE()')->fetchColumn(),
            'drop' => $drop, 'drop_order' => $ordered, 'delete' => $delete, 'preserve' => $preserve];
    }

    /** MySQL DDL commits implicitly: back up all removed schema/data before the first mutation. */
    public function apply(string $backupDirectory): array
    {
        $lock = 'php-core-tram-cleanup-' . substr(hash('sha256', (string)$this->db->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([$lock]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Another TRAM cleanup is running.');
        }
        try {
            $plan = $this->plan();
            if ($plan['drop'] === [] && array_sum($plan['delete']) === 0) {
                return ['before' => $plan, 'backup' => null, 'after' => $plan];
            }
            $backup = $this->backup($plan, $backupDirectory);
            $this->db->beginTransaction();
            try {
                foreach ($plan['delete'] as $table => $rows) {
                    if ($rows > 0) {
                        $this->db->exec('DELETE FROM ' . $this->identifier($table) . ' WHERE ' . $this->predicate($table));
                    }
                }
                $this->db->commit();
            } catch (\Throwable $error) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw $error;
            }
            foreach ($plan['drop_order'] as $table) {
                $this->db->exec('DROP TABLE IF EXISTS ' . $this->identifier($table));
            }
            $after = $this->plan();
            if ($after['drop'] !== [] || array_sum($after['delete']) !== 0) {
                throw new RuntimeException('TRAM cleanup verification failed; backup: ' . $backup);
            }
            return ['before' => $plan, 'backup' => $backup, 'after' => $after];
        } finally {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lock]);
        }
    }

    private function backup(array $plan, string $directory): string
    {
        if (!str_starts_with($directory, '/') || (!is_dir($directory) && !mkdir($directory, 0700, true))) {
            throw new RuntimeException('An absolute private backup directory is required.');
        }
        $directory = realpath($directory);
        $project = realpath(dirname(__DIR__, 4));
        if (!$directory || $directory === $project || str_starts_with($directory . '/', $project . '/')) {
            throw new RuntimeException('Backup must be outside the web checkout.');
        }
        if ((fileperms($directory) & 0077) !== 0) {
            throw new RuntimeException('Backup directory must be private (0700).');
        }
        $path = $directory . '/tram-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sql.gz';
        $stream = gzopen($path . '.partial', 'wb6');
        if (!$stream || !chmod($path . '.partial', 0600)) {
            throw new RuntimeException('Cannot create private TRAM backup.');
        }
        $write = static function (string $sql) use ($stream): void {
            if (gzwrite($stream, $sql) !== strlen($sql)) {
                throw new RuntimeException('TRAM backup write failed.');
            }
        };
        try {
            $write("-- Retired TRAM data only; restore into a reviewed/disposable database.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            foreach ($plan['drop_order'] as $table) {
                $ddl = $this->db->query('SHOW CREATE TABLE ' . $this->identifier($table))->fetch(PDO::FETCH_NUM)[1];
                $write($ddl . ";\n");
            }
            $targets = array_fill_keys(array_keys($plan['drop']), '1=1');
            foreach ($plan['delete'] as $table => $rows) {
                if ($rows > 0) $targets[$table] = $this->predicate($table);
            }
            // Stream the large stop-time table without keeping its 700k rows in PHP memory.
            $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            try {
                foreach ($targets as $table => $where) {
                    $query = $this->db->query('SELECT * FROM ' . $this->identifier($table) . ' WHERE ' . $where);
                    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                        $columns = implode(',', array_map($this->identifier(...), array_keys($row)));
                        $values = implode(',', array_map(fn($value) => $value === null ? 'NULL' : $this->db->quote((string)$value), array_values($row)));
                        $write('INSERT INTO ' . $this->identifier($table) . ' (' . $columns . ') VALUES (' . $values . ");\n");
                    }
                    $query->closeCursor();
                }
            } finally {
                $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n");
            if (!gzclose($stream)) throw new RuntimeException('Cannot finish TRAM backup.');
            $stream = null;
            if (!rename($path . '.partial', $path)) throw new RuntimeException('Cannot publish TRAM backup.');
            return $path;
        } finally {
            if (is_resource($stream)) gzclose($stream);
        }
    }

    private function checkSqlObjects(): void
    {
        foreach ([
            "SELECT TABLE_NAME AS name, VIEW_DEFINITION AS body FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE()",
            "SELECT TRIGGER_NAME AS name, CONCAT(EVENT_OBJECT_TABLE, ' ', ACTION_STATEMENT) AS body FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()",
            "SELECT ROUTINE_NAME AS name, ROUTINE_DEFINITION AS body FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()",
            "SELECT EVENT_NAME AS name, EVENT_DEFINITION AS body FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()",
        ] as $sql) {
            foreach ($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $object) {
                if ($object['body'] === null || preg_match('/\b(?:transport|tram|gtfs|otp)_/i', $object['name'] . ' ' . $object['body'])) {
                    throw new RuntimeException('SQL object requires review: ' . $object['name']);
                }
            }
        }
    }

    private function hasTenant(string $table): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='franchise_code'");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function count(string $table, string $where = '1=1'): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM ' . $this->identifier($table) . ' WHERE ' . $where)->fetchColumn();
    }

    private function predicate(string $table): string
    {
        if ($table === 'enumeration') return "franchise_code='tram'";
        if ($table === 'api_rate_limit') {
            return "franchise_code='tram' AND action NOT IN (" . implode(',', array_map($this->db->quote(...), self::AUTH_ACTIONS)) . ')';
        }
        throw new RuntimeException('Unsupported shared table: ' . $table);
    }

    private function identifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
