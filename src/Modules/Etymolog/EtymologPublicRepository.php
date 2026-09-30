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

    /** Fixed daily overview; only published Czech Gregorian name days with active sources. */
    public function today(\DateTimeImmutable $date): array
    {
        $items = $this->db->fetchAll("SELECT DISTINCT n.id AS name_id,n.name,d.source_url,s.title AS source_title,s.url AS source_fallback_url,c.title AS calendar_title
            FROM etymolog_calendar_day d
            JOIN etymolog_name n ON n.id=d.name_id AND n.franchise_code=d.franchise_code AND n.deleted=0 AND n.published=1
            JOIN etymolog_calendar c ON c.id=d.calendar_id AND c.franchise_code=d.franchise_code AND c.deleted=0
            JOIN etymolog_source s ON s.id=d.source_id AND s.franchise_code=d.franchise_code AND s.deleted=0
            WHERE d.franchise_code=? AND d.deleted=0 AND d.published=1 AND d.kind='name_day' AND d.date_kind='fixed'
            AND c.country_code='CZ' AND c.system='gregorian' AND d.month=? AND d.day=?
            AND (c.year_from IS NULL OR c.year_from<=?) AND (c.year_to IS NULL OR c.year_to>=?)
            ORDER BY n.name,n.id", [$this->tenant, (int)$date->format('n'), (int)$date->format('j'), (int)$date->format('Y'), (int)$date->format('Y')]);
        $proverb = $this->datedProverb($date) ?? $this->namedayProverb($date) ?? $this->upcomingProverb($date);
        if ($proverb !== null && !isset($proverb['date'])) { $proverb['date'] = $date->format('Y-m-d'); }
        return ['date' => $date->format('Y-m-d'), 'timezone' => 'Europe/Prague', 'items' => $items, 'proverb' => $proverb];
    }


    /** Prefer a sourced proverb attached to this exact Czech calendar date. */
    private function datedProverb(\DateTimeImmutable $date): ?array
    {
        return $this->db->fetchOne("SELECT e.body,COALESCE(NULLIF(e.source_url,''),NULLIF(d.source_url,''),s.url) AS source_url,s.title AS source_title,NULL AS name_id
            FROM etymolog_calendar_day d
            JOIN etymolog_entry e ON e.id=d.entry_id AND e.franchise_code=d.franchise_code AND e.deleted=0 AND e.published=1 AND e.type='proverb' AND TRIM(e.body)<>''
            JOIN etymolog_calendar c ON c.id=d.calendar_id AND c.franchise_code=d.franchise_code AND c.deleted=0
            JOIN etymolog_source s ON s.id=d.source_id AND s.franchise_code=d.franchise_code AND s.deleted=0
            WHERE d.franchise_code=? AND d.deleted=0 AND d.published=1 AND d.date_kind='fixed' AND d.month=? AND d.day=?
            AND c.country_code='CZ' AND c.system='gregorian'
            AND (c.year_from IS NULL OR c.year_from<=?) AND (c.year_to IS NULL OR c.year_to>=?)
            ORDER BY d.id LIMIT 1", [$this->tenant, (int)$date->format('n'), (int)$date->format('j'), (int)$date->format('Y'), (int)$date->format('Y')]) ?: null;
    }

    /** Select the next published proverb, whether attached to a date or a coming name day. */
    private function upcomingProverb(\DateTimeImmutable $date): ?array
    {
        $dated = $this->upcomingDatedProverb($date);
        $named = $this->upcomingNamedayProverb($date);
        if ($dated === null) { return $named; }
        if ($named === null || $dated['date'] <= $named['date']) { return $dated; }
        return $named;
    }

    private function upcomingDatedProverb(\DateTimeImmutable $date): ?array
    {
        $rows = $this->db->fetchAll("SELECT d.id,d.month,d.day,c.year_from,c.year_to,e.body,
                COALESCE(NULLIF(e.source_url,''),NULLIF(d.source_url,''),s.url) AS source_url,s.title AS source_title,NULL AS name_id
            FROM etymolog_calendar_day d
            JOIN etymolog_entry e ON e.id=d.entry_id AND e.franchise_code=d.franchise_code AND e.deleted=0 AND e.published=1 AND e.type='proverb' AND TRIM(e.body)<>''
            JOIN etymolog_calendar c ON c.id=d.calendar_id AND c.franchise_code=d.franchise_code AND c.deleted=0
            JOIN etymolog_source s ON s.id=d.source_id AND s.franchise_code=d.franchise_code AND s.deleted=0
            WHERE d.franchise_code=? AND d.deleted=0 AND d.published=1 AND d.date_kind='fixed' AND d.entry_id IS NOT NULL
            AND d.month BETWEEN 1 AND 12 AND d.day BETWEEN 1 AND 31
            AND c.country_code='CZ' AND c.system='gregorian' AND (c.year_to IS NULL OR c.year_to>=?)", [$this->tenant, (int)$date->format('Y')]);
        return self::nearestFutureRow($rows, $date);
    }

    private function upcomingNamedayProverb(\DateTimeImmutable $date): ?array
    {
        $best = null;
        foreach ([false, true] as $linked) {
            $entryJoin = $linked
                ? "JOIN etymolog_entry_name link ON link.franchise_code=member.franchise_code AND link.name_id=member.id AND link.deleted=0 AND link.reviewed=1
                   JOIN etymolog_entry e ON e.franchise_code=link.franchise_code AND e.id=link.entry_id"
                : 'JOIN etymolog_entry e ON e.franchise_code=member.franchise_code AND e.name_id=member.id';
            $rows = $this->db->fetchAll("SELECT d.id,d.month,d.day,c.year_from,c.year_to,e.body,
                    COALESCE(NULLIF(citation.url,''),NULLIF(e.source_url,''),s.url) AS source_url,s.title AS source_title,n.id AS name_id
                FROM etymolog_calendar_day d
                JOIN etymolog_name n ON n.id=d.name_id AND n.franchise_code=d.franchise_code AND n.deleted=0 AND n.published=1
                JOIN etymolog_calendar c ON c.id=d.calendar_id AND c.franchise_code=d.franchise_code AND c.deleted=0
                JOIN etymolog_source day_source ON day_source.id=d.source_id AND day_source.franchise_code=d.franchise_code AND day_source.deleted=0
                JOIN etymolog_name member ON member.franchise_code=n.franchise_code AND member.kind=n.kind AND member.normalized_name=n.normalized_name AND member.deleted=0 AND member.published=1
                ".$entryJoin." AND e.deleted=0 AND e.published=1 AND e.type='proverb' AND TRIM(e.body)<>''
                JOIN etymolog_citation citation ON citation.entry_id=e.id AND citation.franchise_code=e.franchise_code AND citation.deleted=0
                JOIN etymolog_source s ON s.id=citation.source_id AND s.franchise_code=citation.franchise_code AND s.deleted=0
                WHERE d.franchise_code=? AND d.deleted=0 AND d.published=1 AND d.kind='name_day' AND d.date_kind='fixed'
                AND d.month BETWEEN 1 AND 12 AND d.day BETWEEN 1 AND 31
                AND c.country_code='CZ' AND c.system='gregorian' AND (c.year_to IS NULL OR c.year_to>=?)", [$this->tenant, (int)$date->format('Y')]);
            $candidate = self::nearestFutureRow($rows, $date);
            if ($candidate !== null && ($best === null || $candidate['date'] < $best['date'])) { $best = $candidate; }
        }
        return $best;
    }

    /** Calendar years can be bounded; February 29 may need the next leap year. */
    private static function nearestFutureRow(array $rows, \DateTimeImmutable $date): ?array
    {
        $year = (int)$date->format('Y');
        $today = $date->format('Y-m-d');
        $todayMonthDay = (int)$date->format('n') * 100 + (int)$date->format('j');
        $best = null;
        $bestDate = null;
        foreach ($rows as $row) {
            $month = (int)$row['month'];
            $day = (int)$row['day'];
            $candidateYear = max($year, (int)($row['year_from'] ?? $year));
            if ($candidateYear === $year && $month * 100 + $day <= $todayMonthDay) { ++$candidateYear; }
            // Eight years cover the longest gap between Gregorian leap days across a non-leap century.
            $lastYear = min(9999, $candidateYear + 8, (int)($row['year_to'] ?? 9999));
            for (; $candidateYear <= $lastYear && !checkdate($month, $day, $candidateYear); ++$candidateYear) {}
            if ($candidateYear > $lastYear) { continue; }
            $candidateDate = sprintf('%04d-%02d-%02d', $candidateYear, $month, $day);
            if ($candidateDate <= $today) { continue; }
            if ($bestDate === null || $candidateDate < $bestDate || ($candidateDate === $bestDate && (int)$row['id'] < (int)$best['id'])) {
                $best = $row;
                $bestDate = $candidateDate;
            }
        }
        if ($best === null) { return null; }
        return ['body'=>$best['body'], 'source_url'=>$best['source_url'], 'source_title'=>$best['source_title'], 'name_id'=>$best['name_id'], 'date'=>$bestDate];
    }

    /** Fall back to a cited proverb belonging to any published name celebrating today. */
    private function namedayProverb(\DateTimeImmutable $date): ?array
    {
        $params = [$this->tenant, (int)$date->format('n'), (int)$date->format('j'), (int)$date->format('Y'), (int)$date->format('Y')];
        foreach ([false, true] as $linked) {
            $entryJoin = $linked
                ? "JOIN etymolog_entry_name link ON link.franchise_code=member.franchise_code AND link.name_id=member.id AND link.deleted=0 AND link.reviewed=1
                   JOIN etymolog_entry e ON e.franchise_code=link.franchise_code AND e.id=link.entry_id"
                : 'JOIN etymolog_entry e ON e.franchise_code=member.franchise_code AND e.name_id=member.id';
            $result = $this->db->fetchOne("SELECT e.body,COALESCE(NULLIF(citation.url,''),NULLIF(e.source_url,''),s.url) AS source_url,s.title AS source_title,n.id AS name_id
            FROM etymolog_calendar_day d
            JOIN etymolog_name n ON n.id=d.name_id AND n.franchise_code=d.franchise_code AND n.deleted=0 AND n.published=1
            JOIN etymolog_calendar c ON c.id=d.calendar_id AND c.franchise_code=d.franchise_code AND c.deleted=0
            JOIN etymolog_source day_source ON day_source.id=d.source_id AND day_source.franchise_code=d.franchise_code AND day_source.deleted=0
            JOIN etymolog_name member ON member.franchise_code=n.franchise_code AND member.kind=n.kind AND member.normalized_name=n.normalized_name AND member.deleted=0 AND member.published=1
            ".$entryJoin." AND e.deleted=0 AND e.published=1 AND e.type='proverb' AND TRIM(e.body)<>''
            JOIN etymolog_citation citation ON citation.entry_id=e.id AND citation.franchise_code=e.franchise_code AND citation.deleted=0
            JOIN etymolog_source s ON s.id=citation.source_id AND s.franchise_code=citation.franchise_code AND s.deleted=0
            WHERE d.franchise_code=? AND d.deleted=0 AND d.published=1 AND d.kind='name_day' AND d.date_kind='fixed'
            AND d.month=? AND d.day=? AND c.country_code='CZ' AND c.system='gregorian'
            AND (c.year_from IS NULL OR c.year_from<=?) AND (c.year_to IS NULL OR c.year_to>=?)
            ORDER BY n.name,n.id,e.id LIMIT 1", $params);
            if ($result) { return $result; }
        }
        return null;
    }

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
