<?php

declare(strict_types=1);

namespace App\Modules\Text;

use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Text (CMS) – DB entity layer.
 */
class TextRepository
{
    private Database $db;
    private string   $code;

    private const SYS = ['id', 'created_at', 'updated_at'];
    private const OWN = ['syscode', 'title', 'content', 'language', 'is_active', 'created_by'];
    private const REL = [];

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    /**
     * Vrati strankovany seznam textovych obsahu (CMS).
     *
     * @return array{
     *   items: list<array{
     *     id: int,
     *     created_at: string,
     *     updated_at: string,
     *     syscode: string,
     *     title: string,
     *     content: string,
     *     language: string,
     *     is_active: int,
     *     created_by: int|null
     *   }>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   totalPages: int
     * }
     */
    public function findAll(
        string $language = 'cs',
        ?bool $isActive = null,
        ?string $search = null,
        string $sort = '',
        int $page = 1,
        int $limit = 20,
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'syscode ASC');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

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

        $f = SQL_FILTER($filter);
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $cols    = array_merge($sys, $ownCols);
        $select  = implode(', ', $cols);

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM text WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM text WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            $item = $proj->apply($item, $sys);
        }
        unset($item);

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Najde textovy obsah dle ID.
     *
     * @return array{id: int, created_at: string, updated_at: string, syscode: string, title: string, content: string, language: string, is_active: int, created_by: int|null}|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj    = new Projection($projection);
        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $cols    = array_merge($sys, $ownCols);
        $select  = implode(', ', $cols);

        $row = $this->db->fetchOne(
            "SELECT {$select} FROM text WHERE id = ? AND franchise_code = ?",
            [$id, $this->code],
        );

        if (!$row) {
            return null;
        }

        return $proj->apply($row, $sys);
    }

    /**
     * Najde textovy obsah dle syscode a jazyka.
     *
     * @return array{id: int, created_at: string, updated_at: string, syscode: string, title: string, content: string, language: string, is_active: int, created_by: int|null}|null
     */
    public function findByKey(string $key, string $language): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM text WHERE franchise_code = ? AND syscode = ? AND language = ?',
            [$this->code, $key, $language],
        );

        return $row ?: null;
    }

    /**
     * Vraci true, pokud kombinace syscode + language jiz existuje.
     */
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

    /**
     * Vlozi novy textovy obsah a vrati vytvoreny zaznam.
     *
     * @param  array<string, mixed> $data
     * @return array{id: int, created_at: string, updated_at: string, syscode: string, title: string, content: string, language: string, is_active: int, created_by: int|null}
     */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('text', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Aktualizuje textovy obsah a vrati aktualizovany zaznam.
     *
     * @param  array<string, mixed> $data
     * @return array{id: int, created_at: string, updated_at: string, syscode: string, title: string, content: string, language: string, is_active: int, created_by: int|null}
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->db->update(
            'text',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    public function delete(int $id): void
    {
        $this->db->delete('text', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }
}
