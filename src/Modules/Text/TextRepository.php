<?php

declare(strict_types=1);

namespace App\Modules\Text;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Text (CMS) – DB vrstva entity.
 */
class TextRepository extends BaseRepository
{
    /**
     * Konstruktor tridy TextRepository.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->_table = 'text';
        $this->_alias = 'tx';
        $this->_own   = [
            'syscode',
            'title',
            'content',
            'language',
            'published',
        ];
    }

    /**
     * Vrati strankovany seznam textovych obsahu (CMS).
     *
     * @param  int         $page
     * @param  int         $limit
     * @param  string      $sort
     * @param  string      $filter
     * @param  array|null  $projection
     * @return array{
     *   items: list<array{
     *     id: int,
     *     created_at: string,
     *     updated_at: string,
     *     syscode: string,
     *     title: string,
     *     content: string,
     *     language: string,
     *     published: int,
     *   }>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   totalPages: int
     * }
     */
    public function findAll(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'tx.syscode ASC', 'tx');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['tx.franchise_code = ?'];
        $params = [$this->_code];

        // Extrahuj 'deleted' z filtru (vychozi 0 = pouze aktivni).
        $filterArr  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deletedVal = isset($filterArr['deleted']) ? (int) $filterArr['deleted'] : 0;
        unset($filterArr['deleted']);
        $filter = count($filterArr) > 0 ? json_encode($filterArr) : '';
        $where[]  = 'tx.deleted = ?';
        $params[] = $deletedVal;

        $f = SQL_FILTER($filter, 'tx');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);
        $select   = $this->_buildSelect($proj);
        $sys      = $this->_sys;

        $total = (int) $this->_db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM text tx WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->_db->fetchAll(
            "SELECT {$select} FROM text tx WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            $item = $proj->apply($item, $sys);
        }
        unset($item);

        return $this->_resultList($items, $total, $page, $limit);
    }

    /**
     * Najde textovy obsah dle syscode a jazyka.
     *
     * @param  string $key
     * @param  string $language
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   syscode: string,
     *   title: string,
     *   content: string,
     *   language: string,
     *   published: int,
     * }|null
     */
    public function findByKey(string $key, string $language): ?array
    {
        $row = $this->_db->fetchOne(
            'SELECT * FROM text WHERE franchise_code = ? AND syscode = ? AND language = ? AND deleted = 0',
            [$this->_code, $key, $language],
        );

        return $row ?: null;
    }

    /**
     * Vraci true, pokud kombinace syscode + language jiz existuje.
     *
     * @param  string   $key
     * @param  string   $language
     * @param  int|null $excludeId
     * @return bool
     */
    public function keyExists(
        string $key,
        string $language,
        ?int $excludeId = null
    ): bool {
        if ($excludeId !== null) {
            $row = $this->_db->fetchOne(
                'SELECT id FROM text
                 WHERE franchise_code = ? AND syscode = ? AND language = ? AND id != ? AND deleted = 0',
                [$this->_code, $key, $language, $excludeId],
            );
        } else {
            $row = $this->_db->fetchOne(
                'SELECT id FROM text
                 WHERE franchise_code = ? AND syscode = ? AND language = ? AND deleted = 0',
                [$this->_code, $key, $language],
            );
        }

        return (bool) $row;
    }

    /**
     * Vlozi novy textovy obsah a vrati vytvoreny zaznam.
     *
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   syscode: string,
     *   title: string,
     *   content: string,
     *   language: string,
     *   published: int,
     * }
     */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->_db->insert('text', array_merge($data, [
            'franchise_code' => $this->_code,
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Aktualizuje textovy obsah a vrati aktualizovany zaznam.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   syscode: string,
     *   title: string,
     *   content: string,
     *   language: string,
     *   published: int,
     * }
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->_db->update(
            'text',
            $data,
            'id = ? AND franchise_code = ?',
            [$id, $this->_code],
        );

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

}
