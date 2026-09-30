<?php

declare(strict_types=1);

namespace App\Modules\CustomerProfile;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;

/**
 * DB vrstva zákaznických profilů.
 *
 * Kromě sloupců v tabulce `customer_profile` načítá i tři podřízené kolekce
 * (otázky, námytky, preference) a skládá je do výsledku pod klíči
 * `questions`, `objections` a `preferences`.
 */
class CustomerProfileRepository extends BaseRepository
{
    /**
     * @param  Database $db            Připojení k databázi.
     * @param  string   $franchiseCode Kód okurku, podle kterého se filtrují řádky.
     * @return void
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->_table = 'customer_profile';
        $this->_alias = 'cp';
        $this->_own = ['profile_number','syscode','name','selection_need','summary','aura','visual','behavior','business_potential','typical_quote','average_basket','marketing_note','position','published'];
    }

    /**
     * Vrátí stránkovaný seznam profilů se všemi podřízenými kolekcemi.
     *
     * @param  int    $page   Číslo stránky (od 1).
     * @param  int    $limit  Počet záznamů na stránku; ořízne na maximálně 100.
     * @param  string $sort   Řazení dle standardního dotazového kontraktu.
     * @param  string $filter JSON filtr; klíč `deleted` se vyjme a řídí výběr smazaných řádků.
     * @return array           Stránkovací odpověď s `data`, `total`, `page`, `limit`, `totalPages`.
     */
    public function findAll(int $page = 1, int $limit = 100, string $sort = '', string $filter = ''): array
    {
        $limit = min(100, max(1, $limit));
        $page = max(1, $page);
        $where = ['cp.franchise_code = ?'];
        $params = [$this->_code];
        $filters = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deleted = isset($filters['deleted']) ? (int) $filters['deleted'] : 0;
        unset($filters['deleted']);
        $where[] = 'cp.deleted = ?';
        $params[] = $deleted;
        $f = SQL_FILTER($filters ? json_encode($filters) : '', 'cp');
        if ($f['sql'] !== '') { $where[] = $f['sql']; array_push($params, ...$f['params']); }
        $whereSql = implode(' AND ', $where);
        $orderBy = SQL_SORT($sort, 'cp.position ASC, cp.id ASC', 'cp');
        $total = (int) $this->_db->fetchOne("SELECT COUNT(*) cnt FROM customer_profile cp WHERE {$whereSql}", $params)['cnt'];
        $rows = $this->_db->fetchAll("SELECT cp.* FROM customer_profile cp WHERE {$whereSql} ORDER BY {$orderBy} LIMIT {$limit} OFFSET " . (($page - 1) * $limit), $params);
        $this->attachRelations($rows);
        return $this->_resultList($rows, $total, $page, $limit);
    }

    /**
     * Najde jeden profil včetně podřízených kolekcí.
     *
     * @param  int        $id         ID profilu.
     * @param  array|null $projection Rezervováno pro kompatibilitu; tento dotaz vrací celý řádek.
     * @return array<string, mixed>|null Profil, nebo null pokud neexistuje nebo je smazaný.
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $row = $this->_db->fetchOne('SELECT * FROM customer_profile WHERE id = ? AND franchise_code = ? AND deleted = 0', [$id, $this->_code]);
        if (!$row) return null;
        $rows = [$row];
        $this->attachRelations($rows);
        return $rows[0];
    }

    /**
     * Načte více profilů jedním dotazem.
     *
     * @param  list<array-key,mixed> $ids Seznam ID profilů; prázdný seznam vrací prázdné pole.
     * @return list<array<string, mixed>>  Profily seřazené podle pozice a ID.
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->_db->fetchAll("SELECT * FROM customer_profile WHERE franchise_code = ? AND deleted = 0 AND id IN ({$marks}) ORDER BY position,id", [$this->_code, ...$ids]);
        $this->attachRelations($rows);
        return $rows;
    }

    /**
     * Ověří, zda v okurku už existuje profil se stejným systémovým kódem.
     *
     * @param  string   $syscode   Systémový kód profilu.
     * @param  int|null $excludeId ID profilu, které se při kontrole ignoruje (vlastní řádek při update).
     * @return bool                true, pokud existuje jiný profil se stejným kódem.
     */
    public function codeExists(string $syscode, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM customer_profile WHERE franchise_code = ? AND syscode = ?';
        $params = [$this->_code, $syscode];
        if ($excludeId !== null) { $sql .= ' AND id != ?'; $params[] = $excludeId; }
        return (bool) $this->_db->fetchOne($sql . ' LIMIT 1', $params);
    }

    /**
     * Vloží profil a jeho podřízené kolekce (otázky, námytky, preference).
     *
     * @param  array<string, mixed> $data      Atributy profilu včetně polí `questions`, `objections`, `preferences`.
     * @param  array|null           $projection Rezervováno pro kompatibilitu.
     * @return array<string, mixed>            Vytvořený profil.
     */
    public function create(array $data, ?array $projection = null): array
    {
        $questions = $data['questions'] ?? []; $objections = $data['objections'] ?? []; $preferences = $data['preferences'] ?? [];
        unset($data['questions'], $data['objections'], $data['preferences']);
        $id = $this->_db->insert('customer_profile', ['franchise_code' => $this->_code, ...$data]);
        $this->syncText('customer_profile_question', 'question', $id, $questions);
        $this->syncText('customer_profile_objection', 'objection', $id, $objections);
        $this->syncPreferences($id, $preferences);
        return $this->findById($id) ?? ['id' => $id];
    }

    /**
     * Aktualizuje profil; přítomné podřízené kolekce se přepíší celé.
     *
     * @param  int                  $id        ID profilu.
     * @param  array<string, mixed> $data      Sloupce a nové hodnoty; prázdné pole znamená nechat beze změny.
     * @param  array|null           $projection Rezervováno pro kompatibilitu.
     * @return array<string, mixed>            Aktualizovaný profil.
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        foreach (['questions' => ['customer_profile_question','question'], 'objections' => ['customer_profile_objection','objection']] as $key => [$table,$column]) {
            if (array_key_exists($key, $data)) $this->syncText($table, $column, $id, is_array($data[$key]) ? $data[$key] : []);
            unset($data[$key]);
        }
        if (array_key_exists('preferences', $data)) { $this->syncPreferences($id, is_array($data['preferences']) ? $data['preferences'] : []); unset($data['preferences']); }
        if ($data) $this->_db->update('customer_profile', $data, 'id = ? AND franchise_code = ?', [$id, $this->_code]);
        return $this->findById($id) ?? ['id' => $id];
    }

    /**
     * Přepíše textovou podřízenou kolekci profilu (otázky nebo námytky).
     *
     * @param  string   $table  Název tabulky podřízené kolekce.
     * @param  string   $column Název sloupce s textem.
     * @param  int      $id     ID profilu.
     * @param  array    $values Hodnoty (řetězce nebo pole s klíčem odpovídajícím sloupci); prázdné položky se přeskočí.
     * @return void             Vedlejší efekt: smaže a znovu vloží řádky kolekce.
     */
    private function syncText(string $table, string $column, int $id, array $values): void
    {
        $this->_db->delete($table, 'customer_profile_id = ?', [$id]);
        foreach (array_values($values) as $i => $value) {
            $text = trim((string) (is_array($value) ? ($value[$column] ?? '') : $value));
            if ($text !== '') $this->_db->insert($table, ['customer_profile_id'=>$id, $column=>$text, 'position'=>($i+1)*10]);
        }
    }

    /**
     * Přepíše preference profilu; přijímá pouze typy `animal` a `product_kind`.
     *
     * @param  int   $id     ID profilu.
     * @param  array $values Pole položek `{ type, value }`; neplatné položky se přeskočí.
     * @return void          Vedlejší efekt: smaže a znovu vloží řádky preference.
     */
    private function syncPreferences(int $id, array $values): void
    {
        $this->_db->delete('customer_profile_preference', 'customer_profile_id = ?', [$id]);
        foreach (array_values($values) as $i => $value) {
            if (!is_array($value) || !in_array($value['type'] ?? '', ['animal','product_kind'], true)) continue;
            $name = trim((string) ($value['value'] ?? ''));
            if ($name !== '') $this->_db->insert('customer_profile_preference', ['customer_profile_id'=>$id,'preference_type'=>$value['type'],'value'=>$name,'position'=>($i+1)*10]);
        }
    }

    /**
     * Doplní do každého řádku podřízené kolekce profilu (dávkové načtení bez N+1).
     *
     * @param  list<array<string, mixed>> $rows Řádky k doplnění; modifikováno in-place.
     * @return void                       Vedlejší efekt: přidá klíče `questions`, `objections`, `preferences`.
     */
    private function attachRelations(array &$rows): void
    {
        if (!$rows) return;
        $ids = array_map('intval', array_column($rows, 'id'));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $questions = $this->_db->fetchAll("SELECT customer_profile_id,question FROM customer_profile_question WHERE customer_profile_id IN ({$marks}) ORDER BY position,id", $ids);
        $objections = $this->_db->fetchAll("SELECT customer_profile_id,objection FROM customer_profile_objection WHERE customer_profile_id IN ({$marks}) ORDER BY position,id", $ids);
        $preferences = $this->_db->fetchAll("SELECT customer_profile_id,preference_type AS type,value FROM customer_profile_preference WHERE customer_profile_id IN ({$marks}) ORDER BY position,id", $ids);
        $q=[]; $o=[]; $p=[];
        foreach ($questions as $v) $q[(int)$v['customer_profile_id']][]=$v['question'];
        foreach ($objections as $v) $o[(int)$v['customer_profile_id']][]=$v['objection'];
        foreach ($preferences as $v) $p[(int)$v['customer_profile_id']][]=['type'=>$v['type'],'value'=>$v['value']];
        foreach ($rows as &$row) { $id=(int)$row['id']; $row['questions']=$q[$id]??[]; $row['objections']=$o[$id]??[]; $row['preferences']=$p[$id]??[]; }
        unset($row);
    }
}
