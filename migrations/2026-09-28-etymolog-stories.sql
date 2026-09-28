-- Additive extension; apply after 2026-09-27-etymolog.sql. Safe to repeat.
SET NAMES utf8mb4;
-- Existing primary name links stay intact; shared stories may have no primary name.
ALTER TABLE etymolog_entry MODIFY COLUMN name_id INT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS etymolog_entry_name (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 franchise_code VARCHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 deleted TINYINT(1) NOT NULL DEFAULT 0,
 created_by INT UNSIGNED NULL,
 updated_by INT UNSIGNED NULL,
 entry_id INT UNSIGNED NOT NULL,
 name_id INT UNSIGNED NOT NULL,
 relation VARCHAR(32) NOT NULL DEFAULT 'mentioned',
 reviewed TINYINT(1) NOT NULL DEFAULT 0,
 notes TEXT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_ety_entry_name_tenant_id (franchise_code,id),
 KEY idx_ety_entry_name_entry (franchise_code,entry_id,deleted),
 KEY idx_ety_entry_name_name (franchise_code,name_id,deleted,reviewed),
 CONSTRAINT fk_ety_link_entry FOREIGN KEY (franchise_code,entry_id) REFERENCES etymolog_entry(franchise_code,id),
 CONSTRAINT fk_ety_link_name FOREIGN KEY (franchise_code,name_id) REFERENCES etymolog_name(franchise_code,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS etymolog_story_import (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 franchise_code VARCHAR(64) NOT NULL,
 entry_id INT UNSIGNED NOT NULL,
 source_id INT UNSIGNED NOT NULL,
 provider VARCHAR(32) NOT NULL,
 external_id VARCHAR(100) NOT NULL,
 revision VARCHAR(32) NOT NULL,
 source_url VARCHAR(2048) NOT NULL,
 license VARCHAR(100) NOT NULL,
 license_url VARCHAR(2048) NOT NULL,
 attribution VARCHAR(255) NOT NULL,
 payload JSON NOT NULL,
 content_hash CHAR(64) NOT NULL,
 fetched_at DATETIME NOT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_ety_story_external (franchise_code,provider,external_id),
 KEY idx_ety_story_entry (franchise_code,entry_id),
 CONSTRAINT fk_ety_story_entry FOREIGN KEY (franchise_code,entry_id) REFERENCES etymolog_entry(franchise_code,id),
 CONSTRAINT fk_ety_story_source FOREIGN KEY (franchise_code,source_id) REFERENCES etymolog_source(franchise_code,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
