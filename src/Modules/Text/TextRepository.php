<?php

declare(strict_types=1);

namespace App\Modules\Text;

use App\Modules\Database\Database;

/**
 * Text (CMS) – DB entity layer.
 */
class TextRepository
{
    private Database $db;
    private string   $code;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    public function findAll(
        string $language = 'cs',
        ?bool $isActive = null,
        ?string $search = null,
        string $sortBy = 'syscode',
        string $sortDir = 'ASC',
    ): array {
        $allowed = ['syscode', 'title', 'created_at', 'updated_at'];
        $sortBy  = in_array($sortBy, $allowed, true) ? $sortBy : 'syscode';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $where  = ['franchise_code = ?', 'language = ?'];
        $params = [$this->code, $language];

        if ($isActive !== null) {
            $where[]  = 'is_active = ?';
            $params[] = (int) $isActive;
        }
        if ($search) {
            $where[] = '(syscode LIKE ? OR title LIKE ? OR content LIKE ?)';
            $s       = '%' . $search . '%';
            array_push($params, $s, $s, $s);
        }

        $whereStr = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT id, syscode, title, language, is_active, created_at, updated_at
             FROM text WHERE {$whereStr} ORDER BY {$sortBy} {$sortDir}",
            $params,
        );
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM text WHERE id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $row ?: null;
    }

    public function findByKey(string $key, string $language): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM text WHERE franchise_code = ? AND syscode = ? AND language = ?',
            [$this->code, $key, $language],
        );

        return $row ?: null;
    }

    public function keyExists(string $key, string $language, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->db->fetchOne(
                'SELECT id FROM text
                 WHERE franchise_code = ? AND syscode = ? AND language = ? AND id != ?',
                [$this->code, $key, $language, $excludeId],
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM text
                 WHERE franchise_code = ? AND syscode = ? AND language = ?',
                [$this->code, $key, $language],
            );
        }

        return (bool) $row;
    }

    public function create(array $data): int
    {
        return $this->db->insert('text', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update(
            'text',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }

    public function delete(int $id): void
    {
        $this->db->delete('text', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }
}
