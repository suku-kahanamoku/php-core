<?php

declare(strict_types=1);

namespace App\Modules\File;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * File – DB vrstva entity.
 */
class FileRepository extends BaseRepository
{
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->table = 'file';
        $this->alias = 'f';
        $this->own   = [
            'temp_token',
            'type',
            'mime_type',
            'path',
            'name',
            'size',
            'visibility',
            'entity_type',
            'entity_id',
            'expires_at',
        ];
        $this->rel = [];
    }

    /**
     * Vrati strankovany seznam souboru (pouze commitnute – temp_token IS NULL).
     *
     * @param  int        $page
     * @param  int        $limit
     * @param  string     $sort
     * @param  string     $filter
     * @param  array|null $projection
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function findAll(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj   = new Projection($projection);
        $select = $this->buildSelect($proj);

        $where  = 'f.franchise_code = ? AND f.deleted = 0 AND f.temp_token IS NULL';
        $params = [$this->code];

        if ($filter !== '') {
            $where   .= ' AND (f.name LIKE ? OR f.type LIKE ?)';
            $params[] = "%{$filter}%";
            $params[] = "%{$filter}%";
        }

        $orderBy = match ($sort) {
            'name'         => 'f.name ASC',
            'name_desc'    => 'f.name DESC',
            'size'         => 'f.size ASC',
            'size_desc'    => 'f.size DESC',
            'created_desc' => 'f.created_at DESC',
            default        => 'f.created_at DESC',
        };

        $offset = ($page - 1) * $limit;
        $total  = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM file f WHERE {$where}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM file f WHERE {$where} ORDER BY {$orderBy} LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );

        return $this->resultList($items, $total, $page, $limit);
    }

    /**
     * Najde zaznam dle temp_token (pred commitem).
     *
     * @param  string $token
     * @return array<string, mixed>|null
     */
    public function findByTempToken(string $token): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM file WHERE temp_token = ? AND franchise_code = ? AND deleted = 0',
            [$token, $this->code],
        ) ?: null;
    }

    /**
     * Vlozi novy zaznam (tmp faze).
     *
     * @param  array<string, mixed> $data
     * @return int  nove ID
     */
    public function insert(array $data): int
    {
        $data['franchise_code'] = $this->code;
        return $this->db->insert('file', $data);
    }

    /**
     * Aktualizuje zaznam dle ID a vrati aktualizovany zaznam.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->db->update(
            'file',
            $data,
            'id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        return $this->findById($id, $projection);
    }

    /**
     * Soft-delete zaznamu.
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych radku (0 nebo 1)
     */
    public function softDelete(int $id): int
    {
        return $this->db->update(
            'file',
            ['deleted' => 1],
            'id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
    }
}
