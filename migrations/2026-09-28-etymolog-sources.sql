-- Additive source extension; apply after both preceding Etymolog schema migrations.
SET NAMES utf8mb4;
ALTER TABLE etymolog_sync_job MODIFY COLUMN `cursor` VARCHAR(2048) NULL;

SET @ety_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='import_key')=0,
 'ALTER TABLE etymolog_source ADD COLUMN import_key VARCHAR(100) NULL', 'SELECT 1');
PREPARE ety_stmt FROM @ety_sql; EXECUTE ety_stmt; DEALLOCATE PREPARE ety_stmt;

SET @ety_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='observed_on')=0,
 'ALTER TABLE etymolog_occurrence ADD COLUMN observed_on DATE NULL', 'SELECT 1');
PREPARE ety_stmt FROM @ety_sql; EXECUTE ety_stmt; DEALLOCATE PREPARE ety_stmt;

SET @ety_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='sex')=0,
 'ALTER TABLE etymolog_occurrence ADD COLUMN sex VARCHAR(10) NULL', 'SELECT 1');
PREPARE ety_stmt FROM @ety_sql; EXECUTE ety_stmt; DEALLOCATE PREPARE ety_stmt;

SET @ety_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='measure')=0,
 'ALTER TABLE etymolog_occurrence ADD COLUMN measure VARCHAR(32) NULL', 'SELECT 1');
PREPARE ety_stmt FROM @ety_sql; EXECUTE ety_stmt; DEALLOCATE PREPARE ety_stmt;

SET @ety_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND INDEX_NAME='uq_ety_source_import')=0,
 'ALTER TABLE etymolog_source ADD UNIQUE KEY uq_ety_source_import (franchise_code,import_key)', 'SELECT 1');
PREPARE ety_stmt FROM @ety_sql; EXECUTE ety_stmt; DEALLOCATE PREPARE ety_stmt;

CREATE TABLE IF NOT EXISTS etymolog_external_record (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 franchise_code VARCHAR(64) NOT NULL,
 provider VARCHAR(32) NOT NULL,
 external_id VARCHAR(255) NOT NULL,
 name_id INT UNSIGNED NOT NULL,
 source_id INT UNSIGNED NOT NULL,
 entry_id INT UNSIGNED NULL,
 occurrence_id INT UNSIGNED NULL,
 revision VARCHAR(100) NOT NULL,
 source_url VARCHAR(2048) NOT NULL,
 license VARCHAR(100) NOT NULL,
 license_url VARCHAR(2048) NOT NULL,
 attribution TEXT NOT NULL,
 payload JSON NOT NULL,
 content_hash CHAR(64) NOT NULL,
 fetched_at DATETIME NOT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_ety_external (franchise_code,provider,external_id),
 CONSTRAINT fk_ety_external_name FOREIGN KEY (franchise_code,name_id) REFERENCES etymolog_name(franchise_code,id),
 CONSTRAINT fk_ety_external_source FOREIGN KEY (franchise_code,source_id) REFERENCES etymolog_source(franchise_code,id),
 CONSTRAINT fk_ety_external_entry FOREIGN KEY (franchise_code,entry_id) REFERENCES etymolog_entry(franchise_code,id),
 CONSTRAINT fk_ety_external_occurrence FOREIGN KEY (franchise_code,occurrence_id) REFERENCES etymolog_occurrence(franchise_code,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
