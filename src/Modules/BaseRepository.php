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
 *   - resultList() — standardni pole strankovaci odpovedi
 *
 * Kazda podtrida musi v konstruktoru nastavit:
 *   $this->_table  — nazev tabulky v DB
 *   $this->_alias  — alias tabulky pouzivany v SQL (napr. 'u', 'p', '')
 *   $this->_own    — vlastni sloupce (bez SYS)
 *   $this->_rel    — vazebne klice pro projection (napr. ['role', 'user'])
 *
 * Volitelne prepsat:
 *   $this->_sys    — systemove sloupce (default: id, created_at, updated_at)
 */
abstract class BaseRepository
{
    protected Database $_db;
    protected string   $_code;

    protected string $_table    = '';
    protected string $_alias    = '';
    protected array  $_sys      = ['id', 'created_at', 'updated_at', 'deleted'];
    protected array  $_own      = [];
    protected array  $_rel      = [];
    /** Nazvy sloupcu, ktere jsou v DB ulozeny jako JSON (napr. ['data']). */
    protected array  $_jsonCols = [];

    /**
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        $this->_db   = $db;
        $this->_code = $franchiseCode;
    }

    /**
     * Sestavi aliasovanou SELECT klauzuli z projekce.
     *
     * @param  Projection $proj
     * @return string
     */
    protected function _buildSelect(Projection $proj): string
    {
        $a       = $this->_alias !== '' ? $this->_alias . '.' : '';
        $ownCols = $proj->getOwnCols($this->_own, $this->_rel);
        $sysSel  = $a . implode(", {$a}", $this->_sys);
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
        $select = $this->_buildSelect($proj);
        $a      = $this->_alias !== '' ? $this->_alias . '.' : '';
        $from   = $this->_alias !== '' ? "{$this->_table} {$this->_alias}" : $this->_table;

        $row = $this->_db->fetchOne(
            "SELECT {$select} FROM {$from} WHERE {$a}id = ? AND {$a}franchise_code = ? AND {$a}deleted = 0",
            [$id, $this->_code],
        );

        return $row ? $proj->apply($row, $this->_sys) : null;
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
    /**
     * Vrati franchise_code tohoto repozitare.
     */
    public function getCode(): string
    {
        return $this->_code;
    }

    /**
     * PATCH semantika pro JSON sloupce.
     *
     * Pro kazdy sloupec z $_jsonCols, ktery je v $data pritomen jako pole,
     * nacte aktualni zaznam a merguje existujici JSON s dodanymi atributy.
     * Ostatni klice v JSON sloupci zustavaji nedotceny.
     *
     * - hodnota null  → ulozi NULL do DB (beze zmeny)
     * - hodnota pole  → merguje s existujicim JSON a JSON-enkoduje
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function _patchJsonCols(int $id, array $data): array
    {
        $jsonKeysInData = array_intersect(array_keys($data), $this->_jsonCols);

        if (empty($jsonKeysInData)) {
            return $data;
        }

        $current = $this->findById($id) ?? [];

        foreach ($jsonKeysInData as $col) {
            $newVal = $data[$col];

            if ($newVal === null) {
                // null explicitne nastavi sloupec na NULL, zadny merge
                continue;
            }

            if (is_array($newVal)) {
                $existing    = (isset($current[$col]) && is_array($current[$col]))
                    ? $current[$col] : [];
                $data[$col]  = json_encode(
                    array_merge($existing, $newVal),
                    JSON_UNESCAPED_UNICODE
                );
            }
            // Jiz retezec nebo jiny skalar – neupravovat
        }

        return $data;
    }

    /**
     * PATCH semantika pro JSON sloupce.
     *
     * Pro kazdy sloupec z $_jsonCols, ktery je v $data pritomen jako pole,
     * nacte aktualni zaznam a merguje existujici JSON s dodanymi atributy.
     * Ostatni klice v JSON sloupci zustavaji nedotceny.
     *
     * - hodnota null  → ulozi NULL do DB (beze zmeny)
     * - hodnota pole  → merguje s existujicim JSON a JSON-enkoduje
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych radku (0 nebo 1)
     */
    public function softDelete(int $id): int
    {
        return $this->_db->update(
            $this->_table,
            ['deleted' => 1],
            'id = ? AND franchise_code = ?',
            [$id, $this->_code]
        );
    }

    /**
     * Hard-smazani zaznamu z DB (fyzicke smazani radku).
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych radku (0 nebo 1)
     */
    public function hardDelete(int $id): int
    {
        return $this->_db->delete(
            $this->_table,
            'id = ? AND franchise_code = ?',
            [$id, $this->_code]
        );
    }

    /**
     * Vrati standardni pole strankovaci odpovedi.
     *
     * @param  list<array<string, mixed>> $items
     */
    protected function _resultList(
        array $items,
        int $total,
        int $page,
        int $limit
    ): array {
        return [
            'data'       => $items,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }
}
