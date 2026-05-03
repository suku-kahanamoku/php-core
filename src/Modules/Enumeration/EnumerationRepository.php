<?php

declare(strict_types=1);

namespace App\Modules\Enumeration;

use App\Modules\Database\Database;

/**
 * Enumeration (codebook) – DB entity layer.
 */
class EnumerationRepository
{
    private Database $db;
    private string   $code;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    public function findAll(
        ?string $type = null,
        ?bool $isActive = null,
        string $sort = '',
        int $page = 1,
        int $limit = 20,
        string $filter = '',
    ): array {
        $orderBy = SQL_SORT($sort, 'type ASC, position ASC, label ASC');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['franchise_code = ?'];
        $params = [$this->code];

        if ($type !== null) {
            $where[]  = 'type = ?';
            $params[] = $type;
        }
        if ($isActive !== null) {
            $where[]  = 'is_active = ?';
            $params[] = (int) $isActive;
        }

        $f = SQL_FILTER($filter);
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM enumeration WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT * FROM enumeration
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM enumeration WHERE id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $row ?: null;
    }

    public function getTypes(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT type FROM enumeration
             WHERE franchise_code = ? ORDER BY type ASC',
            [$this->code],
        );

        return array_column($rows, 'type');
    }

    public function codeExists(string $type, string $code, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->db->fetchOne(
                'SELECT id FROM enumeration
                 WHERE franchise_code = ? AND type = ? AND syscode = ? AND id != ?',
                [$this->code, $type, $code, $excludeId],
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM enumeration
                 WHERE franchise_code = ? AND type = ? AND syscode = ?',
                [$this->code, $type, $code],
            );
        }

        return (bool) $row;
    }

    public function create(array $data): int
    {
        return $this->db->insert('enumeration', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update(
            'enumeration',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }

    public function delete(int $id): void
    {
        $this->db->delete(
            'enumeration',
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }
}
