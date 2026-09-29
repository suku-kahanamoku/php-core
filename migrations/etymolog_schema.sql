-- etymolog_schema: canonical definitions. Apply schema.sql before product schemas.
-- Run python3 scripts/build-schemas.py after editing CREATE definitions.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `etymolog_calendar` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `import_key` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `country_code` char(2) COLLATE utf8mb4_bin NOT NULL,
  `system` varchar(16) COLLATE utf8mb4_bin NOT NULL DEFAULT 'gregorian',
  `tradition` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `region` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `year_from` int DEFAULT NULL,
  `year_to` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ety_calendar_tenant_id` (`franchise_code`,`id`),
  UNIQUE KEY `uq_ety_calendar_import` (`franchise_code`,`import_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_calendar_day` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `import_key` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL,
  `calendar_id` int unsigned NOT NULL,
  `source_id` int unsigned NOT NULL,
  `name_id` int unsigned DEFAULT NULL,
  `entry_id` int unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `kind` varchar(32) COLLATE utf8mb4_bin NOT NULL DEFAULT 'name_day',
  `date_kind` varchar(16) COLLATE utf8mb4_bin NOT NULL DEFAULT 'fixed',
  `month` tinyint unsigned DEFAULT NULL,
  `day` tinyint unsigned DEFAULT NULL,
  `date_rule` varchar(1000) COLLATE utf8mb4_bin DEFAULT NULL,
  `source_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL,
  `locator` varchar(1000) COLLATE utf8mb4_bin DEFAULT NULL,
  `notes` text COLLATE utf8mb4_bin,
  `published` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ety_calendar_day_tenant_id` (`franchise_code`,`id`),
  UNIQUE KEY `uq_ety_calendar_day_import` (`franchise_code`,`import_key`),
  KEY `idx_ety_calendar_date` (`franchise_code`,`calendar_id`,`month`,`day`,`deleted`),
  KEY `fk_ety_calendar_day_source` (`franchise_code`,`source_id`),
  KEY `fk_ety_calendar_day_name` (`franchise_code`,`name_id`),
  KEY `fk_ety_calendar_day_entry` (`franchise_code`,`entry_id`)
  -- deferred CONSTRAINT `fk_ety_calendar_day_calendar` FOREIGN KEY (`franchise_code`, `calendar_id`) REFERENCES `etymolog_calendar` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_calendar_day_entry` FOREIGN KEY (`franchise_code`, `entry_id`) REFERENCES `etymolog_entry` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_calendar_day_name` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_calendar_day_source` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_citation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `entry_id` int unsigned NOT NULL,
  `source_id` int unsigned NOT NULL,
  `url` varchar(2048) COLLATE utf8mb4_bin DEFAULT NULL,
  `locator` varchar(1000) COLLATE utf8mb4_bin DEFAULT NULL,
  `quotation` text COLLATE utf8mb4_bin,
  `notes` text COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etymolog_citation_tenant_id` (`franchise_code`,`id`),
  KEY `idx_etymolog_citation_active` (`franchise_code`,`deleted`),
  KEY `fk_ety_citation_entry_id` (`franchise_code`,`entry_id`),
  KEY `fk_ety_citation_source_id` (`franchise_code`,`source_id`)
  -- deferred CONSTRAINT `fk_ety_citation_entry_id` FOREIGN KEY (`franchise_code`, `entry_id`) REFERENCES `etymolog_entry` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_citation_source_id` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_entry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `name_id` int unsigned DEFAULT NULL,
  `type` varchar(32) COLLATE utf8mb4_bin NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `body` text COLLATE utf8mb4_bin NOT NULL,
  `certainty` varchar(20) COLLATE utf8mb4_bin NOT NULL DEFAULT 'unverified',
  `language` varchar(35) COLLATE utf8mb4_bin NOT NULL DEFAULT 'cs',
  `region` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `year_from` smallint DEFAULT NULL,
  `year_to` smallint DEFAULT NULL,
  `published` tinyint(1) NOT NULL DEFAULT '0',
  `source_url` varchar(2048) COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etymolog_entry_tenant_id` (`franchise_code`,`id`),
  KEY `idx_etymolog_entry_active` (`franchise_code`,`deleted`),
  KEY `fk_ety_entry_name_id` (`franchise_code`,`name_id`)
  -- deferred CONSTRAINT `fk_ety_entry_name_id` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_entry_name` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `entry_id` int unsigned NOT NULL,
  `name_id` int unsigned NOT NULL,
  `relation` varchar(32) COLLATE utf8mb4_bin NOT NULL DEFAULT 'mentioned',
  `reviewed` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ety_entry_name_tenant_id` (`franchise_code`,`id`),
  KEY `idx_ety_entry_name_entry` (`franchise_code`,`entry_id`,`deleted`),
  KEY `idx_ety_entry_name_name` (`franchise_code`,`name_id`,`deleted`,`reviewed`)
  -- deferred CONSTRAINT `fk_ety_link_entry` FOREIGN KEY (`franchise_code`, `entry_id`) REFERENCES `etymolog_entry` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_link_name` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_external_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_bin NOT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `name_id` int unsigned DEFAULT NULL,
  `source_id` int unsigned NOT NULL,
  `entry_id` int unsigned DEFAULT NULL,
  `occurrence_id` int unsigned DEFAULT NULL,
  `revision` varchar(100) COLLATE utf8mb4_bin NOT NULL,
  `source_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL,
  `license` varchar(100) COLLATE utf8mb4_bin NOT NULL,
  `license_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL,
  `attribution` text COLLATE utf8mb4_bin NOT NULL,
  `payload` json NOT NULL,
  `content_hash` char(64) COLLATE utf8mb4_bin NOT NULL,
  `fetched_at` datetime NOT NULL,
  `calendar_day_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ety_external` (`franchise_code`,`provider`,`external_id`),
  KEY `fk_ety_external_name` (`franchise_code`,`name_id`),
  KEY `fk_ety_external_source` (`franchise_code`,`source_id`),
  KEY `fk_ety_external_entry` (`franchise_code`,`entry_id`),
  KEY `fk_ety_external_occurrence` (`franchise_code`,`occurrence_id`),
  KEY `fk_ety_external_calendar_day` (`franchise_code`,`calendar_day_id`)
  -- deferred CONSTRAINT `fk_ety_external_calendar_day` FOREIGN KEY (`franchise_code`, `calendar_day_id`) REFERENCES `etymolog_calendar_day` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_external_entry` FOREIGN KEY (`franchise_code`, `entry_id`) REFERENCES `etymolog_entry` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_external_name` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_external_occurrence` FOREIGN KEY (`franchise_code`, `occurrence_id`) REFERENCES `etymolog_occurrence` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_external_source` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_import_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `name_id` int unsigned NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_bin NOT NULL,
  `external_id` varchar(32) COLLATE utf8mb4_bin NOT NULL,
  `source_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL,
  `license` varchar(100) COLLATE utf8mb4_bin NOT NULL,
  `license_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL,
  `attribution` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `revision` varchar(32) COLLATE utf8mb4_bin NOT NULL,
  `payload` json NOT NULL,
  `content_hash` char(64) COLLATE utf8mb4_bin NOT NULL,
  `fetched_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etymolog_import` (`franchise_code`,`name_id`,`provider`,`external_id`)
  -- deferred CONSTRAINT `fk_ety_import_name` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_name` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` varchar(16) COLLATE utf8mb4_bin NOT NULL,
  `language` varchar(35) COLLATE utf8mb4_bin DEFAULT NULL,
  `country_code` char(2) COLLATE utf8mb4_bin DEFAULT NULL,
  `summary` text COLLATE utf8mb4_bin,
  `published` tinyint(1) NOT NULL DEFAULT '0',
  `import_key` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etymolog_name_tenant_id` (`franchise_code`,`id`),
  UNIQUE KEY `uq_etymolog_name_import` (`franchise_code`,`import_key`),
  KEY `idx_etymolog_name_search` (`franchise_code`,`name`,`kind`),
  KEY `idx_etymolog_name_active` (`franchise_code`,`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_occurrence` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `name_id` int unsigned NOT NULL,
  `source_id` int unsigned NOT NULL,
  `country_code` char(2) COLLATE utf8mb4_bin NOT NULL,
  `region` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `observed_year` smallint NOT NULL,
  `count` int unsigned DEFAULT NULL,
  `original_spelling` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `locator` varchar(1000) COLLATE utf8mb4_bin DEFAULT NULL,
  `notes` text COLLATE utf8mb4_bin,
  `observed_on` date DEFAULT NULL,
  `sex` varchar(10) COLLATE utf8mb4_bin DEFAULT NULL,
  `measure` varchar(32) COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etymolog_occurrence_tenant_id` (`franchise_code`,`id`),
  KEY `idx_etymolog_occurrence_active` (`franchise_code`,`deleted`),
  KEY `fk_ety_occurrence_name_id` (`franchise_code`,`name_id`),
  KEY `fk_ety_occurrence_source_id` (`franchise_code`,`source_id`)
  -- deferred CONSTRAINT `fk_ety_occurrence_name_id` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_occurrence_source_id` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_source` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `author` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `url` varchar(2048) COLLATE utf8mb4_bin DEFAULT NULL,
  `license` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL,
  `license_url` varchar(2048) COLLATE utf8mb4_bin DEFAULT NULL,
  `attribution` text COLLATE utf8mb4_bin,
  `notes` text COLLATE utf8mb4_bin,
  `import_key` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etymolog_source_tenant_id` (`franchise_code`,`id`),
  UNIQUE KEY `uq_ety_source_import` (`franchise_code`,`import_key`),
  KEY `idx_etymolog_source_active` (`franchise_code`,`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_story_import` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `entry_id` int unsigned NOT NULL,
  `source_id` int unsigned NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_bin NOT NULL,
  `external_id` varchar(100) COLLATE utf8mb4_bin NOT NULL,
  `revision` varchar(32) COLLATE utf8mb4_bin NOT NULL,
  `source_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL,
  `license` varchar(100) COLLATE utf8mb4_bin NOT NULL,
  `license_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL,
  `attribution` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `payload` json NOT NULL,
  `content_hash` char(64) COLLATE utf8mb4_bin NOT NULL,
  `fetched_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ety_story_external` (`franchise_code`,`provider`,`external_id`),
  KEY `idx_ety_story_entry` (`franchise_code`,`entry_id`),
  KEY `fk_ety_story_source` (`franchise_code`,`source_id`)
  -- deferred CONSTRAINT `fk_ety_story_entry` FOREIGN KEY (`franchise_code`, `entry_id`) REFERENCES `etymolog_entry` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_story_source` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_sync_batch` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `request_id` char(32) COLLATE utf8mb4_bin NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_bin NOT NULL,
  `requested_by` int unsigned DEFAULT NULL,
  `total` int unsigned NOT NULL DEFAULT '0',
  `completed` int unsigned NOT NULL DEFAULT '0',
  `failed` int unsigned NOT NULL DEFAULT '0',
  `processed` int unsigned NOT NULL DEFAULT '0',
  `error_code` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `heartbeat_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `pending_jobs` json DEFAULT NULL,
  `retry_at` datetime DEFAULT NULL,
  `retry_count` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`franchise_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_sync_job` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_bin NOT NULL DEFAULT 'wikidata',
  `language` varchar(35) COLLATE utf8mb4_bin NOT NULL DEFAULT 'cs',
  `kind` varchar(16) COLLATE utf8mb4_bin NOT NULL DEFAULT 'surname',
  `batch_size` smallint unsigned NOT NULL DEFAULT '20',
  `interval_seconds` int unsigned NOT NULL DEFAULT '3600',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `cursor` varchar(2048) COLLATE utf8mb4_bin DEFAULT NULL,
  `next_run_at` datetime DEFAULT NULL,
  `last_status` varchar(20) COLLATE utf8mb4_bin DEFAULT NULL,
  `last_error` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etymolog_sync_job_tenant_id` (`franchise_code`,`id`),
  KEY `idx_etymolog_job_due` (`franchise_code`,`deleted`,`enabled`,`next_run_at`),
  KEY `idx_etymolog_sync_job_active` (`franchise_code`,`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_sync_run` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `job_id` int unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_bin NOT NULL,
  `processed` int unsigned NOT NULL DEFAULT '0',
  `error_code` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_etymolog_run_job` (`franchise_code`,`job_id`,`id`)
  -- deferred CONSTRAINT `fk_ety_run_job` FOREIGN KEY (`franchise_code`, `job_id`) REFERENCES `etymolog_sync_job` (`franchise_code`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_sync_schedule` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `scheduled_date` date NOT NULL,
  `request_id` char(32) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`franchise_code`,`scheduled_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `etymolog_variant` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `name_id` int unsigned NOT NULL,
  `target_name_id` int unsigned DEFAULT NULL,
  `variant` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `relation` varchar(32) COLLATE utf8mb4_bin NOT NULL DEFAULT 'spelling',
  `language` varchar(35) COLLATE utf8mb4_bin DEFAULT NULL,
  `region` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `year_from` smallint DEFAULT NULL,
  `year_to` smallint DEFAULT NULL,
  `source_id` int unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etymolog_variant_tenant_id` (`franchise_code`,`id`),
  KEY `idx_etymolog_variant_search` (`franchise_code`,`variant`),
  KEY `idx_etymolog_variant_active` (`franchise_code`,`deleted`),
  KEY `fk_ety_variant_name_id` (`franchise_code`,`name_id`),
  KEY `fk_ety_variant_target_name_id` (`franchise_code`,`target_name_id`),
  KEY `fk_ety_variant_source_id` (`franchise_code`,`source_id`)
  -- deferred CONSTRAINT `fk_ety_variant_name_id` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_variant_source_id` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)
  -- deferred CONSTRAINT `fk_ety_variant_target_name_id` FOREIGN KEY (`franchise_code`, `target_name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- BEGIN GENERATED ADDITIVE DDL (scripts/build-schemas.py)
-- Missing columns first, then indexes and foreign keys. Existing data stay intact.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='deleted'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='created_by'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `created_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='updated_by'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `updated_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='import_key'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `import_key` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='title'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `title` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='country_code'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `country_code` char(2) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='system'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `system` varchar(16) COLLATE utf8mb4_bin NOT NULL DEFAULT ''gregorian''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='tradition'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `tradition` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='region'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `region` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='year_from'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `year_from` int DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='year_to'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `year_to` int DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND COLUMN_NAME='notes'), 'ALTER TABLE `etymolog_calendar` ADD COLUMN `notes` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='deleted'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='created_by'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `created_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='updated_by'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `updated_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='import_key'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `import_key` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='calendar_id'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `calendar_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='source_id'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `source_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='name_id'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `name_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='entry_id'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `entry_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='title'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `title` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='kind'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `kind` varchar(32) COLLATE utf8mb4_bin NOT NULL DEFAULT ''name_day''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='date_kind'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `date_kind` varchar(16) COLLATE utf8mb4_bin NOT NULL DEFAULT ''fixed''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='month'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `month` tinyint unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='day'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `day` tinyint unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='date_rule'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `date_rule` varchar(1000) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='source_url'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `source_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='locator'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `locator` varchar(1000) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='notes'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `notes` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND COLUMN_NAME='published'), 'ALTER TABLE `etymolog_calendar_day` ADD COLUMN `published` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='deleted'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='created_by'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `created_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='updated_by'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `updated_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='entry_id'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `entry_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='source_id'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `source_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='url'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `url` varchar(2048) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='locator'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `locator` varchar(1000) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='quotation'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `quotation` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND COLUMN_NAME='notes'), 'ALTER TABLE `etymolog_citation` ADD COLUMN `notes` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='deleted'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='created_by'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `created_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='updated_by'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `updated_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='name_id'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `name_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='type'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `type` varchar(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='title'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `title` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='body'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `body` text COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='certainty'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `certainty` varchar(20) COLLATE utf8mb4_bin NOT NULL DEFAULT ''unverified''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='language'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `language` varchar(35) COLLATE utf8mb4_bin NOT NULL DEFAULT ''cs''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='region'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `region` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='year_from'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `year_from` smallint DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='year_to'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `year_to` smallint DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='published'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `published` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='source_url'), 'ALTER TABLE `etymolog_entry` ADD COLUMN `source_url` varchar(2048) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='deleted'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='created_by'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `created_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='updated_by'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `updated_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='entry_id'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `entry_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='name_id'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `name_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='relation'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `relation` varchar(32) COLLATE utf8mb4_bin NOT NULL DEFAULT ''mentioned''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='reviewed'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `reviewed` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND COLUMN_NAME='notes'), 'ALTER TABLE `etymolog_entry_name` ADD COLUMN `notes` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='provider'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `provider` varchar(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='external_id'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='name_id'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `name_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='source_id'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `source_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='entry_id'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `entry_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='occurrence_id'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `occurrence_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='revision'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `revision` varchar(100) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='source_url'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `source_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='license'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `license` varchar(100) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='license_url'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `license_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='attribution'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `attribution` text COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='payload'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `payload` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='content_hash'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `content_hash` char(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='fetched_at'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `fetched_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='calendar_day_id'), 'ALTER TABLE `etymolog_external_record` ADD COLUMN `calendar_day_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='name_id'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `name_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='provider'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `provider` varchar(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='external_id'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `external_id` varchar(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='source_url'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `source_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='license'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `license` varchar(100) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='license_url'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `license_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='attribution'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `attribution` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='revision'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `revision` varchar(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='payload'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `payload` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='content_hash'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `content_hash` char(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND COLUMN_NAME='fetched_at'), 'ALTER TABLE `etymolog_import_record` ADD COLUMN `fetched_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_name` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_name` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_name` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `etymolog_name` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='deleted'), 'ALTER TABLE `etymolog_name` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='created_by'), 'ALTER TABLE `etymolog_name` ADD COLUMN `created_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='updated_by'), 'ALTER TABLE `etymolog_name` ADD COLUMN `updated_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='name'), 'ALTER TABLE `etymolog_name` ADD COLUMN `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='kind'), 'ALTER TABLE `etymolog_name` ADD COLUMN `kind` varchar(16) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='language'), 'ALTER TABLE `etymolog_name` ADD COLUMN `language` varchar(35) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='country_code'), 'ALTER TABLE `etymolog_name` ADD COLUMN `country_code` char(2) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='summary'), 'ALTER TABLE `etymolog_name` ADD COLUMN `summary` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='published'), 'ALTER TABLE `etymolog_name` ADD COLUMN `published` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND COLUMN_NAME='import_key'), 'ALTER TABLE `etymolog_name` ADD COLUMN `import_key` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='deleted'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='created_by'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `created_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='updated_by'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `updated_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='name_id'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `name_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='source_id'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `source_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='country_code'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `country_code` char(2) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='region'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `region` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='observed_year'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `observed_year` smallint NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='count'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `count` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='original_spelling'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `original_spelling` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='locator'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `locator` varchar(1000) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='notes'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `notes` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='observed_on'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `observed_on` date DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='sex'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `sex` varchar(10) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND COLUMN_NAME='measure'), 'ALTER TABLE `etymolog_occurrence` ADD COLUMN `measure` varchar(32) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_source` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_source` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_source` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `etymolog_source` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='deleted'), 'ALTER TABLE `etymolog_source` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='created_by'), 'ALTER TABLE `etymolog_source` ADD COLUMN `created_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='updated_by'), 'ALTER TABLE `etymolog_source` ADD COLUMN `updated_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='title'), 'ALTER TABLE `etymolog_source` ADD COLUMN `title` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='author'), 'ALTER TABLE `etymolog_source` ADD COLUMN `author` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='url'), 'ALTER TABLE `etymolog_source` ADD COLUMN `url` varchar(2048) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='license'), 'ALTER TABLE `etymolog_source` ADD COLUMN `license` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='license_url'), 'ALTER TABLE `etymolog_source` ADD COLUMN `license_url` varchar(2048) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='attribution'), 'ALTER TABLE `etymolog_source` ADD COLUMN `attribution` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='notes'), 'ALTER TABLE `etymolog_source` ADD COLUMN `notes` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND COLUMN_NAME='import_key'), 'ALTER TABLE `etymolog_source` ADD COLUMN `import_key` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='entry_id'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `entry_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='source_id'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `source_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='provider'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `provider` varchar(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='external_id'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `external_id` varchar(100) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='revision'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `revision` varchar(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='source_url'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `source_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='license'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `license` varchar(100) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='license_url'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `license_url` varchar(2048) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='attribution'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `attribution` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='payload'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `payload` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='content_hash'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `content_hash` char(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND COLUMN_NAME='fetched_at'), 'ALTER TABLE `etymolog_story_import` ADD COLUMN `fetched_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='request_id'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `request_id` char(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='status'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `status` varchar(20) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='requested_by'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `requested_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='total'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `total` int unsigned NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='completed'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `completed` int unsigned NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='failed'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `failed` int unsigned NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='processed'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `processed` int unsigned NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='error_code'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `error_code` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `created_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='heartbeat_at'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `heartbeat_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='finished_at'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `finished_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='pending_jobs'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `pending_jobs` json DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='retry_at'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `retry_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME='retry_count'), 'ALTER TABLE `etymolog_sync_batch` ADD COLUMN `retry_count` tinyint unsigned NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='deleted'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='created_by'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `created_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='updated_by'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `updated_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='title'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `title` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='provider'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `provider` varchar(32) COLLATE utf8mb4_bin NOT NULL DEFAULT ''wikidata''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='language'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `language` varchar(35) COLLATE utf8mb4_bin NOT NULL DEFAULT ''cs''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='kind'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `kind` varchar(16) COLLATE utf8mb4_bin NOT NULL DEFAULT ''surname''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='batch_size'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `batch_size` smallint unsigned NOT NULL DEFAULT ''20''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='interval_seconds'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `interval_seconds` int unsigned NOT NULL DEFAULT ''3600''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='enabled'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `enabled` tinyint(1) NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='cursor'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `cursor` varchar(2048) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='next_run_at'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `next_run_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='last_status'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `last_status` varchar(20) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='last_error'), 'ALTER TABLE `etymolog_sync_job` ADD COLUMN `last_error` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_sync_run` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_sync_run` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND COLUMN_NAME='job_id'), 'ALTER TABLE `etymolog_sync_run` ADD COLUMN `job_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND COLUMN_NAME='status'), 'ALTER TABLE `etymolog_sync_run` ADD COLUMN `status` varchar(20) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND COLUMN_NAME='processed'), 'ALTER TABLE `etymolog_sync_run` ADD COLUMN `processed` int unsigned NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND COLUMN_NAME='error_code'), 'ALTER TABLE `etymolog_sync_run` ADD COLUMN `error_code` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND COLUMN_NAME='started_at'), 'ALTER TABLE `etymolog_sync_run` ADD COLUMN `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND COLUMN_NAME='finished_at'), 'ALTER TABLE `etymolog_sync_run` ADD COLUMN `finished_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_schedule' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_sync_schedule` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_schedule' AND COLUMN_NAME='scheduled_date'), 'ALTER TABLE `etymolog_sync_schedule` ADD COLUMN `scheduled_date` date NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_schedule' AND COLUMN_NAME='request_id'), 'ALTER TABLE `etymolog_sync_schedule` ADD COLUMN `request_id` char(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_schedule' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_sync_schedule` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='id'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='created_at'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='deleted'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='created_by'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `created_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='updated_by'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `updated_by` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='name_id'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `name_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='target_name_id'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `target_name_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='variant'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `variant` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='relation'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `relation` varchar(32) COLLATE utf8mb4_bin NOT NULL DEFAULT ''spelling''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='language'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `language` varchar(35) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='region'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `region` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='year_from'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `year_from` smallint DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='year_to'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `year_to` smallint DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='source_id'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `source_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND COLUMN_NAME='notes'), 'ALTER TABLE `etymolog_variant` ADD COLUMN `notes` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)>0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND COLUMN_NAME='name_id' AND IS_NULLABLE='NO'), 'ALTER TABLE `etymolog_entry` MODIFY COLUMN `name_id` int unsigned NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)>0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND COLUMN_NAME='name_id' AND IS_NULLABLE='NO'), 'ALTER TABLE `etymolog_external_record` MODIFY COLUMN `name_id` int unsigned NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)>0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='cursor' AND DATA_TYPE='varchar' AND CHARACTER_MAXIMUM_LENGTH<2048), 'ALTER TABLE `etymolog_sync_job` MODIFY COLUMN `cursor` varchar(2048) NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COALESCE(GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX),'')='franchise_code,name_id,provider' FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND INDEX_NAME='uq_etymolog_import'), 'ALTER TABLE `etymolog_import_record` DROP INDEX `uq_etymolog_import`, ADD UNIQUE KEY `uq_etymolog_import` (`franchise_code`,`name_id`,`provider`,`external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

-- Missing indexes. Conflicting existing rows cause an error, never data removal.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_calendar` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND INDEX_NAME='uq_ety_calendar_tenant_id'), 'ALTER TABLE `etymolog_calendar` ADD UNIQUE KEY `uq_ety_calendar_tenant_id` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar' AND INDEX_NAME='uq_ety_calendar_import'), 'ALTER TABLE `etymolog_calendar` ADD UNIQUE KEY `uq_ety_calendar_import` (`franchise_code`,`import_key`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_calendar_day` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND INDEX_NAME='uq_ety_calendar_day_tenant_id'), 'ALTER TABLE `etymolog_calendar_day` ADD UNIQUE KEY `uq_ety_calendar_day_tenant_id` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND INDEX_NAME='uq_ety_calendar_day_import'), 'ALTER TABLE `etymolog_calendar_day` ADD UNIQUE KEY `uq_ety_calendar_day_import` (`franchise_code`,`import_key`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND INDEX_NAME='idx_ety_calendar_date'), 'ALTER TABLE `etymolog_calendar_day` ADD KEY `idx_ety_calendar_date` (`franchise_code`,`calendar_id`,`month`,`day`,`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND INDEX_NAME='fk_ety_calendar_day_source'), 'ALTER TABLE `etymolog_calendar_day` ADD KEY `fk_ety_calendar_day_source` (`franchise_code`,`source_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND INDEX_NAME='fk_ety_calendar_day_name'), 'ALTER TABLE `etymolog_calendar_day` ADD KEY `fk_ety_calendar_day_name` (`franchise_code`,`name_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND INDEX_NAME='fk_ety_calendar_day_entry'), 'ALTER TABLE `etymolog_calendar_day` ADD KEY `fk_ety_calendar_day_entry` (`franchise_code`,`entry_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_citation` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND INDEX_NAME='uq_etymolog_citation_tenant_id'), 'ALTER TABLE `etymolog_citation` ADD UNIQUE KEY `uq_etymolog_citation_tenant_id` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND INDEX_NAME='idx_etymolog_citation_active'), 'ALTER TABLE `etymolog_citation` ADD KEY `idx_etymolog_citation_active` (`franchise_code`,`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND INDEX_NAME='fk_ety_citation_entry_id'), 'ALTER TABLE `etymolog_citation` ADD KEY `fk_ety_citation_entry_id` (`franchise_code`,`entry_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND INDEX_NAME='fk_ety_citation_source_id'), 'ALTER TABLE `etymolog_citation` ADD KEY `fk_ety_citation_source_id` (`franchise_code`,`source_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_entry` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND INDEX_NAME='uq_etymolog_entry_tenant_id'), 'ALTER TABLE `etymolog_entry` ADD UNIQUE KEY `uq_etymolog_entry_tenant_id` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND INDEX_NAME='idx_etymolog_entry_active'), 'ALTER TABLE `etymolog_entry` ADD KEY `idx_etymolog_entry_active` (`franchise_code`,`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND INDEX_NAME='fk_ety_entry_name_id'), 'ALTER TABLE `etymolog_entry` ADD KEY `fk_ety_entry_name_id` (`franchise_code`,`name_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_entry_name` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND INDEX_NAME='uq_ety_entry_name_tenant_id'), 'ALTER TABLE `etymolog_entry_name` ADD UNIQUE KEY `uq_ety_entry_name_tenant_id` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND INDEX_NAME='idx_ety_entry_name_entry'), 'ALTER TABLE `etymolog_entry_name` ADD KEY `idx_ety_entry_name_entry` (`franchise_code`,`entry_id`,`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND INDEX_NAME='idx_ety_entry_name_name'), 'ALTER TABLE `etymolog_entry_name` ADD KEY `idx_ety_entry_name_name` (`franchise_code`,`name_id`,`deleted`,`reviewed`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_external_record` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND INDEX_NAME='uq_ety_external'), 'ALTER TABLE `etymolog_external_record` ADD UNIQUE KEY `uq_ety_external` (`franchise_code`,`provider`,`external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND INDEX_NAME='fk_ety_external_name'), 'ALTER TABLE `etymolog_external_record` ADD KEY `fk_ety_external_name` (`franchise_code`,`name_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND INDEX_NAME='fk_ety_external_source'), 'ALTER TABLE `etymolog_external_record` ADD KEY `fk_ety_external_source` (`franchise_code`,`source_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND INDEX_NAME='fk_ety_external_entry'), 'ALTER TABLE `etymolog_external_record` ADD KEY `fk_ety_external_entry` (`franchise_code`,`entry_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND INDEX_NAME='fk_ety_external_occurrence'), 'ALTER TABLE `etymolog_external_record` ADD KEY `fk_ety_external_occurrence` (`franchise_code`,`occurrence_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND INDEX_NAME='fk_ety_external_calendar_day'), 'ALTER TABLE `etymolog_external_record` ADD KEY `fk_ety_external_calendar_day` (`franchise_code`,`calendar_day_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_import_record` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND INDEX_NAME='uq_etymolog_import'), 'ALTER TABLE `etymolog_import_record` ADD UNIQUE KEY `uq_etymolog_import` (`franchise_code`,`name_id`,`provider`,`external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_name` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND INDEX_NAME='uq_etymolog_name_tenant_id'), 'ALTER TABLE `etymolog_name` ADD UNIQUE KEY `uq_etymolog_name_tenant_id` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND INDEX_NAME='uq_etymolog_name_import'), 'ALTER TABLE `etymolog_name` ADD UNIQUE KEY `uq_etymolog_name_import` (`franchise_code`,`import_key`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND INDEX_NAME='idx_etymolog_name_search'), 'ALTER TABLE `etymolog_name` ADD KEY `idx_etymolog_name_search` (`franchise_code`,`name`,`kind`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND INDEX_NAME='idx_etymolog_name_active'), 'ALTER TABLE `etymolog_name` ADD KEY `idx_etymolog_name_active` (`franchise_code`,`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_occurrence` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND INDEX_NAME='uq_etymolog_occurrence_tenant_id'), 'ALTER TABLE `etymolog_occurrence` ADD UNIQUE KEY `uq_etymolog_occurrence_tenant_id` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND INDEX_NAME='idx_etymolog_occurrence_active'), 'ALTER TABLE `etymolog_occurrence` ADD KEY `idx_etymolog_occurrence_active` (`franchise_code`,`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND INDEX_NAME='fk_ety_occurrence_name_id'), 'ALTER TABLE `etymolog_occurrence` ADD KEY `fk_ety_occurrence_name_id` (`franchise_code`,`name_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND INDEX_NAME='fk_ety_occurrence_source_id'), 'ALTER TABLE `etymolog_occurrence` ADD KEY `fk_ety_occurrence_source_id` (`franchise_code`,`source_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_source` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND INDEX_NAME='uq_etymolog_source_tenant_id'), 'ALTER TABLE `etymolog_source` ADD UNIQUE KEY `uq_etymolog_source_tenant_id` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND INDEX_NAME='uq_ety_source_import'), 'ALTER TABLE `etymolog_source` ADD UNIQUE KEY `uq_ety_source_import` (`franchise_code`,`import_key`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_source' AND INDEX_NAME='idx_etymolog_source_active'), 'ALTER TABLE `etymolog_source` ADD KEY `idx_etymolog_source_active` (`franchise_code`,`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_story_import` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND INDEX_NAME='uq_ety_story_external'), 'ALTER TABLE `etymolog_story_import` ADD UNIQUE KEY `uq_ety_story_external` (`franchise_code`,`provider`,`external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND INDEX_NAME='idx_ety_story_entry'), 'ALTER TABLE `etymolog_story_import` ADD KEY `idx_ety_story_entry` (`franchise_code`,`entry_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND INDEX_NAME='fk_ety_story_source'), 'ALTER TABLE `etymolog_story_import` ADD KEY `fk_ety_story_source` (`franchise_code`,`source_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_sync_batch` ADD PRIMARY KEY (`franchise_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_sync_job` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND INDEX_NAME='uq_etymolog_sync_job_tenant_id'), 'ALTER TABLE `etymolog_sync_job` ADD UNIQUE KEY `uq_etymolog_sync_job_tenant_id` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND INDEX_NAME='idx_etymolog_job_due'), 'ALTER TABLE `etymolog_sync_job` ADD KEY `idx_etymolog_job_due` (`franchise_code`,`deleted`,`enabled`,`next_run_at`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND INDEX_NAME='idx_etymolog_sync_job_active'), 'ALTER TABLE `etymolog_sync_job` ADD KEY `idx_etymolog_sync_job_active` (`franchise_code`,`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_sync_run` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND INDEX_NAME='idx_etymolog_run_job'), 'ALTER TABLE `etymolog_sync_run` ADD KEY `idx_etymolog_run_job` (`franchise_code`,`job_id`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_schedule' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_sync_schedule` ADD PRIMARY KEY (`franchise_code`,`scheduled_date`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `etymolog_variant` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND INDEX_NAME='uq_etymolog_variant_tenant_id'), 'ALTER TABLE `etymolog_variant` ADD UNIQUE KEY `uq_etymolog_variant_tenant_id` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND INDEX_NAME='idx_etymolog_variant_search'), 'ALTER TABLE `etymolog_variant` ADD KEY `idx_etymolog_variant_search` (`franchise_code`,`variant`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND INDEX_NAME='idx_etymolog_variant_active'), 'ALTER TABLE `etymolog_variant` ADD KEY `idx_etymolog_variant_active` (`franchise_code`,`deleted`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND INDEX_NAME='fk_ety_variant_name_id'), 'ALTER TABLE `etymolog_variant` ADD KEY `fk_ety_variant_name_id` (`franchise_code`,`name_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND INDEX_NAME='fk_ety_variant_target_name_id'), 'ALTER TABLE `etymolog_variant` ADD KEY `fk_ety_variant_target_name_id` (`franchise_code`,`target_name_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND INDEX_NAME='fk_ety_variant_source_id'), 'ALTER TABLE `etymolog_variant` ADD KEY `fk_ety_variant_source_id` (`franchise_code`,`source_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

-- Missing constraints.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND CONSTRAINT_NAME='fk_ety_calendar_day_calendar'), 'ALTER TABLE `etymolog_calendar_day` ADD CONSTRAINT `fk_ety_calendar_day_calendar` FOREIGN KEY (`franchise_code`, `calendar_id`) REFERENCES `etymolog_calendar` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND CONSTRAINT_NAME='fk_ety_calendar_day_entry'), 'ALTER TABLE `etymolog_calendar_day` ADD CONSTRAINT `fk_ety_calendar_day_entry` FOREIGN KEY (`franchise_code`, `entry_id`) REFERENCES `etymolog_entry` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND CONSTRAINT_NAME='fk_ety_calendar_day_name'), 'ALTER TABLE `etymolog_calendar_day` ADD CONSTRAINT `fk_ety_calendar_day_name` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_calendar_day' AND CONSTRAINT_NAME='fk_ety_calendar_day_source'), 'ALTER TABLE `etymolog_calendar_day` ADD CONSTRAINT `fk_ety_calendar_day_source` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND CONSTRAINT_NAME='fk_ety_citation_entry_id'), 'ALTER TABLE `etymolog_citation` ADD CONSTRAINT `fk_ety_citation_entry_id` FOREIGN KEY (`franchise_code`, `entry_id`) REFERENCES `etymolog_entry` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_citation' AND CONSTRAINT_NAME='fk_ety_citation_source_id'), 'ALTER TABLE `etymolog_citation` ADD CONSTRAINT `fk_ety_citation_source_id` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry' AND CONSTRAINT_NAME='fk_ety_entry_name_id'), 'ALTER TABLE `etymolog_entry` ADD CONSTRAINT `fk_ety_entry_name_id` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND CONSTRAINT_NAME='fk_ety_link_entry'), 'ALTER TABLE `etymolog_entry_name` ADD CONSTRAINT `fk_ety_link_entry` FOREIGN KEY (`franchise_code`, `entry_id`) REFERENCES `etymolog_entry` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_entry_name' AND CONSTRAINT_NAME='fk_ety_link_name'), 'ALTER TABLE `etymolog_entry_name` ADD CONSTRAINT `fk_ety_link_name` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND CONSTRAINT_NAME='fk_ety_external_calendar_day'), 'ALTER TABLE `etymolog_external_record` ADD CONSTRAINT `fk_ety_external_calendar_day` FOREIGN KEY (`franchise_code`, `calendar_day_id`) REFERENCES `etymolog_calendar_day` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND CONSTRAINT_NAME='fk_ety_external_entry'), 'ALTER TABLE `etymolog_external_record` ADD CONSTRAINT `fk_ety_external_entry` FOREIGN KEY (`franchise_code`, `entry_id`) REFERENCES `etymolog_entry` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND CONSTRAINT_NAME='fk_ety_external_name'), 'ALTER TABLE `etymolog_external_record` ADD CONSTRAINT `fk_ety_external_name` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND CONSTRAINT_NAME='fk_ety_external_occurrence'), 'ALTER TABLE `etymolog_external_record` ADD CONSTRAINT `fk_ety_external_occurrence` FOREIGN KEY (`franchise_code`, `occurrence_id`) REFERENCES `etymolog_occurrence` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_external_record' AND CONSTRAINT_NAME='fk_ety_external_source'), 'ALTER TABLE `etymolog_external_record` ADD CONSTRAINT `fk_ety_external_source` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND CONSTRAINT_NAME='fk_ety_import_name'), 'ALTER TABLE `etymolog_import_record` ADD CONSTRAINT `fk_ety_import_name` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND CONSTRAINT_NAME='fk_ety_occurrence_name_id'), 'ALTER TABLE `etymolog_occurrence` ADD CONSTRAINT `fk_ety_occurrence_name_id` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_occurrence' AND CONSTRAINT_NAME='fk_ety_occurrence_source_id'), 'ALTER TABLE `etymolog_occurrence` ADD CONSTRAINT `fk_ety_occurrence_source_id` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND CONSTRAINT_NAME='fk_ety_story_entry'), 'ALTER TABLE `etymolog_story_import` ADD CONSTRAINT `fk_ety_story_entry` FOREIGN KEY (`franchise_code`, `entry_id`) REFERENCES `etymolog_entry` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_story_import' AND CONSTRAINT_NAME='fk_ety_story_source'), 'ALTER TABLE `etymolog_story_import` ADD CONSTRAINT `fk_ety_story_source` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_run' AND CONSTRAINT_NAME='fk_ety_run_job'), 'ALTER TABLE `etymolog_sync_run` ADD CONSTRAINT `fk_ety_run_job` FOREIGN KEY (`franchise_code`, `job_id`) REFERENCES `etymolog_sync_job` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND CONSTRAINT_NAME='fk_ety_variant_name_id'), 'ALTER TABLE `etymolog_variant` ADD CONSTRAINT `fk_ety_variant_name_id` FOREIGN KEY (`franchise_code`, `name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND CONSTRAINT_NAME='fk_ety_variant_source_id'), 'ALTER TABLE `etymolog_variant` ADD CONSTRAINT `fk_ety_variant_source_id` FOREIGN KEY (`franchise_code`, `source_id`) REFERENCES `etymolog_source` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_variant' AND CONSTRAINT_NAME='fk_ety_variant_target_name_id'), 'ALTER TABLE `etymolog_variant` ADD CONSTRAINT `fk_ety_variant_target_name_id` FOREIGN KEY (`franchise_code`, `target_name_id`) REFERENCES `etymolog_name` (`franchise_code`, `id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;
