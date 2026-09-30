<?php

declare(strict_types=1);

namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\{Projection, QueryPolicy};

/**
 * Sdílené CRUD SQL pro pevný registr zdrojů modulu Etymolog; název tabulky se nikdy nebere z HTTP.
 *
 * Připustná pole, filtry a řazení se odvozují z `ResourceRegistry`, takže klient
 * nemůže přistupovat ke sloupcům mimo definici zdroje. Systémová pole se ze
 * výsledku vyfiltrují podle projekce.
 */
final class EtymologRepository extends BaseRepository
{
    /**
     * @param  Database $db     Databazove pripojeni.
     * @param  string   $tenant Kod okurku; vsechny dotazy jsou jim omezene.
     * @param  string   $resource Klíč zdroje z `ResourceRegistry`.
     * @return void
     * @throws EtymologException Při neznámém klíči zdroje.
     */
    public function __construct(Database $db, string $tenant, public readonly string $resource)
    {
        parent::__construct($db, $tenant);
        $definition = ResourceRegistry::get($resource);
        $this->_table = $definition['table'];
        $this->_alias = 'e';
        $this->_own = [...array_keys($definition['fields']), ...($definition['system'] ?? []), 'created_by', 'updated_by'];
    }

    /**
     * Vrátí stránku záznamů podle standardního dotazového kontraktu.
     *
     * @param  int                 $page       Číslo stránky (1–1 000 000).
     * @param  int                 $limit      Velikost stránky (1–100).
     * @param  string              $sort       Řazení `pole:směr`, omezeno na povolená pole.
     * @param  string              $filter     Filtr `q` jako JSON, omezený na povolená pole.
     * @param  array<int, string>|null $projection Požadovaná pole, nebo null pro vše.
     * @return array<string, mixed>            Řádky a metadata stránky.
     */
    public function findAll(int $page = 1, int $limit = 20, string $sort = '', string $filter = '', ?array $projection = null): array
    {
        $page = max(1, min(1000000, $page));
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;
        $allowed = [...$this->_own, 'id', 'created_at', 'updated_at'];
        $f = SQL_FILTER(QueryPolicy::filter($filter, $allowed), 'e');
        $where = 'e.franchise_code = ? AND e.deleted = 0' . ($f['sql'] ? ' AND '.$f['sql'] : '');
        $params = [$this->_code, ...$f['params']];
        $order = SQL_SORT(QueryPolicy::sort($sort, $allowed), 'e.id ASC', 'e');
        $proj = new Projection($projection);
        $select = $this->_buildSelect($proj);
        $total = (int)$this->_db->fetchOne("SELECT COUNT(*) AS cnt FROM {$this->_table} e WHERE {$where}", $params)['cnt'];
        $rows = $this->_db->fetchAll("SELECT {$select} FROM {$this->_table} e WHERE {$where} ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}", $params);
        return $this->_resultList(array_map(fn ($row) => $proj->apply($row, $this->_sys), $rows), $total, $page, $limit);
    }

    /**
     * Načte záznam včetně zrušených; používá se až po autorizaci administrátora
     * pro fyzické smazání.
     *
     * @param  int $id ID záznamu.
     * @return array<string, mixed>|null Záznam, nebo null pokud v okurku neexistuje.
     */
    public function findIncludingDeleted(int $id): ?array
    {
        $select = $this->_buildSelect(new Projection(null));
        return $this->_db->fetchOne("SELECT {$select} FROM {$this->_table} e WHERE e.id=? AND e.franchise_code=?", [$id, $this->_code]) ?: null;
    }

    /**
     * Načte dávku nezveřejněných konceptů nad zadaným ID.
     *
     * Stránkování klíčem zůstává stabilní, i když se koncepty mezitím zveřejní
     * nebo validace odmítne.
     *
     * @param  int $after ID posledního zpracovaného záznamu.
     * @return list<array<string, mixed>> Koncepty seřazené podle ID (max. 200).
     * @throws EtymologException 403, pokud zdroj nemá stav publikace.
     */
    public function draftsAfter(int $after): array
    {
        $this->requirePublicationResource();
        return $this->_db->fetchAll("SELECT * FROM {$this->_table} WHERE franchise_code=? AND deleted=0 AND published=0 AND id>? ORDER BY id LIMIT 200", [$this->_code, $after]);
    }

    /**
     * Zveřejní dávku konceptů jedním příkazem.
     *
     * @param  list<int> $ids   ID konceptů.
     * @param  int       $actor ID uživatele, kterého se zapíše jako autor.
     * @return int              Počet skutečně zveřejněných záznamů.
     * @throws EtymologException 403, pokud zdroj nemá stav publikace.
     */
    public function publishIds(array $ids, int $actor): int
    {
        $this->requirePublicationResource();
        if ($ids === []) { return 0; }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->_db->query("UPDATE {$this->_table} SET published=1,updated_by=? WHERE franchise_code=? AND deleted=0 AND published=0 AND id IN ({$placeholders})", [$actor, $this->_code, ...$ids])->rowCount();
    }

    /**
     * Ověří, že zdroj má stav publikace.
     *
     * @return void
     * @throws \LogicException Pokud zdroj mezi 'names', 'entries' a 'calendar-days' není.
     */
    private function requirePublicationResource(): void
    {
        if (!in_array($this->resource, ['names', 'entries', 'calendar-days'], true)) { throw new \LogicException('Resource has no publication state'); }
    }

    /**
     * Vloží záznam a vrátí jeho celý obsah.
     *
     * @param  array<string, mixed> $data Normalizované hodnoty z `EtymologService::validate()`.
     * @return array<string, mixed>       Vytvořený záznam.
     * @throws EtymologException         404, pokud se vložený záznam nepovede načíst.
     */
    public function create(array $data): array
    {
        $id = $this->_db->insert($this->_table, array_merge($data, ['franchise_code' => $this->_code]));
        return $this->findById($id);
    }

    /**
     * Aktualizuje záznam a vrátí jeho obsah po změně.
     *
     * `franchise_code` a `id` se ignorují, aby je nebylo možné přepsat.
     *
     * @param  int                  $id   ID záznamu.
     * @param  array<string, mixed> $data Sloupce k aktualizaci.
     * @return array<string, mixed>       Aktualizovaný záznam; u měkkého smazání jen `{ id }`.
     * @throws EtymologException         404, pokud záznam neexistuje.
     */
    public function update(int $id, array $data): array
    {
        unset($data['franchise_code'], $data['id']);
        if ($data !== []) {
            $this->_db->update($this->_table, $data, 'id = ? AND franchise_code = ? AND deleted = 0', [$id, $this->_code]);
        }
        return $this->findById($id) ?? ((int)($data['deleted'] ?? 0) === 1 ? ['id' => $id] : throw new EtymologException('Record not found', 404));
    }

    /**
     * Zserializuje zmutace modulu včetně validace vazeb mezi zdroji a měkkého smazání.
     *
     * @param  callable $action Akce k provedení pod zámkem okurku.
     * @return mixed           Návratová hodnota akce.
     * @throws EtymologException 409 'Etymolog is busy', pokud drží zámek jiná relace.
     */
    public function exclusive(callable $action): mixed
    {
        $key = 'etymolog:'.substr(hash('sha256', $this->_code), 0, 48);
        if ((int)$this->_db->fetchOne('SELECT GET_LOCK(?, 2) AS acquired', [$key])['acquired'] !== 1) {
            throw new EtymologException('Etymolog is busy; retry later', 409);
        }
        try {
            return $action();
        } finally {
            $this->_db->query('SELECT RELEASE_LOCK(?)', [$key]);
        }
    }

    /**
     * Provede akci v jedné transakci.
     *
     * @param  callable $action Akce k provedení.
     * @return mixed           Návratová hodnota akce.
     * @throws \Throwable      Výjimka z akce se po rollbacku znovu vyhodí.
     */
    public function transaction(callable $action): mixed
    {
        $pdo = $this->_db->getPdo();
        $pdo->beginTransaction();
        try {
            $result = $action();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Zjistí, zda na záznamu něco závisí (reference mezi zdroji, importy, běhy).
     *
     * @param  int  $id             ID záznamu.
     * @param  bool $includeDeleted true při natvrdém mazání, kdy se počítají i zrušené vazby.
     * @return bool                true, pokud záznam nelze smazat.
     */
    public function hasDependants(int $id, bool $includeDeleted): bool
    {
        foreach (ResourceRegistry::all() as $definition) {
            foreach ($definition['references'] ?? [] as $column => $target) {
                if ($target !== $this->resource) {
                    continue;
                }
                $active = $includeDeleted ? '' : ' AND deleted = 0';
                if ($this->_db->fetchOne("SELECT id FROM {$definition['table']} WHERE franchise_code = ? AND `{$column}` = ?{$active} LIMIT 1", [$this->_code, $id])) {
                    return true;
                }
            }
        }
        if ($includeDeleted && in_array($this->resource, ['names', 'entries', 'sources', 'occurrences', 'calendar-days'], true)) {
            $column = ['names' => 'name_id', 'entries' => 'entry_id', 'sources' => 'source_id', 'occurrences' => 'occurrence_id', 'calendar-days' => 'calendar_day_id'][$this->resource];
            if ($this->_db->fetchOne("SELECT id FROM etymolog_external_record WHERE franchise_code=? AND {$column}=? LIMIT 1", [$this->_code, $id])) { return true; }
        }
        if ($includeDeleted && in_array($this->resource, ['entries', 'sources'], true)) {
            $column = $this->resource === 'entries' ? 'entry_id' : 'source_id';
            if ($this->_db->fetchOne("SELECT id FROM etymolog_story_import WHERE franchise_code=? AND {$column}=? LIMIT 1", [$this->_code, $id])) {
                return true;
            }
        }
        if ($includeDeleted && in_array($this->resource, ['names', 'sync-jobs'], true)) {
            [$table, $column] = $this->resource === 'names' ? ['etymolog_import_record', 'name_id'] : ['etymolog_sync_run', 'job_id'];
            return (bool)$this->_db->fetchOne("SELECT id FROM {$table} WHERE franchise_code = ? AND {$column} = ? LIMIT 1", [$this->_code, $id]);
        }
        return false;
    }

    /**
     * Zjistí, zda už výklad odkazuje na dané jméno.
     *
     * @param  int      $entryId  ID výkladu.
     * @param  int      $nameId   ID jména.
     * @param  int|null $exceptId ID vazby, která se při úpravě ignoruje.
     * @return bool              true, pokud už aktivní vazba existuje.
     */
    public function hasActiveLink(int $entryId, int $nameId, ?int $exceptId): bool
    {
        return (bool)$this->_db->fetchOne('SELECT id FROM etymolog_entry_name WHERE franchise_code=? AND entry_id=? AND name_id=? AND deleted=0 AND id<>? LIMIT 1', [$this->_code, $entryId, $nameId, $exceptId ?? 0]);
    }

    /**
     * Zjistí, zda je výklad zveřejněný a patří mezi kulturní typy.
     *
     * @param  int $entryId ID výkladu.
     * @return bool         true, pokud jde o zveřejněný kulturní text.
     */
    public function isPublishedCultural(int $entryId): bool
    {
        $row = $this->_db->fetchOne('SELECT type,published FROM etymolog_entry WHERE franchise_code=? AND id=? AND deleted=0', [$this->_code, $entryId]);
        return $row && (bool)$row['published'] && in_array($row['type'], ResourceRegistry::CULTURAL_TYPES, true);
    }

    /**
     * Zjistí, zda zdroj podpírá zveřejněný důkaz — kalendářní den nebo citaci kulturního textu.
     *
     * @param  int $sourceId ID zdroje.
     * @return bool          true, pokud zdroj nelze upravit bez zrušení publikace dotčených záznamů.
     */
    public function hasPublishedEvidenceUse(int $sourceId): bool
    {
        if ($this->_db->fetchOne('SELECT id FROM etymolog_calendar_day WHERE franchise_code=? AND source_id=? AND published=1 AND deleted=0 LIMIT 1', [$this->_code, $sourceId])) {return true;}
        $rows = $this->_db->fetchAll('SELECT e.type FROM etymolog_citation c JOIN etymolog_entry e ON e.franchise_code=c.franchise_code AND e.id=c.entry_id WHERE c.franchise_code=? AND c.source_id=? AND c.deleted=0 AND e.deleted=0 AND e.published=1', [$this->_code, $sourceId]);
        return (bool)array_intersect(array_column($rows, 'type'), ResourceRegistry::CULTURAL_TYPES);
    }

    /**
     * Ověří, zda existuje citace na uvedenou webovou adresu, která obsahuje text výkladu doslova.
     *
     * Vyžaduje zdroj s licencí a uvedením autora; bílé znaky se před porovnáním
     * sjednotí, takže formátování zdroje není překážkou.
     *
     * @param  int    $entryId ID výkladu.
     * @param  string $url     Původní webová adresa zdroje.
     * @param  string $body    Text výkladu, který musí být v citaci citován doslova.
     * @return bool            true, pokud odpovídající citace existuje.
     */
    public function hasWebQuotation(int $entryId, string $url, string $body): bool
    {
        $rows = $this->_db->fetchAll("SELECT c.quotation FROM etymolog_citation c JOIN etymolog_source s ON s.franchise_code=c.franchise_code AND s.id=c.source_id WHERE c.franchise_code=? AND c.entry_id=? AND c.deleted=0 AND s.deleted=0 AND COALESCE(c.url,s.url)=? AND s.license IS NOT NULL AND s.license<>'' AND (COALESCE(s.attribution,'')<>'' OR COALESCE(s.author,'')<>'')", [$this->_code, $entryId, $url]);
        $normalize = static fn (string $text): string => trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $body = $normalize($body);
        foreach ($rows as $row) {
            if ($body !== '' && $row['quotation'] !== null && str_contains($normalize($row['quotation']), $body)) { return true; }
        }
        return false;
    }

    /**
     * Zjistí, zda má výklad alespoň jednu platnou citaci.
     *
     * @param  int $entryId ID výkladu.
     * @return bool         true, pokud existuje vazba na nezrušený zdroj.
     */
    public function hasCitation(int $entryId): bool
    {
        return (bool)$this->_db->fetchOne('SELECT c.id FROM etymolog_citation c JOIN etymolog_source s ON s.id=c.source_id AND s.franchise_code=c.franchise_code AND s.deleted=0 WHERE c.franchise_code=? AND c.entry_id=? AND c.deleted=0 LIMIT 1', [$this->_code, $entryId]);
    }
}
