-- Apply after etymolog-sources.sql; additive and repeatable.
SET NAMES utf8mb4;
SET @ety_sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='source_url')=0,
 'ALTER TABLE etymolog_entry ADD COLUMN source_url VARCHAR(2048) NULL','SELECT 1');
PREPARE ety_stmt FROM @ety_sql; EXECUTE ety_stmt; DEALLOCATE PREPARE ety_stmt;

CREATE TABLE IF NOT EXISTS etymolog_calendar (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 franchise_code VARCHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 deleted TINYINT(1) NOT NULL DEFAULT 0,
 created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL,
 import_key VARCHAR(100) NULL,
 title VARCHAR(255) NOT NULL, country_code CHAR(2) NOT NULL,
 `system` VARCHAR(16) NOT NULL DEFAULT 'gregorian', tradition VARCHAR(255) NOT NULL,
 region VARCHAR(255) NULL, year_from INT NULL, year_to INT NULL, notes TEXT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_ety_calendar_tenant_id(franchise_code,id),
 UNIQUE KEY uq_ety_calendar_import(franchise_code,import_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE IF NOT EXISTS etymolog_calendar_day (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 franchise_code VARCHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 deleted TINYINT(1) NOT NULL DEFAULT 0,
 created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL,
 import_key VARCHAR(100) NULL,
 calendar_id INT UNSIGNED NOT NULL, source_id INT UNSIGNED NOT NULL,
 name_id INT UNSIGNED NULL, entry_id INT UNSIGNED NULL,
 title VARCHAR(255) NOT NULL, kind VARCHAR(32) NOT NULL DEFAULT 'name_day',
 date_kind VARCHAR(16) NOT NULL DEFAULT 'fixed', month TINYINT UNSIGNED NULL, day TINYINT UNSIGNED NULL,
 date_rule VARCHAR(1000) NULL, source_url VARCHAR(2048) NOT NULL, locator VARCHAR(1000) NULL,
 notes TEXT NULL, published TINYINT(1) NOT NULL DEFAULT 0,
 PRIMARY KEY(id), UNIQUE KEY uq_ety_calendar_day_tenant_id(franchise_code,id),
 UNIQUE KEY uq_ety_calendar_day_import(franchise_code,import_key),
 KEY idx_ety_calendar_date(franchise_code,calendar_id,month,day,deleted),
 CONSTRAINT fk_ety_calendar_day_calendar FOREIGN KEY(franchise_code,calendar_id) REFERENCES etymolog_calendar(franchise_code,id),
 CONSTRAINT fk_ety_calendar_day_source FOREIGN KEY(franchise_code,source_id) REFERENCES etymolog_source(franchise_code,id),
 CONSTRAINT fk_ety_calendar_day_name FOREIGN KEY(franchise_code,name_id) REFERENCES etymolog_name(franchise_code,id),
 CONSTRAINT fk_ety_calendar_day_entry FOREIGN KEY(franchise_code,entry_id) REFERENCES etymolog_entry(franchise_code,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
ALTER TABLE etymolog_external_record MODIFY COLUMN name_id INT UNSIGNED NULL;
SET @ety_sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='calendar_day_id')=0,
 'ALTER TABLE etymolog_external_record ADD COLUMN calendar_day_id INT UNSIGNED NULL, ADD CONSTRAINT fk_ety_external_calendar_day FOREIGN KEY(franchise_code,calendar_day_id) REFERENCES etymolog_calendar_day(franchise_code,id)','SELECT 1');
PREPARE ety_stmt FROM @ety_sql; EXECUTE ety_stmt; DEALLOCATE PREPARE ety_stmt;
-- Backfill only provenance actually stored by the existing importer; never invent a source.
UPDATE etymolog_entry e JOIN etymolog_story_import i ON i.franchise_code=e.franchise_code AND i.entry_id=e.id
 SET e.source_url=i.source_url WHERE e.source_url IS NULL;
UPDATE etymolog_citation c JOIN etymolog_story_import i ON i.franchise_code=c.franchise_code AND i.entry_id=c.entry_id AND i.source_id=c.source_id AND i.source_url=c.url
 SET c.quotation=JSON_UNQUOTE(JSON_EXTRACT(i.payload,'$.body'))
 WHERE c.quotation IS NULL AND JSON_TYPE(JSON_EXTRACT(i.payload,'$.body'))='STRING';
