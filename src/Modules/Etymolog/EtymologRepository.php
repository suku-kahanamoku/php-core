<?php

declare(strict_types=1);

namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\{Projection, QueryPolicy};

/** Shared CRUD SQL for the fixed Etymolog resource registry; never accepts table names from HTTP. */
final class EtymologRepository extends BaseRepository
{
    public function __construct(Database $db, string $tenant, public readonly string $resource)
    {
        parent::__construct($db, $tenant);
        $definition = ResourceRegistry::get($resource);
        $this->_table = $definition['table'];
        $this->_alias = 'e';
        $this->_own = [...array_keys($definition['fields']), ...($definition['system'] ?? []), 'created_by', 'updated_by'];
    }

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

    /** Used only after admin authorization for a physical deletion. */
    public function findIncludingDeleted(int $id): ?array
    {
        $select = $this->_buildSelect(new Projection(null));
        return $this->_db->fetchOne("SELECT {$select} FROM {$this->_table} e WHERE e.id=? AND e.franchise_code=?", [$id, $this->_code]) ?: null;
    }

    public function create(array $data): array
    {
        $id = $this->_db->insert($this->_table, array_merge($data, ['franchise_code' => $this->_code]));
        return $this->findById($id);
    }

    public function update(int $id, array $data): array
    {
        unset($data['franchise_code'], $data['id']);
        if ($data !== []) {
            $this->_db->update($this->_table, $data, 'id = ? AND franchise_code = ? AND deleted = 0', [$id, $this->_code]);
        }
        return $this->findById($id) ?? ((int)($data['deleted'] ?? 0) === 1 ? ['id' => $id] : throw new EtymologException('Record not found', 404));
    }

    /** Serialize module mutations, including cross-resource reference validation and soft deletion. */
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

    public function hasActiveLink(int $entryId, int $nameId, ?int $exceptId): bool
    {
        return (bool)$this->_db->fetchOne('SELECT id FROM etymolog_entry_name WHERE franchise_code=? AND entry_id=? AND name_id=? AND deleted=0 AND id<>? LIMIT 1', [$this->_code, $entryId, $nameId, $exceptId ?? 0]);
    }

    public function isPublishedCultural(int $entryId): bool
    {
        $row = $this->_db->fetchOne('SELECT type,published FROM etymolog_entry WHERE franchise_code=? AND id=? AND deleted=0', [$this->_code, $entryId]);
        return $row && (bool)$row['published'] && in_array($row['type'], ResourceRegistry::CULTURAL_TYPES, true);
    }

    public function hasPublishedEvidenceUse(int $sourceId): bool
    {
        if ($this->_db->fetchOne('SELECT id FROM etymolog_calendar_day WHERE franchise_code=? AND source_id=? AND published=1 AND deleted=0 LIMIT 1', [$this->_code, $sourceId])) {return true;}
        $rows = $this->_db->fetchAll('SELECT e.type FROM etymolog_citation c JOIN etymolog_entry e ON e.franchise_code=c.franchise_code AND e.id=c.entry_id WHERE c.franchise_code=? AND c.source_id=? AND c.deleted=0 AND e.deleted=0 AND e.published=1', [$this->_code, $sourceId]);
        return (bool)array_intersect(array_column($rows, 'type'), ResourceRegistry::CULTURAL_TYPES);
    }

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

    public function hasCitation(int $entryId): bool
    {
        return (bool)$this->_db->fetchOne('SELECT c.id FROM etymolog_citation c JOIN etymolog_source s ON s.id=c.source_id AND s.franchise_code=c.franchise_code AND s.deleted=0 WHERE c.franchise_code=? AND c.entry_id=? AND c.deleted=0 LIMIT 1', [$this->_code, $entryId]);
    }
}
