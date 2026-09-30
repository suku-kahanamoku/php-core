<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\Database\Database;

/**
 * Repozitar pouze pro čtení s pevnou veřejnou projekcí.
 *
 * Každý JOIN zůstává uvnitř vybraného okurku, takže i agregace a existuje
 * poddotazy nemohou přes hranici okurku. Zveřejná projekce vrací jen sloupce,
 * které se smí zobrazit na webu.
 */
final class EtymologPublicRepository
{
    /**
     * @param  Database $db     Databazove pripojeni.
     * @param  string   $tenant Kod okurku; vsechny dotazy jsou jim omezene.
     * @return void
     */
    public function __construct(private readonly Database $db, private readonly string $tenant) {}

    /**
     * Vyhledá zveřejněná jména a seskupí varianty pravopisu.
     *
     * @param  string $filter JSON filtr, který se převede na SQL přes `SQL_FILTER()`.
     * @param  int    $page   Číslo stránky (20 položek na stránku).
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int} Stránka výsledků.
     */
    public function search(string $filter, int $page): array
    {
        $f = SQL_FILTER($filter);
        $where  = 'franchise_code=? AND deleted=0 AND published=1';
        $params = [$this->tenant];
        if ($f['sql'] !== '') {
            $where .= ' AND '.$f['sql'];
            array_push($params, ...$f['params']);
        }

        // Group spelling within each name kind, independently of source/country.
        // Binary LOWER keeps diacritics significant for identity; LIKE still permits unaccented search.
        $grouped = 'SELECT COALESCE(MIN(CASE WHEN BINARY name <> BINARY UPPER(name) THEN id END),MIN(id)) id,
            kind,
            CASE WHEN COUNT(DISTINCT language)=1 THEN MAX(language) END language,
            CASE WHEN COUNT(DISTINCT country_code)=1 THEN MAX(country_code) END country_code
            FROM etymolog_name WHERE '.$where.' GROUP BY kind,normalized_name';
        $total = (int)$this->db->fetchOne('SELECT COUNT(*) n FROM ('.$grouped.') grouped_names', $params)['n'];
        $offset = ($page - 1) * 20;
        $items = $this->db->fetchAll('SELECT n.id,n.name,g.kind,g.language,g.country_code,n.summary FROM ('.$grouped.') g JOIN etymolog_name n ON n.id=g.id ORDER BY n.name,n.id LIMIT 20 OFFSET '.$offset, $params);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => 20];
    }

    /**
     * Načte detail jména a všechny související záznamy včetně zdrojů.
     *
     * Zahrnuje výklady, citace, varianty pravopisu, historické výskyty, kalendářní
     * dny a deduplikované zdroje. Jazyk a země jsou vráceny jen tehdy, když
     * všechny varianty jména mají stejnou hodnotu.
     *
     * @param  int $id ID jména.
     * @return array<string, mixed>|null Kompletní detail, nebo null pokud jméno není zveřejněné.
     */
    public function detail(int $id): ?array
    {
        $name = $this->db->fetchOne('SELECT id,name,kind,language,country_code,summary FROM etymolog_name WHERE id=? AND franchise_code=? AND deleted=0 AND published=1', [$id, $this->tenant]);
        if (!$name) { return null; }
        $members = $this->db->fetchAll('SELECT id,name,kind,language,country_code,summary FROM etymolog_name WHERE franchise_code=? AND deleted=0 AND published=1 AND kind=? AND normalized_name=LOWER(TRIM(?)) ORDER BY (BINARY name=BINARY UPPER(name)),id', [$this->tenant, $name['kind'], $name['name']]);
        $name = $members[0];
        foreach (['language', 'country_code'] as $field) {
            $values = array_values(array_unique(array_filter(array_column($members, $field), static fn ($value) => $value !== null)));
            $name[$field] = count($values) === 1 ? $values[0] : null;
        }
        $nameIds = array_column($members, 'id');
        $nameMarks = implode(',', array_fill(0, count($nameIds), '?'));
        $entries = $this->db->fetchAll('SELECT e.id,e.type,e.title,e.body,e.source_url,e.certainty,e.language,e.region,e.year_from,e.year_to FROM etymolog_entry e WHERE e.franchise_code=? AND e.deleted=0 AND e.published=1 AND (e.name_id IN ('.$nameMarks.') OR EXISTS (SELECT 1 FROM etymolog_entry_name n WHERE n.entry_id=e.id AND n.franchise_code=e.franchise_code AND n.name_id IN ('.$nameMarks.') AND n.deleted=0 AND n.reviewed=1)) ORDER BY e.year_from,e.id', [$this->tenant, ...$nameIds, ...$nameIds]);
        $entryIds = array_column($entries, 'id');
        $citations = [];
        if ($entryIds) {
            $marks = implode(',', array_fill(0, count($entryIds), '?'));
            $citations = $this->db->fetchAll('SELECT c.id,c.entry_id,c.source_id,c.url,c.locator,c.quotation FROM etymolog_citation c JOIN etymolog_source s ON s.id=c.source_id AND s.franchise_code=c.franchise_code AND s.deleted=0 WHERE c.franchise_code=? AND c.deleted=0 AND c.entry_id IN ('.$marks.') ORDER BY c.id', [$this->tenant, ...$entryIds]);
        }
        $variants = $this->db->fetchAll('SELECT v.id,v.variant,v.relation,v.language,v.region,v.year_from,v.year_to,v.source_id,n.id AS target_name_id FROM etymolog_variant v LEFT JOIN etymolog_name n ON n.id=v.target_name_id AND n.franchise_code=v.franchise_code AND n.deleted=0 AND n.published=1 WHERE v.franchise_code=? AND v.name_id IN ('.$nameMarks.') AND v.deleted=0 ORDER BY v.variant,v.id', [$this->tenant, ...$nameIds]);
        $occurrences = $this->db->fetchAll('SELECT o.id,o.source_id,o.country_code,o.region,o.observed_year,o.observed_on,o.sex,o.measure,o.count,o.original_spelling,o.locator FROM etymolog_occurrence o JOIN etymolog_source s ON s.id=o.source_id AND s.franchise_code=o.franchise_code AND s.deleted=0 WHERE o.franchise_code=? AND o.name_id IN ('.$nameMarks.') AND o.deleted=0 ORDER BY o.observed_year DESC,o.id', [$this->tenant, ...$nameIds]);
        $entryCondition = $entryIds ? ' OR d.entry_id IN ('.implode(',', array_fill(0, count($entryIds), '?')).')' : '';
        $days = $this->db->fetchAll('SELECT d.id,d.source_id,d.title,d.kind,d.date_kind,d.month,d.day,d.date_rule,d.source_url,d.locator,c.title AS calendar_title,c.country_code,c.system,c.tradition,c.region,c.year_from,c.year_to FROM etymolog_calendar_day d JOIN etymolog_calendar c ON c.id=d.calendar_id AND c.franchise_code=d.franchise_code AND c.deleted=0 JOIN etymolog_source s ON s.id=d.source_id AND s.franchise_code=d.franchise_code AND s.deleted=0 WHERE d.franchise_code=? AND d.deleted=0 AND d.published=1 AND (d.name_id IN ('.$nameMarks.')'.$entryCondition.') ORDER BY d.month,d.day,d.id', [$this->tenant, ...$nameIds, ...$entryIds]);
        $sourceIds = array_values(array_unique(array_filter(array_merge(array_column($citations, 'source_id'), array_column($variants, 'source_id'), array_column($occurrences, 'source_id'), array_column($days, 'source_id')))));
        $sources = $sourceIds ? $this->db->fetchAll('SELECT id,title,author,url,license,license_url,attribution FROM etymolog_source WHERE franchise_code=? AND deleted=0 AND id IN ('.implode(',', array_fill(0, count($sourceIds), '?')).') ORDER BY title,id', [$this->tenant, ...$sourceIds]) : [];
        return ['name' => $name, 'entries' => $entries, 'citations' => $citations, 'variants' => $variants, 'occurrences' => $occurrences, 'calendar_days' => $days, 'sources' => $sources];
    }
}
