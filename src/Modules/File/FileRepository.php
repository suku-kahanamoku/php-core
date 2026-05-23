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
        $this->_table = 'file';
        $this->_alias = 'f';
        $this->_own   = [
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
        $this->_rel = [];
    }

    /**
     * Vrati strankovany seznam souboru.
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
        $select = $this->_buildSelect($proj);

        // Extrahuj 'deleted' z filtru (vychozi 0 = pouze aktivni).
        $filterArr  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deletedVal = isset($filterArr['deleted']) ? (int) $filterArr['deleted'] : 0;
        unset($filterArr['deleted']);
        $search = $filterArr['search'] ?? ($filter !== '' && !str_starts_with($filter, '{') ? $filter : '');

        $where  = 'f.franchise_code = ? AND f.deleted = ?';
        $params = [$this->_code, $deletedVal];

        if ($search !== '') {
            $where   .= ' AND (f.name LIKE ? OR f.type LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
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
        $total  = (int) $this->_db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM file f WHERE {$where}",
            $params,
        )['cnt'];

        $items = $this->_db->fetchAll(
            "SELECT {$select} FROM file f WHERE {$where} ORDER BY {$orderBy} LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );

        return $this->_resultList($items, $total, $page, $limit);
    }

    /**
     * Vlozi novy zaznam (tmp faze).
     *
     * @param  array<string, mixed> $data
     * @return int  nove ID
     */
    public function insert(array $data): int
    {
        $data['franchise_code'] = $this->_code;
        return $this->_db->insert('file', $data);
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
        $this->_db->update(
            'file',
            $data,
            'id = ? AND franchise_code = ?',
            [$id, $this->_code]
        );
        return $this->findById($id, $projection);
    }

    /**
     * Nacte soubory entity pres junction tabulku.
     * Vraci pole objektu [{id, path, name, mime_type}].
     *
     * @param  string $junctionTable   Nazev junction tabulky (napr. 'product_file')
     * @param  string $entityFkColumn  FK sloupec entity v junction tabulce (napr. 'product_id')
     * @param  int    $entityId
     * @return list<array{id: int, path: string, name: string, mime_type: string}>
     */
    public function findByJunctionItem(string $junctionTable, string $entityFkColumn, int $entityId): array
    {
        $rows = $this->_db->fetchAll(
            "SELECT j.file_id, f.path, f.name, f.mime_type
             FROM {$junctionTable} j
             INNER JOIN file f ON f.id = j.file_id AND f.deleted = 0
             WHERE j.{$entityFkColumn} = ?",
            [$entityId],
        );

        return array_map(static fn($r) => [
            'id'        => (int) $r['file_id'],
            'path'      => $r['path'],
            'name'      => $r['name'],
            'mime_type' => $r['mime_type'],
        ], $rows);
    }

    /**
     * Batch load souboru pro vice entit pres junction tabulku.
     * Vraci mapu [entityId => [{id, path, name, mime_type}]].
     *
     * @param  string    $junctionTable
     * @param  string    $entityFkColumn
     * @param  list<int> $entityIds
     * @return array<int, list<array{id: int, path: string, name: string, mime_type: string}>>
     */
    public function findByJunctionList(string $junctionTable, string $entityFkColumn, array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($entityIds), '?'));
        $rows = $this->_db->fetchAll(
            "SELECT j.{$entityFkColumn} AS entity_id, j.file_id, f.path, f.name, f.mime_type
             FROM {$junctionTable} j
             INNER JOIN file f ON f.id = j.file_id AND f.deleted = 0
             WHERE j.{$entityFkColumn} IN ({$placeholders})",
            $entityIds,
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['entity_id']][] = [
                'id'        => (int) $row['file_id'],
                'path'      => $row['path'],
                'name'      => $row['name'],
                'mime_type' => $row['mime_type'],
            ];
        }

        return $map;
    }

    /**
     * Ulozi raw obsah souboru na disk, zaregistruje zaznam v DB a vrati file ID.
     *
     * Pouziti pro programaticky generovane soubory (napr. PDF faktury),
     * kde nevznika HTTP upload — obsah je rovnou k dispozici jako string.
     *
     * @param  string      $content      Raw obsah souboru
     * @param  string      $name         Zobrazovany nazev (napr. '2026-00001.pdf')
     * @param  string      $mimeType     MIME type (napr. 'application/pdf')
     * @param  string      $type         Pripona / typ (napr. 'pdf')
     * @param  string      $entityType   Nazev entity (napr. 'invoice')
     * @param  int         $entityId     ID entity
     * @param  string      $visibility   'public' | 'private'
     * @return int  nove file.id
     */
    public function storeContent(
        string $content,
        string $name,
        string $mimeType,
        string $type,
        string $entityType,
        int $entityId,
        string $visibility = 'private',
    ): int {
        $root    = rtrim($_ENV['FILE_ROOT'] ?? dirname(__DIR__, 3), '/');
        $dir     = $root . '/files/' . $this->_code . '/' . $entityType . '/' . $entityId;
        $relPath = 'files/' . $this->_code . '/' . $entityType . '/' . $entityId . '/' . $name;
        $absPath = $root . '/' . $relPath;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }

        if (file_put_contents($absPath, $content) === false) {
            throw new \RuntimeException("Cannot write file: {$absPath}");
        }

        return $this->insert([
            'type'        => $type,
            'mime_type'   => $mimeType,
            'path'        => $relPath,
            'name'        => $name,
            'size'        => strlen($content),
            'visibility'  => $visibility,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
        ]);
    }
}
