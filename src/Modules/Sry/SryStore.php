<?php
declare(strict_types=1);
namespace App\Modules\Sry;
use PDO;
/** SQL is parameterized; services must include family/member scope on every resource lookup. */
class SryStore
{
    public function __construct(public readonly PDO $pdo) {}
    public function all(string $sql, array $args = []): array
    {
        $q = $this->pdo->prepare($sql);
        $q->execute($args);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function one(string $sql, array $args = []): ?array
    {
        return $this->all($sql, $args)[0] ?? null;
    }
    public function execute(string $sql, array $args = []): void
    {
        $q = $this->pdo->prepare($sql);
        $q->execute($args);
    }
    public function insert(string $table, array $data): int
    {
        $keys = array_keys($data);
        $this->execute(
            "INSERT INTO " .
                $table .
                " (" .
                implode(",", $keys) .
                ") VALUES (" .
                implode(",", array_fill(0, count($keys), "?")) .
                ")",
            array_values($data),
        );
        return (int) $this->pdo->lastInsertId();
    }
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    public function lock(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === "mysql"
            ? " FOR UPDATE"
            : "";
    }
}
