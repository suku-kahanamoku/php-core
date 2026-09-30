-- tram_schema: canonical definitions. Apply schema.sql before product schemas.
-- Run python3 scripts/build-schemas.py after editing CREATE definitions.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `transport_provider` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `adapter` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `role` varchar(16) COLLATE utf8mb4_bin NOT NULL DEFAULT 'primary',
  `config` json NOT NULL,
  `coverage` json NOT NULL,
  `fallback_for` json NOT NULL,
  `published` tinyint NOT NULL DEFAULT '0',
  `failure_count` int unsigned NOT NULL DEFAULT '0',
  `open_until` datetime DEFAULT NULL,
  `probe_until` datetime DEFAULT NULL,
  `next_request_at` datetime(3) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`franchise_code`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_feed` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `provider_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `url` text COLLATE utf8mb4_bin NOT NULL,
  `timezone` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `config` json NOT NULL,
  `active_version_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`franchise_code`,`code`),
  KEY `fk_transport_feed_provider` (`franchise_code`,`provider_code`)
  -- deferred CONSTRAINT `fk_transport_feed_provider` FOREIGN KEY (`franchise_code`, `provider_code`) REFERENCES `transport_provider` (`franchise_code`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_feed_version` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `feed_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `checksum` char(64) COLLATE utf8mb4_bin NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_bin NOT NULL DEFAULT 'importing',
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `archive_path` text COLLATE utf8mb4_bin NOT NULL,
  `row_counts` json DEFAULT NULL,
  `graph_url` text COLLATE utf8mb4_bin,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transport_version_scope` (`franchise_code`,`id`),
  UNIQUE KEY `uq_transport_version_hash` (`franchise_code`,`feed_code`,`checksum`)
  -- deferred CONSTRAINT `fk_transport_version_feed` FOREIGN KEY (`franchise_code`, `feed_code`) REFERENCES `transport_feed` (`franchise_code`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_sync_run` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `feed_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_bin NOT NULL,
  `error_code` varchar(64) COLLATE utf8mb4_bin DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transport_sync` (`franchise_code`,`feed_code`,`started_at`),
  KEY `idx_transport_sync_version` (`franchise_code`,`version_id`,`status`,`finished_at`)
  -- deferred CONSTRAINT `fk_transport_sync_feed` FOREIGN KEY (`franchise_code`, `feed_code`) REFERENCES `transport_feed` (`franchise_code`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_operator` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned NOT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `timezone` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `data` json NOT NULL,
  PRIMARY KEY (`franchise_code`,`version_id`,`external_id`)
  -- deferred CONSTRAINT `fk_transport_operator_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_stop` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned NOT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lon` decimal(10,7) DEFAULT NULL,
  `parent_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `location_type` smallint NOT NULL DEFAULT '0',
  `platform` varchar(64) COLLATE utf8mb4_bin DEFAULT NULL,
  `data` json NOT NULL,
  PRIMARY KEY (`franchise_code`,`version_id`,`external_id`),
  KEY `idx_transport_stop_name` (`franchise_code`,`version_id`,`name`),
  KEY `idx_transport_stop_geo` (`franchise_code`,`version_id`,`lat`,`lon`)
  -- deferred CONSTRAINT `fk_transport_stop_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_route` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned NOT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `operator_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode` varchar(32) COLLATE utf8mb4_bin NOT NULL,
  `data` json NOT NULL,
  PRIMARY KEY (`franchise_code`,`version_id`,`external_id`),
  KEY `fk_transport_route_operator` (`franchise_code`,`version_id`,`operator_id`)
  -- deferred CONSTRAINT `fk_transport_route_operator` FOREIGN KEY (`franchise_code`, `version_id`, `operator_id`) REFERENCES `transport_operator` (`franchise_code`, `version_id`, `external_id`)
  -- deferred CONSTRAINT `fk_transport_route_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_service` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned NOT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `weekdays` char(7) COLLATE utf8mb4_bin NOT NULL DEFAULT '0000000',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `data` json NOT NULL,
  PRIMARY KEY (`franchise_code`,`version_id`,`external_id`)
  -- deferred CONSTRAINT `fk_transport_service_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_service_exception` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned NOT NULL,
  `service_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `service_date` date NOT NULL,
  `exception_type` tinyint NOT NULL,
  PRIMARY KEY (`franchise_code`,`version_id`,`service_id`,`service_date`)
  -- deferred CONSTRAINT `fk_transport_exception_service` FOREIGN KEY (`franchise_code`, `version_id`, `service_id`) REFERENCES `transport_service` (`franchise_code`, `version_id`, `external_id`)
  -- deferred CONSTRAINT `fk_transport_service_exception_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_shape` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned NOT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `sequence` int unsigned NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lon` decimal(10,7) NOT NULL,
  `distance` double DEFAULT NULL,
  PRIMARY KEY (`franchise_code`,`version_id`,`external_id`,`sequence`)
  -- deferred CONSTRAINT `fk_transport_shape_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_trip` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned NOT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `route_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `service_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `shape_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `headsign` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `data` json NOT NULL,
  PRIMARY KEY (`franchise_code`,`version_id`,`external_id`),
  KEY `idx_transport_trip_service` (`franchise_code`,`version_id`,`service_id`),
  KEY `fk_transport_trip_route` (`franchise_code`,`version_id`,`route_id`)
  -- deferred CONSTRAINT `fk_transport_trip_route` FOREIGN KEY (`franchise_code`, `version_id`, `route_id`) REFERENCES `transport_route` (`franchise_code`, `version_id`, `external_id`)
  -- deferred CONSTRAINT `fk_transport_trip_service` FOREIGN KEY (`franchise_code`, `version_id`, `service_id`) REFERENCES `transport_service` (`franchise_code`, `version_id`, `external_id`)
  -- deferred CONSTRAINT `fk_transport_trip_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_stop_time` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned NOT NULL,
  `trip_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `sequence` int unsigned NOT NULL,
  `stop_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `arrival_seconds` int unsigned DEFAULT NULL,
  `departure_seconds` int unsigned DEFAULT NULL,
  `pickup_type` tinyint NOT NULL DEFAULT '0',
  `drop_off_type` tinyint NOT NULL DEFAULT '0',
  `data` json NOT NULL,
  PRIMARY KEY (`franchise_code`,`version_id`,`trip_id`,`sequence`),
  KEY `idx_transport_departures` (`franchise_code`,`version_id`,`stop_id`,`departure_seconds`)
  -- deferred CONSTRAINT `fk_transport_stop_time_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE
  -- deferred CONSTRAINT `fk_transport_time_stop` FOREIGN KEY (`franchise_code`, `version_id`, `stop_id`) REFERENCES `transport_stop` (`franchise_code`, `version_id`, `external_id`)
  -- deferred CONSTRAINT `fk_transport_time_trip` FOREIGN KEY (`franchise_code`, `version_id`, `trip_id`) REFERENCES `transport_trip` (`franchise_code`, `version_id`, `external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_frequency` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned NOT NULL,
  `trip_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `start_seconds` int unsigned NOT NULL,
  `end_seconds` int unsigned NOT NULL,
  `headway_seconds` int unsigned NOT NULL,
  `exact_times` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`franchise_code`,`version_id`,`trip_id`,`start_seconds`)
  -- deferred CONSTRAINT `fk_transport_frequency_trip` FOREIGN KEY (`franchise_code`, `version_id`, `trip_id`) REFERENCES `transport_trip` (`franchise_code`, `version_id`, `external_id`)
  -- deferred CONSTRAINT `fk_transport_frequency_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_transfer` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `version_id` bigint unsigned NOT NULL,
  `sequence` int unsigned NOT NULL,
  `from_stop_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `to_stop_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `transfer_type` tinyint NOT NULL,
  `min_transfer_seconds` int unsigned DEFAULT NULL,
  `data` json NOT NULL,
  PRIMARY KEY (`franchise_code`,`version_id`,`sequence`)
  -- deferred CONSTRAINT `fk_transport_transfer_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `transport_journey_cache` (
  `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `id` char(32) COLLATE utf8mb4_bin NOT NULL,
  `payload` json NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`franchise_code`,`id`),
  KEY `idx_transport_cache_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- BEGIN GENERATED ADDITIVE DDL (scripts/build-schemas.py)
-- Missing columns first, then indexes and foreign keys. Existing data stay intact.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_provider` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='code'), 'ALTER TABLE `transport_provider` ADD COLUMN `code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='adapter'), 'ALTER TABLE `transport_provider` ADD COLUMN `adapter` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='role'), 'ALTER TABLE `transport_provider` ADD COLUMN `role` varchar(16) COLLATE utf8mb4_bin NOT NULL DEFAULT ''primary''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='config'), 'ALTER TABLE `transport_provider` ADD COLUMN `config` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='coverage'), 'ALTER TABLE `transport_provider` ADD COLUMN `coverage` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='fallback_for'), 'ALTER TABLE `transport_provider` ADD COLUMN `fallback_for` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='published'), 'ALTER TABLE `transport_provider` ADD COLUMN `published` tinyint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='failure_count'), 'ALTER TABLE `transport_provider` ADD COLUMN `failure_count` int unsigned NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='open_until'), 'ALTER TABLE `transport_provider` ADD COLUMN `open_until` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='probe_until'), 'ALTER TABLE `transport_provider` ADD COLUMN `probe_until` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='next_request_at'), 'ALTER TABLE `transport_provider` ADD COLUMN `next_request_at` datetime(3) DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND COLUMN_NAME='updated_at'), 'ALTER TABLE `transport_provider` ADD COLUMN `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_feed` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed' AND COLUMN_NAME='code'), 'ALTER TABLE `transport_feed` ADD COLUMN `code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed' AND COLUMN_NAME='provider_code'), 'ALTER TABLE `transport_feed` ADD COLUMN `provider_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed' AND COLUMN_NAME='url'), 'ALTER TABLE `transport_feed` ADD COLUMN `url` text COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed' AND COLUMN_NAME='timezone'), 'ALTER TABLE `transport_feed` ADD COLUMN `timezone` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed' AND COLUMN_NAME='config'), 'ALTER TABLE `transport_feed` ADD COLUMN `config` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed' AND COLUMN_NAME='active_version_id'), 'ALTER TABLE `transport_feed` ADD COLUMN `active_version_id` bigint unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='id'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `id` bigint unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='feed_code'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `feed_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='checksum'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `checksum` char(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='status'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `status` varchar(20) COLLATE utf8mb4_bin NOT NULL DEFAULT ''importing''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='valid_from'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `valid_from` date DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='valid_until'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `valid_until` date DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='archive_path'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `archive_path` text COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='row_counts'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `row_counts` json DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='graph_url'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `graph_url` text COLLATE utf8mb4_bin', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND COLUMN_NAME='created_at'), 'ALTER TABLE `transport_feed_version` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND COLUMN_NAME='id'), 'ALTER TABLE `transport_sync_run` ADD COLUMN `id` bigint unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_sync_run` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND COLUMN_NAME='feed_code'), 'ALTER TABLE `transport_sync_run` ADD COLUMN `feed_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_sync_run` ADD COLUMN `version_id` bigint unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND COLUMN_NAME='status'), 'ALTER TABLE `transport_sync_run` ADD COLUMN `status` varchar(20) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND COLUMN_NAME='error_code'), 'ALTER TABLE `transport_sync_run` ADD COLUMN `error_code` varchar(64) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND COLUMN_NAME='started_at'), 'ALTER TABLE `transport_sync_run` ADD COLUMN `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND COLUMN_NAME='finished_at'), 'ALTER TABLE `transport_sync_run` ADD COLUMN `finished_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_operator' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_operator` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_operator' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_operator` ADD COLUMN `version_id` bigint unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_operator' AND COLUMN_NAME='external_id'), 'ALTER TABLE `transport_operator` ADD COLUMN `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_operator' AND COLUMN_NAME='name'), 'ALTER TABLE `transport_operator` ADD COLUMN `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_operator' AND COLUMN_NAME='timezone'), 'ALTER TABLE `transport_operator` ADD COLUMN `timezone` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_operator' AND COLUMN_NAME='data'), 'ALTER TABLE `transport_operator` ADD COLUMN `data` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_stop` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_stop` ADD COLUMN `version_id` bigint unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND COLUMN_NAME='external_id'), 'ALTER TABLE `transport_stop` ADD COLUMN `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND COLUMN_NAME='name'), 'ALTER TABLE `transport_stop` ADD COLUMN `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND COLUMN_NAME='lat'), 'ALTER TABLE `transport_stop` ADD COLUMN `lat` decimal(10,7) DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND COLUMN_NAME='lon'), 'ALTER TABLE `transport_stop` ADD COLUMN `lon` decimal(10,7) DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND COLUMN_NAME='parent_id'), 'ALTER TABLE `transport_stop` ADD COLUMN `parent_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND COLUMN_NAME='location_type'), 'ALTER TABLE `transport_stop` ADD COLUMN `location_type` smallint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND COLUMN_NAME='platform'), 'ALTER TABLE `transport_stop` ADD COLUMN `platform` varchar(64) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND COLUMN_NAME='data'), 'ALTER TABLE `transport_stop` ADD COLUMN `data` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_route` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_route` ADD COLUMN `version_id` bigint unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND COLUMN_NAME='external_id'), 'ALTER TABLE `transport_route` ADD COLUMN `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND COLUMN_NAME='operator_id'), 'ALTER TABLE `transport_route` ADD COLUMN `operator_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND COLUMN_NAME='name'), 'ALTER TABLE `transport_route` ADD COLUMN `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND COLUMN_NAME='mode'), 'ALTER TABLE `transport_route` ADD COLUMN `mode` varchar(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND COLUMN_NAME='data'), 'ALTER TABLE `transport_route` ADD COLUMN `data` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_service` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_service` ADD COLUMN `version_id` bigint unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service' AND COLUMN_NAME='external_id'), 'ALTER TABLE `transport_service` ADD COLUMN `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service' AND COLUMN_NAME='weekdays'), 'ALTER TABLE `transport_service` ADD COLUMN `weekdays` char(7) COLLATE utf8mb4_bin NOT NULL DEFAULT ''0000000''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service' AND COLUMN_NAME='start_date'), 'ALTER TABLE `transport_service` ADD COLUMN `start_date` date DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service' AND COLUMN_NAME='end_date'), 'ALTER TABLE `transport_service` ADD COLUMN `end_date` date DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service' AND COLUMN_NAME='data'), 'ALTER TABLE `transport_service` ADD COLUMN `data` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service_exception' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_service_exception` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service_exception' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_service_exception` ADD COLUMN `version_id` bigint unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service_exception' AND COLUMN_NAME='service_id'), 'ALTER TABLE `transport_service_exception` ADD COLUMN `service_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service_exception' AND COLUMN_NAME='service_date'), 'ALTER TABLE `transport_service_exception` ADD COLUMN `service_date` date NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service_exception' AND COLUMN_NAME='exception_type'), 'ALTER TABLE `transport_service_exception` ADD COLUMN `exception_type` tinyint NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_shape' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_shape` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_shape' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_shape` ADD COLUMN `version_id` bigint unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_shape' AND COLUMN_NAME='external_id'), 'ALTER TABLE `transport_shape` ADD COLUMN `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_shape' AND COLUMN_NAME='sequence'), 'ALTER TABLE `transport_shape` ADD COLUMN `sequence` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_shape' AND COLUMN_NAME='lat'), 'ALTER TABLE `transport_shape` ADD COLUMN `lat` decimal(10,7) NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_shape' AND COLUMN_NAME='lon'), 'ALTER TABLE `transport_shape` ADD COLUMN `lon` decimal(10,7) NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_shape' AND COLUMN_NAME='distance'), 'ALTER TABLE `transport_shape` ADD COLUMN `distance` double DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_trip` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_trip` ADD COLUMN `version_id` bigint unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND COLUMN_NAME='external_id'), 'ALTER TABLE `transport_trip` ADD COLUMN `external_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND COLUMN_NAME='route_id'), 'ALTER TABLE `transport_trip` ADD COLUMN `route_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND COLUMN_NAME='service_id'), 'ALTER TABLE `transport_trip` ADD COLUMN `service_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND COLUMN_NAME='shape_id'), 'ALTER TABLE `transport_trip` ADD COLUMN `shape_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND COLUMN_NAME='headsign'), 'ALTER TABLE `transport_trip` ADD COLUMN `headsign` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND COLUMN_NAME='data'), 'ALTER TABLE `transport_trip` ADD COLUMN `data` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_stop_time` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_stop_time` ADD COLUMN `version_id` bigint unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND COLUMN_NAME='trip_id'), 'ALTER TABLE `transport_stop_time` ADD COLUMN `trip_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND COLUMN_NAME='sequence'), 'ALTER TABLE `transport_stop_time` ADD COLUMN `sequence` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND COLUMN_NAME='stop_id'), 'ALTER TABLE `transport_stop_time` ADD COLUMN `stop_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND COLUMN_NAME='arrival_seconds'), 'ALTER TABLE `transport_stop_time` ADD COLUMN `arrival_seconds` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND COLUMN_NAME='departure_seconds'), 'ALTER TABLE `transport_stop_time` ADD COLUMN `departure_seconds` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND COLUMN_NAME='pickup_type'), 'ALTER TABLE `transport_stop_time` ADD COLUMN `pickup_type` tinyint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND COLUMN_NAME='drop_off_type'), 'ALTER TABLE `transport_stop_time` ADD COLUMN `drop_off_type` tinyint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND COLUMN_NAME='data'), 'ALTER TABLE `transport_stop_time` ADD COLUMN `data` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_frequency' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_frequency` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_frequency' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_frequency` ADD COLUMN `version_id` bigint unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_frequency' AND COLUMN_NAME='trip_id'), 'ALTER TABLE `transport_frequency` ADD COLUMN `trip_id` varchar(255) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_frequency' AND COLUMN_NAME='start_seconds'), 'ALTER TABLE `transport_frequency` ADD COLUMN `start_seconds` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_frequency' AND COLUMN_NAME='end_seconds'), 'ALTER TABLE `transport_frequency` ADD COLUMN `end_seconds` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_frequency' AND COLUMN_NAME='headway_seconds'), 'ALTER TABLE `transport_frequency` ADD COLUMN `headway_seconds` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_frequency' AND COLUMN_NAME='exact_times'), 'ALTER TABLE `transport_frequency` ADD COLUMN `exact_times` tinyint NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_transfer' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_transfer` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_transfer' AND COLUMN_NAME='version_id'), 'ALTER TABLE `transport_transfer` ADD COLUMN `version_id` bigint unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_transfer' AND COLUMN_NAME='sequence'), 'ALTER TABLE `transport_transfer` ADD COLUMN `sequence` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_transfer' AND COLUMN_NAME='from_stop_id'), 'ALTER TABLE `transport_transfer` ADD COLUMN `from_stop_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_transfer' AND COLUMN_NAME='to_stop_id'), 'ALTER TABLE `transport_transfer` ADD COLUMN `to_stop_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_transfer' AND COLUMN_NAME='transfer_type'), 'ALTER TABLE `transport_transfer` ADD COLUMN `transfer_type` tinyint NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_transfer' AND COLUMN_NAME='min_transfer_seconds'), 'ALTER TABLE `transport_transfer` ADD COLUMN `min_transfer_seconds` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_transfer' AND COLUMN_NAME='data'), 'ALTER TABLE `transport_transfer` ADD COLUMN `data` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_journey_cache' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `transport_journey_cache` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_journey_cache' AND COLUMN_NAME='id'), 'ALTER TABLE `transport_journey_cache` ADD COLUMN `id` char(32) COLLATE utf8mb4_bin NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_journey_cache' AND COLUMN_NAME='payload'), 'ALTER TABLE `transport_journey_cache` ADD COLUMN `payload` json NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_journey_cache' AND COLUMN_NAME='expires_at'), 'ALTER TABLE `transport_journey_cache` ADD COLUMN `expires_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

-- Missing indexes. Conflicting existing rows cause an error, never data removal.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_provider' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_provider` ADD PRIMARY KEY (`franchise_code`,`code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_feed` ADD PRIMARY KEY (`franchise_code`,`code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed' AND INDEX_NAME='fk_transport_feed_provider'), 'ALTER TABLE `transport_feed` ADD KEY `fk_transport_feed_provider` (`franchise_code`,`provider_code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_feed_version` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND INDEX_NAME='uq_transport_version_scope'), 'ALTER TABLE `transport_feed_version` ADD UNIQUE KEY `uq_transport_version_scope` (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND INDEX_NAME='uq_transport_version_hash'), 'ALTER TABLE `transport_feed_version` ADD UNIQUE KEY `uq_transport_version_hash` (`franchise_code`,`feed_code`,`checksum`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_sync_run` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND INDEX_NAME='idx_transport_sync'), 'ALTER TABLE `transport_sync_run` ADD KEY `idx_transport_sync` (`franchise_code`,`feed_code`,`started_at`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND INDEX_NAME='idx_transport_sync_version'), 'ALTER TABLE `transport_sync_run` ADD KEY `idx_transport_sync_version` (`franchise_code`,`version_id`,`status`,`finished_at`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_operator' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_operator` ADD PRIMARY KEY (`franchise_code`,`version_id`,`external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_stop` ADD PRIMARY KEY (`franchise_code`,`version_id`,`external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND INDEX_NAME='idx_transport_stop_name'), 'ALTER TABLE `transport_stop` ADD KEY `idx_transport_stop_name` (`franchise_code`,`version_id`,`name`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND INDEX_NAME='idx_transport_stop_geo'), 'ALTER TABLE `transport_stop` ADD KEY `idx_transport_stop_geo` (`franchise_code`,`version_id`,`lat`,`lon`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_route` ADD PRIMARY KEY (`franchise_code`,`version_id`,`external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND INDEX_NAME='fk_transport_route_operator'), 'ALTER TABLE `transport_route` ADD KEY `fk_transport_route_operator` (`franchise_code`,`version_id`,`operator_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_service` ADD PRIMARY KEY (`franchise_code`,`version_id`,`external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_service_exception' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_service_exception` ADD PRIMARY KEY (`franchise_code`,`version_id`,`service_id`,`service_date`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_shape' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_shape` ADD PRIMARY KEY (`franchise_code`,`version_id`,`external_id`,`sequence`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_trip` ADD PRIMARY KEY (`franchise_code`,`version_id`,`external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND INDEX_NAME='idx_transport_trip_service'), 'ALTER TABLE `transport_trip` ADD KEY `idx_transport_trip_service` (`franchise_code`,`version_id`,`service_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND INDEX_NAME='fk_transport_trip_route'), 'ALTER TABLE `transport_trip` ADD KEY `fk_transport_trip_route` (`franchise_code`,`version_id`,`route_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_stop_time` ADD PRIMARY KEY (`franchise_code`,`version_id`,`trip_id`,`sequence`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND INDEX_NAME='idx_transport_departures'), 'ALTER TABLE `transport_stop_time` ADD KEY `idx_transport_departures` (`franchise_code`,`version_id`,`stop_id`,`departure_seconds`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_frequency' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_frequency` ADD PRIMARY KEY (`franchise_code`,`version_id`,`trip_id`,`start_seconds`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_transfer' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_transfer` ADD PRIMARY KEY (`franchise_code`,`version_id`,`sequence`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_journey_cache' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `transport_journey_cache` ADD PRIMARY KEY (`franchise_code`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_journey_cache' AND INDEX_NAME='idx_transport_cache_expiry'), 'ALTER TABLE `transport_journey_cache` ADD KEY `idx_transport_cache_expiry` (`expires_at`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

-- Missing constraints.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed' AND CONSTRAINT_NAME='fk_transport_feed_provider'), 'ALTER TABLE `transport_feed` ADD CONSTRAINT `fk_transport_feed_provider` FOREIGN KEY (`franchise_code`, `provider_code`) REFERENCES `transport_provider` (`franchise_code`, `code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_feed_version' AND CONSTRAINT_NAME='fk_transport_version_feed'), 'ALTER TABLE `transport_feed_version` ADD CONSTRAINT `fk_transport_version_feed` FOREIGN KEY (`franchise_code`, `feed_code`) REFERENCES `transport_feed` (`franchise_code`, `code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_sync_run' AND CONSTRAINT_NAME='fk_transport_sync_feed'), 'ALTER TABLE `transport_sync_run` ADD CONSTRAINT `fk_transport_sync_feed` FOREIGN KEY (`franchise_code`, `feed_code`) REFERENCES `transport_feed` (`franchise_code`, `code`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_operator' AND CONSTRAINT_NAME='fk_transport_operator_version'), 'ALTER TABLE `transport_operator` ADD CONSTRAINT `fk_transport_operator_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop' AND CONSTRAINT_NAME='fk_transport_stop_version'), 'ALTER TABLE `transport_stop` ADD CONSTRAINT `fk_transport_stop_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND CONSTRAINT_NAME='fk_transport_route_operator'), 'ALTER TABLE `transport_route` ADD CONSTRAINT `fk_transport_route_operator` FOREIGN KEY (`franchise_code`, `version_id`, `operator_id`) REFERENCES `transport_operator` (`franchise_code`, `version_id`, `external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_route' AND CONSTRAINT_NAME='fk_transport_route_version'), 'ALTER TABLE `transport_route` ADD CONSTRAINT `fk_transport_route_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_service' AND CONSTRAINT_NAME='fk_transport_service_version'), 'ALTER TABLE `transport_service` ADD CONSTRAINT `fk_transport_service_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_service_exception' AND CONSTRAINT_NAME='fk_transport_exception_service'), 'ALTER TABLE `transport_service_exception` ADD CONSTRAINT `fk_transport_exception_service` FOREIGN KEY (`franchise_code`, `version_id`, `service_id`) REFERENCES `transport_service` (`franchise_code`, `version_id`, `external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_service_exception' AND CONSTRAINT_NAME='fk_transport_service_exception_version'), 'ALTER TABLE `transport_service_exception` ADD CONSTRAINT `fk_transport_service_exception_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_shape' AND CONSTRAINT_NAME='fk_transport_shape_version'), 'ALTER TABLE `transport_shape` ADD CONSTRAINT `fk_transport_shape_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND CONSTRAINT_NAME='fk_transport_trip_route'), 'ALTER TABLE `transport_trip` ADD CONSTRAINT `fk_transport_trip_route` FOREIGN KEY (`franchise_code`, `version_id`, `route_id`) REFERENCES `transport_route` (`franchise_code`, `version_id`, `external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND CONSTRAINT_NAME='fk_transport_trip_service'), 'ALTER TABLE `transport_trip` ADD CONSTRAINT `fk_transport_trip_service` FOREIGN KEY (`franchise_code`, `version_id`, `service_id`) REFERENCES `transport_service` (`franchise_code`, `version_id`, `external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_trip' AND CONSTRAINT_NAME='fk_transport_trip_version'), 'ALTER TABLE `transport_trip` ADD CONSTRAINT `fk_transport_trip_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND CONSTRAINT_NAME='fk_transport_stop_time_version'), 'ALTER TABLE `transport_stop_time` ADD CONSTRAINT `fk_transport_stop_time_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND CONSTRAINT_NAME='fk_transport_time_stop'), 'ALTER TABLE `transport_stop_time` ADD CONSTRAINT `fk_transport_time_stop` FOREIGN KEY (`franchise_code`, `version_id`, `stop_id`) REFERENCES `transport_stop` (`franchise_code`, `version_id`, `external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_stop_time' AND CONSTRAINT_NAME='fk_transport_time_trip'), 'ALTER TABLE `transport_stop_time` ADD CONSTRAINT `fk_transport_time_trip` FOREIGN KEY (`franchise_code`, `version_id`, `trip_id`) REFERENCES `transport_trip` (`franchise_code`, `version_id`, `external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_frequency' AND CONSTRAINT_NAME='fk_transport_frequency_trip'), 'ALTER TABLE `transport_frequency` ADD CONSTRAINT `fk_transport_frequency_trip` FOREIGN KEY (`franchise_code`, `version_id`, `trip_id`) REFERENCES `transport_trip` (`franchise_code`, `version_id`, `external_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_frequency' AND CONSTRAINT_NAME='fk_transport_frequency_version'), 'ALTER TABLE `transport_frequency` ADD CONSTRAINT `fk_transport_frequency_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transport_transfer' AND CONSTRAINT_NAME='fk_transport_transfer_version'), 'ALTER TABLE `transport_transfer` ADD CONSTRAINT `fk_transport_transfer_version` FOREIGN KEY (`franchise_code`, `version_id`) REFERENCES `transport_feed_version` (`franchise_code`, `id`) ON DELETE CASCADE', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;
