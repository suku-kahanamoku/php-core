-- Jednorazovy uplny reset OBSAHU + prechod na CZ pouze tenantu etymolog.
-- Po dokonceni aplikovat etymolog_seed.sql, potom spustit synchronizaci.
-- Maze i rucni obsah, koncepty a logicky smazane zaznamy. Sam nic neimportuje.
-- Zachova uzivatele, role, ceske definice sync_job a ostatni tenanty.
-- Smaze i vsechny prameny/licence: cisty import je vytvori znovu.
-- Zachova sync_schedule: ochrana proti druhemu automatickemu spusteni v temze dni.
-- Spustit cely soubor v jednom samostatnem spojeni, bez jiz otevrene transakce.
-- Pri SQL chybe NEPOKRACOVAT (nepouzivat mysql --force), provest ROLLBACK
-- a ukoncit spojeni, cimz se uvolni i pojmenovane zamky.
SET NAMES utf8mb4;
SET @ety_reset_tenant = 'etymolog';
SET @ety_reset_worker_key = CONCAT('ety-worker:', LEFT(SHA2(@ety_reset_tenant, 256), 48));
SET @ety_reset_request_key = CONCAT('ety-request:', LEFT(SHA2(@ety_reset_tenant, 256), 48));
SET @ety_reset_domain_key = CONCAT('etymolog:', LEFT(SHA2(@ety_reset_tenant, 256), 48));

-- Stejne zamky a poradi jako aplikace. Pri soubehu nedojde k zadne zmene.
SET @ety_reset_worker = GET_LOCK(@ety_reset_worker_key, 0);
SET @ety_reset_request = IF(@ety_reset_worker = 1, GET_LOCK(@ety_reset_request_key, 0), 0);
SET @ety_reset_domain = IF(@ety_reset_request = 1, GET_LOCK(@ety_reset_domain_key, 0), 0);
START TRANSACTION;
SET @ety_reset_allowed = IF(
    @ety_reset_worker = 1 AND @ety_reset_request = 1 AND @ety_reset_domain = 1
    AND NOT EXISTS (SELECT 1 FROM etymolog_sync_batch
        WHERE franchise_code = @ety_reset_tenant AND status IN ('queued', 'running')), 1, 0);
SET @ety_reset_deleted = 0;

-- Deti pred rodici. Cizi klice zustavaji zapnute a vsechny zmeny jsou v transakci.
DELETE FROM etymolog_external_record
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_import_record
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_story_import
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_citation
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_calendar_day
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_entry_name
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_variant
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_occurrence
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_entry
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_name
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_calendar
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_sync_run
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

DELETE FROM etymolog_sync_batch
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

-- Po odstraneni vsech zavislych importu/citaci lze odstranit i prameny.
DELETE FROM etymolog_source
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

-- Zrusit cizi a historicke duplicitni ulohy, ne jen deaktivovat.
DELETE FROM etymolog_sync_job
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1
  AND NOT (language = 'cs' AND (
    (provider IN ('wikidata','wiktionary-cs') AND kind IN ('given','surname'))
    OR (provider = 'wiktionary' AND kind = 'surname')
    OR (provider = 'wikipedia-names' AND kind IN ('etymologies','culture'))
    OR (provider = 'wikisource' AND kind = 'stories')
    OR (provider = 'erben-folklore' AND kind = 'folklore')
    OR (provider = 'czech-namedays' AND kind = 'calendar')
    OR (provider = 'csu-baby-names' AND kind = 'births_2025')
  ));
SET @ety_reset_deleted = @ety_reset_deleted + ROW_COUNT();

-- Restart pouze ponechanych ceskych uloh. Nastaveni a intervaly zachovat.
UPDATE etymolog_sync_job
SET `cursor` = NULL, next_run_at = NULL, last_status = NULL, last_error = NULL,
    updated_at = updated_at
WHERE franchise_code = @ety_reset_tenant AND @ety_reset_allowed = 1;
SET @ety_reset_jobs = ROW_COUNT();
COMMIT;

DO IF(@ety_reset_domain = 1, RELEASE_LOCK(@ety_reset_domain_key), 0);
DO IF(@ety_reset_request = 1, RELEASE_LOCK(@ety_reset_request_key), 0);
DO IF(@ety_reset_worker = 1, RELEASE_LOCK(@ety_reset_worker_key), 0);

SELECT IF(@ety_reset_allowed = 1, 'RESET', 'SKIPPED_BUSY') AS result,
       @ety_reset_tenant AS franchise_code,
       @ety_reset_deleted AS deleted_rows,
       @ety_reset_jobs AS reset_jobs;
