<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Zakladni trida pro vsechny repozitare.
 *
 * Poskytuje sdilene pomocne metody:
 *   - findById()       — standardni vyhledani dle ID s projekci
 *   - buildSelect()    — sestaveni SELECT klauzule s aliasem a projekci
 *   - paginationResult() — standardni pole strankovaci odpovedi
 *
 * Kazda podtrida musi v konstruktoru nastavit:
 *   $this->table  — nazev tabulky v DB
 *   $this->alias  — alias tabulky pouzivany v SQL (napr. 'u', 'p', '')
 *   $this->own    — vlastni sloupce (bez SYS)
 *   $this->rel    — vazebne klice pro projection (napr. ['role', 'user'])
 *
 * Volitelne prepsat:
 *   $this->sys    — systemove sloupce (default: id, created_at, updated_at)
 */
abstract class BaseRepository
{
    protected Database $db;
    protected string   $code;

    protected string $table = '';
    protected string $alias = '';
    protected array  $sys   = ['id', 'created_at', 'updated_at'];
    protected array  $own   = [];
    protected array  $rel   = [];

    /**
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    /**
     * Sestavi aliasovanou SELECT klauzuli z projekce.
     *
     * @param  Projection $proj
     * @return string
     */
    protected function buildSelect(Projection $proj): string
    {
        $a       = $this->alias !== '' ? $this->alias . '.' : '';
        $ownCols = $proj->getOwnCols($this->own, $this->rel);
        $sysSel  = $a . implode(", {$a}", $this->sys);
        $ownSel  = $ownCols ? ', ' . $a . implode(", {$a}", $ownCols) : '';
        return $sysSel . $ownSel;
    }

    /**
     * Najde zaznam dle ID s podporou projekce.
     * Podtridy s JOINy v findById() tuto metodu prepisou.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj   = new Projection($projection);
        $select = $this->buildSelect($proj);
        $a      = $this->alias !== '' ? $this->alias . '.' : '';
        $from   = $this->alias !== '' ? "{$this->table} {$this->alias}" : $this->table;

        $row = $this->db->fetchOne(
            "SELECT {$select} FROM {$from} WHERE {$a}id = ? AND {$a}franchise_code = ?",
            [$id, $this->code],
        );

        return $row ? $proj->apply($row, $this->sys) : null;
    }

    /**
     * Vrati standardni pole strankovaci odpovedi.
     *
     * @param  list<array<string, mixed>> $items
     * @param  int                        $total
     * @param  int                        $page
     * @param  int                        $limit
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    protected function paginationResult(
        array $items,
        int $total,
        int $page,
        int $limit
    ): array {
        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }
}
