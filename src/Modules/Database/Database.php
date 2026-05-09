<?php

declare(strict_types=1);

namespace App\Modules\Database;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $host     = $_ENV['DB_HOST']     ?? 'localhost';
        $port     = $_ENV['DB_PORT']     ?? '3306';
        $dbname   = $_ENV['DB_NAME']     ?? 'php_core';
        $username = $_ENV['DB_USER']     ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $charset  = $_ENV['DB_CHARSET']  ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new \RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Vrati singleton instanci databaze.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Vrati nativni PDO objekt.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Pripravi a vykona SQL dotaz.
     *
     * @param  string  $sql     Parametrizovany SQL dotaz
     * @param  array   $params  Hodnoty pro placeholder '?'
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Vrati vsechny radky jako list asociativnich poli.
     *
     * @param  string  $sql
     * @param  array   $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Vrati prvni radek nebo false kdyz zadny zaznam nenalezen.
     *
     * @param  string              $sql
     * @param  array               $params
     * @return array<string, mixed>|false
     */
    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Vlozi radek do tabulky a vrati ID noveho zaznamu.
     *
     * @param  string               $table  Nazev tabulky
     * @param  array<string, mixed> $data   Asociativni pole sloupec => hodnota
     * @return int                          Last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns      = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql          = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Aktualizuje radky v tabulce a vrati pocet ovlivnenych radku.
     *
     * @param  string               $table        Nazev tabulky
     * @param  array<string, mixed> $data         Pole sloupec => nova hodnota
     * @param  string               $where        WHERE podminka (napr. 'id = ?')
     * @param  array                $whereParams  Hodnoty pro WHERE placeholder
     * @return int  Pocet aktualizovanych radku
     */
    public function update(
        string $table,
        array $data,
        string $where,
        array $whereParams = [],
    ): int {
        $set  = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        $sql  = "UPDATE `{$table}` SET {$set} WHERE {$where}";
        $stmt = $this->query($sql, [...array_values($data), ...$whereParams]);
        return $stmt->rowCount();
    }

    /**
     * Smaze radky z tabulky a vrati pocet smazanych radku.
     *
     * @param  string $table        Nazev tabulky
     * @param  string $where        WHERE podminka (napr. 'id = ?')
     * @param  array  $whereParams  Hodnoty pro WHERE placeholder
     * @return int  Pocet smazanych radku
     */
    public function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql  = "DELETE FROM `{$table}` WHERE {$where}";
        $stmt = $this->query($sql, $whereParams);
        return $stmt->rowCount();
    }

    // Prevent cloning/unserialization
    private function __clone() {}
    public function __wakeup(): never
    {
        throw new \RuntimeException('Cannot unserialize singleton.');
    }
}
