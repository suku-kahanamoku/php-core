-- Read-only preview for the planned duplicate-name cleanup (MySQL 8).
-- Includes every member: published, draft and soft-deleted. No winner is selected.
-- Same spelling as given name vs surname is NOT a duplicate; accents are significant.
SET NAMES utf8mb4;
SET @ety_cleanup_tenant = 'etymolog';

-- 1. Exact name IDs that belong to duplicate groups.
WITH candidates AS (
    SELECT id,name,kind,language,country_code,published,deleted,
           COUNT(*) OVER (PARTITION BY kind,BINARY LOWER(TRIM(name))) AS group_size
    FROM etymolog_name WHERE franchise_code=@ety_cleanup_tenant
)
SELECT id,name,kind,language,country_code,published,deleted,group_size
FROM candidates WHERE group_size>1
ORDER BY kind,BINARY LOWER(TRIM(name)),id;

-- 2. Related entries, including stories shared with names outside the duplicate groups.
WITH candidates AS (
    SELECT id,COUNT(*) OVER (PARTITION BY kind,BINARY LOWER(TRIM(name))) AS group_size
    FROM etymolog_name WHERE franchise_code=@ety_cleanup_tenant
), duplicate_names AS (
    SELECT id FROM candidates WHERE group_size>1
)
SELECT e.id,e.title,e.type,e.published,e.deleted,
       ((e.name_id IS NOT NULL AND e.name_id NOT IN (SELECT id FROM duplicate_names))
        OR EXISTS (
            SELECT 1 FROM etymolog_entry_name l
            WHERE l.franchise_code=e.franchise_code AND l.entry_id=e.id
              AND l.name_id NOT IN (SELECT id FROM duplicate_names)
        )) AS shared_with_other_names
FROM etymolog_entry e
WHERE e.franchise_code=@ety_cleanup_tenant
AND (e.name_id IN (SELECT id FROM duplicate_names) OR EXISTS (
    SELECT 1 FROM etymolog_entry_name l
    WHERE l.franchise_code=e.franchise_code AND l.entry_id=e.id
      AND l.name_id IN (SELECT id FROM duplicate_names)
))
ORDER BY shared_with_other_names DESC,e.id;

-- 3. Direct references requiring a decision before names can be physically deleted.
WITH candidates AS (
    SELECT id,COUNT(*) OVER (PARTITION BY kind,BINARY LOWER(TRIM(name))) AS group_size
    FROM etymolog_name WHERE franchise_code=@ety_cleanup_tenant
), duplicate_names AS (
    SELECT id FROM candidates WHERE group_size>1
)
SELECT 'names' AS resource,COUNT(*) AS records FROM duplicate_names
UNION ALL
SELECT 'primary_entries',COUNT(*) FROM etymolog_entry WHERE franchise_code=@ety_cleanup_tenant AND name_id IN (SELECT id FROM duplicate_names)
UNION ALL
SELECT 'story_links',COUNT(*) FROM etymolog_entry_name WHERE franchise_code=@ety_cleanup_tenant AND name_id IN (SELECT id FROM duplicate_names)
UNION ALL
SELECT 'occurrences',COUNT(*) FROM etymolog_occurrence WHERE franchise_code=@ety_cleanup_tenant AND name_id IN (SELECT id FROM duplicate_names)
UNION ALL
SELECT 'calendar_days',COUNT(*) FROM etymolog_calendar_day WHERE franchise_code=@ety_cleanup_tenant AND name_id IN (SELECT id FROM duplicate_names)
UNION ALL
SELECT 'owned_variants',COUNT(*) FROM etymolog_variant WHERE franchise_code=@ety_cleanup_tenant AND name_id IN (SELECT id FROM duplicate_names)
UNION ALL
SELECT 'incoming_variant_links',COUNT(*) FROM etymolog_variant WHERE franchise_code=@ety_cleanup_tenant AND target_name_id IN (SELECT id FROM duplicate_names)
UNION ALL
SELECT 'wikidata_snapshots',COUNT(*) FROM etymolog_import_record WHERE franchise_code=@ety_cleanup_tenant AND name_id IN (SELECT id FROM duplicate_names)
UNION ALL
SELECT 'external_snapshots',COUNT(*) FROM etymolog_external_record WHERE franchise_code=@ety_cleanup_tenant AND name_id IN (SELECT id FROM duplicate_names);
