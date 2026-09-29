-- sry_schema: canonical definitions. Apply schema.sql before product schemas.
-- Run python3 scripts/build-schemas.py after editing CREATE definitions.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `sry_family` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sry',
  `owner_user_id` int unsigned NOT NULL,
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Europe/Prague',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_user_id` (`owner_user_id`)
  -- deferred CONSTRAINT `sry_family_ibfk_1` FOREIGN KEY (`owner_user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sry_member` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL,
  `daily_target` int unsigned NOT NULL DEFAULT '100',
  `wifi_allowed` tinyint NOT NULL DEFAULT '1',
  `data_allowed` tinyint NOT NULL DEFAULT '1',
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_family` (`id`,`family_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `family_id` (`family_id`)
  -- deferred CONSTRAINT `sry_member_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `sry_family` (`id`)
  -- deferred CONSTRAINT `sry_member_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sry_session` (
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` int unsigned NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token_hash`),
  KEY `member_id` (`member_id`),
  KEY `session_expiry` (`expires_at`)
  -- deferred CONSTRAINT `sry_session_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `sry_member` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sry_invitation` (
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `family_id` int unsigned NOT NULL,
  `child_id` int unsigned DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`token_hash`),
  KEY `family_id` (`family_id`),
  KEY `child_id` (`child_id`,`family_id`),
  KEY `created_by` (`created_by`)
  -- deferred CONSTRAINT `sry_invitation_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `sry_family` (`id`)
  -- deferred CONSTRAINT `sry_invitation_ibfk_2` FOREIGN KEY (`child_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)
  -- deferred CONSTRAINT `sry_invitation_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `sry_member` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sry',
  `family_id` int unsigned NOT NULL,
  `created_by` int unsigned NOT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` int unsigned NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `enumeration_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_family` (`id`,`family_id`),
  KEY `family_id` (`family_id`),
  KEY `created_by` (`created_by`,`family_id`),
  KEY `category_id` (`category_id`),
  KEY `enumeration_id` (`enumeration_id`)
  -- deferred CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `sry_family` (`id`)
  -- deferred CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)
  -- deferred CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`)
  -- deferred CONSTRAINT `tasks_ibfk_4` FOREIGN KEY (`enumeration_id`) REFERENCES `enumeration` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_assignment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned NOT NULL,
  `task_id` int unsigned NOT NULL,
  `member_id` int unsigned NOT NULL,
  `due_date` date NOT NULL,
  `points` int unsigned NOT NULL,
  `status` enum('assigned','submitted','approved','returned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `revision` int unsigned NOT NULL DEFAULT '1',
  `current_submission_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_family` (`id`,`family_id`),
  KEY `member_day` (`member_id`,`due_date`),
  KEY `family_date` (`family_id`,`due_date`,`id`),
  KEY `task_id` (`task_id`,`family_id`),
  KEY `member_id` (`member_id`,`family_id`)
  -- deferred CONSTRAINT `task_assignment_ibfk_1` FOREIGN KEY (`task_id`, `family_id`) REFERENCES `tasks` (`id`, `family_id`)
  -- deferred CONSTRAINT `task_assignment_ibfk_2` FOREIGN KEY (`member_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sry_media` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned NOT NULL,
  `member_id` int unsigned NOT NULL,
  `object_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `byte_size` int unsigned NOT NULL,
  `state` enum('pending','ready') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_key` (`object_key`),
  UNIQUE KEY `media_family` (`id`,`family_id`),
  KEY `member_id` (`member_id`,`family_id`)
  -- deferred CONSTRAINT `sry_media_ibfk_1` FOREIGN KEY (`member_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_media` (
  `task_id` int unsigned NOT NULL,
  `media_id` int unsigned NOT NULL,
  `family_id` int unsigned NOT NULL,
  PRIMARY KEY (`task_id`,`media_id`),
  KEY `task_id` (`task_id`,`family_id`),
  KEY `media_id` (`media_id`,`family_id`)
  -- deferred CONSTRAINT `task_media_ibfk_1` FOREIGN KEY (`task_id`, `family_id`) REFERENCES `tasks` (`id`, `family_id`)
  -- deferred CONSTRAINT `task_media_ibfk_2` FOREIGN KEY (`media_id`, `family_id`) REFERENCES `sry_media` (`id`, `family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_submission` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int unsigned NOT NULL,
  `family_id` int unsigned NOT NULL,
  `member_id` int unsigned NOT NULL,
  `media_id` int unsigned NOT NULL,
  `note` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `assignment_id` (`assignment_id`,`family_id`),
  KEY `member_id` (`member_id`,`family_id`),
  KEY `media_id` (`media_id`,`family_id`)
  -- deferred CONSTRAINT `task_submission_ibfk_1` FOREIGN KEY (`assignment_id`, `family_id`) REFERENCES `task_assignment` (`id`, `family_id`)
  -- deferred CONSTRAINT `task_submission_ibfk_2` FOREIGN KEY (`member_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)
  -- deferred CONSTRAINT `task_submission_ibfk_3` FOREIGN KEY (`media_id`, `family_id`) REFERENCES `sry_media` (`id`, `family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_review` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` int unsigned NOT NULL,
  `reviewer_id` int unsigned NOT NULL,
  `decision` enum('approved','returned') COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `submission_id` (`submission_id`),
  KEY `reviewer_id` (`reviewer_id`)
  -- deferred CONSTRAINT `task_review_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `task_submission` (`id`)
  -- deferred CONSTRAINT `task_review_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `sry_member` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_points` (
  `assignment_id` int unsigned NOT NULL,
  `member_id` int unsigned NOT NULL,
  `points` int unsigned NOT NULL,
  `earned_on` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`assignment_id`),
  KEY `points_day` (`member_id`,`earned_on`)
  -- deferred CONSTRAINT `task_points_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `task_assignment` (`id`)
  -- deferred CONSTRAINT `task_points_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `sry_member` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sry_notification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int unsigned NOT NULL,
  `event` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int unsigned NOT NULL DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_read` (`member_id`,`read_at`)
  -- deferred CONSTRAINT `sry_notification_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `sry_member` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sry_outbox` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int unsigned NOT NULL,
  `topic` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int unsigned NOT NULL,
  `event` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `realtime_at` datetime DEFAULT NULL,
  `push_at` datetime DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pending_delivery` (`delivered_at`,`attempts`,`id`),
  KEY `member_id` (`member_id`)
  -- deferred CONSTRAINT `sry_outbox_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `sry_member` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sry_push_device` (
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` int unsigned NOT NULL,
  `language` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cs',
  PRIMARY KEY (`token`),
  KEY `member_id` (`member_id`)
  -- deferred CONSTRAINT `sry_push_device_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `sry_member` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sry_chat` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned NOT NULL,
  `sender_id` int unsigned NOT NULL,
  `recipient_id` int unsigned NOT NULL,
  `body` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`,`family_id`),
  KEY `recipient_id` (`recipient_id`,`family_id`),
  KEY `thread_lookup` (`family_id`,`id`)
  -- deferred CONSTRAINT `sry_chat_ibfk_1` FOREIGN KEY (`sender_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)
  -- deferred CONSTRAINT `sry_chat_ibfk_2` FOREIGN KEY (`recipient_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sry_password_reset` (
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`token_hash`),
  KEY `user_id` (`user_id`)
  -- deferred CONSTRAINT `sry_password_reset_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BEGIN GENERATED ADDITIVE DDL (scripts/build-schemas.py)
-- Missing columns first, then indexes and foreign keys. Existing data stay intact.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_family' AND COLUMN_NAME='id'), 'ALTER TABLE `sry_family` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_family' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `sry_family` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''sry''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_family' AND COLUMN_NAME='owner_user_id'), 'ALTER TABLE `sry_family` ADD COLUMN `owner_user_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_family' AND COLUMN_NAME='timezone'), 'ALTER TABLE `sry_family` ADD COLUMN `timezone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''Europe/Prague''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_family' AND COLUMN_NAME='created_at'), 'ALTER TABLE `sry_family` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND COLUMN_NAME='id'), 'ALTER TABLE `sry_member` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND COLUMN_NAME='family_id'), 'ALTER TABLE `sry_member` ADD COLUMN `family_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND COLUMN_NAME='user_id'), 'ALTER TABLE `sry_member` ADD COLUMN `user_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND COLUMN_NAME='name'), 'ALTER TABLE `sry_member` ADD COLUMN `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND COLUMN_NAME='role'), 'ALTER TABLE `sry_member` ADD COLUMN `role` enum(''admin'',''user'') COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND COLUMN_NAME='daily_target'), 'ALTER TABLE `sry_member` ADD COLUMN `daily_target` int unsigned NOT NULL DEFAULT ''100''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND COLUMN_NAME='wifi_allowed'), 'ALTER TABLE `sry_member` ADD COLUMN `wifi_allowed` tinyint NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND COLUMN_NAME='data_allowed'), 'ALTER TABLE `sry_member` ADD COLUMN `data_allowed` tinyint NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND COLUMN_NAME='active'), 'ALTER TABLE `sry_member` ADD COLUMN `active` tinyint NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND COLUMN_NAME='created_at'), 'ALTER TABLE `sry_member` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_session' AND COLUMN_NAME='token_hash'), 'ALTER TABLE `sry_session` ADD COLUMN `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_session' AND COLUMN_NAME='member_id'), 'ALTER TABLE `sry_session` ADD COLUMN `member_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_session' AND COLUMN_NAME='expires_at'), 'ALTER TABLE `sry_session` ADD COLUMN `expires_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_session' AND COLUMN_NAME='created_at'), 'ALTER TABLE `sry_session` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND COLUMN_NAME='token_hash'), 'ALTER TABLE `sry_invitation` ADD COLUMN `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND COLUMN_NAME='family_id'), 'ALTER TABLE `sry_invitation` ADD COLUMN `family_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND COLUMN_NAME='child_id'), 'ALTER TABLE `sry_invitation` ADD COLUMN `child_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND COLUMN_NAME='created_by'), 'ALTER TABLE `sry_invitation` ADD COLUMN `created_by` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND COLUMN_NAME='expires_at'), 'ALTER TABLE `sry_invitation` ADD COLUMN `expires_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND COLUMN_NAME='consumed_at'), 'ALTER TABLE `sry_invitation` ADD COLUMN `consumed_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='id'), 'ALTER TABLE `tasks` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='franchise_code'), 'ALTER TABLE `tasks` ADD COLUMN `franchise_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''sry''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='family_id'), 'ALTER TABLE `tasks` ADD COLUMN `family_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='created_by'), 'ALTER TABLE `tasks` ADD COLUMN `created_by` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='title'), 'ALTER TABLE `tasks` ADD COLUMN `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='description'), 'ALTER TABLE `tasks` ADD COLUMN `description` text COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='points'), 'ALTER TABLE `tasks` ADD COLUMN `points` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='category_id'), 'ALTER TABLE `tasks` ADD COLUMN `category_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='enumeration_id'), 'ALTER TABLE `tasks` ADD COLUMN `enumeration_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND COLUMN_NAME='created_at'), 'ALTER TABLE `tasks` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND COLUMN_NAME='id'), 'ALTER TABLE `task_assignment` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND COLUMN_NAME='family_id'), 'ALTER TABLE `task_assignment` ADD COLUMN `family_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND COLUMN_NAME='task_id'), 'ALTER TABLE `task_assignment` ADD COLUMN `task_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND COLUMN_NAME='member_id'), 'ALTER TABLE `task_assignment` ADD COLUMN `member_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND COLUMN_NAME='due_date'), 'ALTER TABLE `task_assignment` ADD COLUMN `due_date` date NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND COLUMN_NAME='points'), 'ALTER TABLE `task_assignment` ADD COLUMN `points` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND COLUMN_NAME='status'), 'ALTER TABLE `task_assignment` ADD COLUMN `status` enum(''assigned'',''submitted'',''approved'',''returned'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''assigned''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND COLUMN_NAME='revision'), 'ALTER TABLE `task_assignment` ADD COLUMN `revision` int unsigned NOT NULL DEFAULT ''1''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND COLUMN_NAME='current_submission_id'), 'ALTER TABLE `task_assignment` ADD COLUMN `current_submission_id` int unsigned DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND COLUMN_NAME='created_at'), 'ALTER TABLE `task_assignment` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND COLUMN_NAME='id'), 'ALTER TABLE `sry_media` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND COLUMN_NAME='family_id'), 'ALTER TABLE `sry_media` ADD COLUMN `family_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND COLUMN_NAME='member_id'), 'ALTER TABLE `sry_media` ADD COLUMN `member_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND COLUMN_NAME='object_key'), 'ALTER TABLE `sry_media` ADD COLUMN `object_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND COLUMN_NAME='mime'), 'ALTER TABLE `sry_media` ADD COLUMN `mime` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND COLUMN_NAME='byte_size'), 'ALTER TABLE `sry_media` ADD COLUMN `byte_size` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND COLUMN_NAME='state'), 'ALTER TABLE `sry_media` ADD COLUMN `state` enum(''pending'',''ready'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''pending''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND COLUMN_NAME='created_at'), 'ALTER TABLE `sry_media` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_media' AND COLUMN_NAME='task_id'), 'ALTER TABLE `task_media` ADD COLUMN `task_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_media' AND COLUMN_NAME='media_id'), 'ALTER TABLE `task_media` ADD COLUMN `media_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_media' AND COLUMN_NAME='family_id'), 'ALTER TABLE `task_media` ADD COLUMN `family_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND COLUMN_NAME='id'), 'ALTER TABLE `task_submission` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND COLUMN_NAME='assignment_id'), 'ALTER TABLE `task_submission` ADD COLUMN `assignment_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND COLUMN_NAME='family_id'), 'ALTER TABLE `task_submission` ADD COLUMN `family_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND COLUMN_NAME='member_id'), 'ALTER TABLE `task_submission` ADD COLUMN `member_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND COLUMN_NAME='media_id'), 'ALTER TABLE `task_submission` ADD COLUMN `media_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND COLUMN_NAME='note'), 'ALTER TABLE `task_submission` ADD COLUMN `note` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND COLUMN_NAME='created_at'), 'ALTER TABLE `task_submission` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND COLUMN_NAME='id'), 'ALTER TABLE `task_review` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND COLUMN_NAME='submission_id'), 'ALTER TABLE `task_review` ADD COLUMN `submission_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND COLUMN_NAME='reviewer_id'), 'ALTER TABLE `task_review` ADD COLUMN `reviewer_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND COLUMN_NAME='decision'), 'ALTER TABLE `task_review` ADD COLUMN `decision` enum(''approved'',''returned'') COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND COLUMN_NAME='note'), 'ALTER TABLE `task_review` ADD COLUMN `note` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND COLUMN_NAME='created_at'), 'ALTER TABLE `task_review` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_points' AND COLUMN_NAME='assignment_id'), 'ALTER TABLE `task_points` ADD COLUMN `assignment_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_points' AND COLUMN_NAME='member_id'), 'ALTER TABLE `task_points` ADD COLUMN `member_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_points' AND COLUMN_NAME='points'), 'ALTER TABLE `task_points` ADD COLUMN `points` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_points' AND COLUMN_NAME='earned_on'), 'ALTER TABLE `task_points` ADD COLUMN `earned_on` date NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_points' AND COLUMN_NAME='created_at'), 'ALTER TABLE `task_points` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_notification' AND COLUMN_NAME='id'), 'ALTER TABLE `sry_notification` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_notification' AND COLUMN_NAME='member_id'), 'ALTER TABLE `sry_notification` ADD COLUMN `member_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_notification' AND COLUMN_NAME='event'), 'ALTER TABLE `sry_notification` ADD COLUMN `event` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_notification' AND COLUMN_NAME='entity_id'), 'ALTER TABLE `sry_notification` ADD COLUMN `entity_id` int unsigned NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_notification' AND COLUMN_NAME='read_at'), 'ALTER TABLE `sry_notification` ADD COLUMN `read_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_notification' AND COLUMN_NAME='created_at'), 'ALTER TABLE `sry_notification` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND COLUMN_NAME='id'), 'ALTER TABLE `sry_outbox` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND COLUMN_NAME='member_id'), 'ALTER TABLE `sry_outbox` ADD COLUMN `member_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND COLUMN_NAME='topic'), 'ALTER TABLE `sry_outbox` ADD COLUMN `topic` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND COLUMN_NAME='entity_id'), 'ALTER TABLE `sry_outbox` ADD COLUMN `entity_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND COLUMN_NAME='event'), 'ALTER TABLE `sry_outbox` ADD COLUMN `event` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND COLUMN_NAME='delivered_at'), 'ALTER TABLE `sry_outbox` ADD COLUMN `delivered_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND COLUMN_NAME='realtime_at'), 'ALTER TABLE `sry_outbox` ADD COLUMN `realtime_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND COLUMN_NAME='push_at'), 'ALTER TABLE `sry_outbox` ADD COLUMN `push_at` datetime DEFAULT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND COLUMN_NAME='attempts'), 'ALTER TABLE `sry_outbox` ADD COLUMN `attempts` int NOT NULL DEFAULT ''0''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND COLUMN_NAME='created_at'), 'ALTER TABLE `sry_outbox` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_push_device' AND COLUMN_NAME='token'), 'ALTER TABLE `sry_push_device` ADD COLUMN `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_push_device' AND COLUMN_NAME='member_id'), 'ALTER TABLE `sry_push_device` ADD COLUMN `member_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_push_device' AND COLUMN_NAME='language'), 'ALTER TABLE `sry_push_device` ADD COLUMN `language` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''cs''', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND COLUMN_NAME='id'), 'ALTER TABLE `sry_chat` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND COLUMN_NAME='family_id'), 'ALTER TABLE `sry_chat` ADD COLUMN `family_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND COLUMN_NAME='sender_id'), 'ALTER TABLE `sry_chat` ADD COLUMN `sender_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND COLUMN_NAME='recipient_id'), 'ALTER TABLE `sry_chat` ADD COLUMN `recipient_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND COLUMN_NAME='body'), 'ALTER TABLE `sry_chat` ADD COLUMN `body` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND COLUMN_NAME='created_at'), 'ALTER TABLE `sry_chat` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_password_reset' AND COLUMN_NAME='token_hash'), 'ALTER TABLE `sry_password_reset` ADD COLUMN `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_password_reset' AND COLUMN_NAME='user_id'), 'ALTER TABLE `sry_password_reset` ADD COLUMN `user_id` int unsigned NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_password_reset' AND COLUMN_NAME='expires_at'), 'ALTER TABLE `sry_password_reset` ADD COLUMN `expires_at` datetime NOT NULL', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

-- Missing indexes. Conflicting existing rows cause an error, never data removal.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_family' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `sry_family` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_family' AND INDEX_NAME='owner_user_id'), 'ALTER TABLE `sry_family` ADD UNIQUE KEY `owner_user_id` (`owner_user_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `sry_member` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND INDEX_NAME='member_family'), 'ALTER TABLE `sry_member` ADD UNIQUE KEY `member_family` (`id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND INDEX_NAME='user_id'), 'ALTER TABLE `sry_member` ADD UNIQUE KEY `user_id` (`user_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND INDEX_NAME='family_id'), 'ALTER TABLE `sry_member` ADD KEY `family_id` (`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_session' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `sry_session` ADD PRIMARY KEY (`token_hash`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_session' AND INDEX_NAME='member_id'), 'ALTER TABLE `sry_session` ADD KEY `member_id` (`member_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_session' AND INDEX_NAME='session_expiry'), 'ALTER TABLE `sry_session` ADD KEY `session_expiry` (`expires_at`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `sry_invitation` ADD PRIMARY KEY (`token_hash`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND INDEX_NAME='family_id'), 'ALTER TABLE `sry_invitation` ADD KEY `family_id` (`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND INDEX_NAME='child_id'), 'ALTER TABLE `sry_invitation` ADD KEY `child_id` (`child_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND INDEX_NAME='created_by'), 'ALTER TABLE `sry_invitation` ADD KEY `created_by` (`created_by`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `tasks` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND INDEX_NAME='task_family'), 'ALTER TABLE `tasks` ADD UNIQUE KEY `task_family` (`id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND INDEX_NAME='family_id'), 'ALTER TABLE `tasks` ADD KEY `family_id` (`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND INDEX_NAME='created_by'), 'ALTER TABLE `tasks` ADD KEY `created_by` (`created_by`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND INDEX_NAME='category_id'), 'ALTER TABLE `tasks` ADD KEY `category_id` (`category_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND INDEX_NAME='enumeration_id'), 'ALTER TABLE `tasks` ADD KEY `enumeration_id` (`enumeration_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `task_assignment` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND INDEX_NAME='assignment_family'), 'ALTER TABLE `task_assignment` ADD UNIQUE KEY `assignment_family` (`id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND INDEX_NAME='member_day'), 'ALTER TABLE `task_assignment` ADD KEY `member_day` (`member_id`,`due_date`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND INDEX_NAME='family_date'), 'ALTER TABLE `task_assignment` ADD KEY `family_date` (`family_id`,`due_date`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND INDEX_NAME='task_id'), 'ALTER TABLE `task_assignment` ADD KEY `task_id` (`task_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND INDEX_NAME='member_id'), 'ALTER TABLE `task_assignment` ADD KEY `member_id` (`member_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `sry_media` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND INDEX_NAME='object_key'), 'ALTER TABLE `sry_media` ADD UNIQUE KEY `object_key` (`object_key`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND INDEX_NAME='media_family'), 'ALTER TABLE `sry_media` ADD UNIQUE KEY `media_family` (`id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND INDEX_NAME='member_id'), 'ALTER TABLE `sry_media` ADD KEY `member_id` (`member_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_media' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `task_media` ADD PRIMARY KEY (`task_id`,`media_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_media' AND INDEX_NAME='task_id'), 'ALTER TABLE `task_media` ADD KEY `task_id` (`task_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_media' AND INDEX_NAME='media_id'), 'ALTER TABLE `task_media` ADD KEY `media_id` (`media_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `task_submission` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND INDEX_NAME='assignment_id'), 'ALTER TABLE `task_submission` ADD KEY `assignment_id` (`assignment_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND INDEX_NAME='member_id'), 'ALTER TABLE `task_submission` ADD KEY `member_id` (`member_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND INDEX_NAME='media_id'), 'ALTER TABLE `task_submission` ADD KEY `media_id` (`media_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `task_review` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND INDEX_NAME='submission_id'), 'ALTER TABLE `task_review` ADD UNIQUE KEY `submission_id` (`submission_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND INDEX_NAME='reviewer_id'), 'ALTER TABLE `task_review` ADD KEY `reviewer_id` (`reviewer_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_points' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `task_points` ADD PRIMARY KEY (`assignment_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='task_points' AND INDEX_NAME='points_day'), 'ALTER TABLE `task_points` ADD KEY `points_day` (`member_id`,`earned_on`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_notification' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `sry_notification` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_notification' AND INDEX_NAME='member_read'), 'ALTER TABLE `sry_notification` ADD KEY `member_read` (`member_id`,`read_at`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `sry_outbox` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND INDEX_NAME='pending_delivery'), 'ALTER TABLE `sry_outbox` ADD KEY `pending_delivery` (`delivered_at`,`attempts`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND INDEX_NAME='member_id'), 'ALTER TABLE `sry_outbox` ADD KEY `member_id` (`member_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_push_device' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `sry_push_device` ADD PRIMARY KEY (`token`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_push_device' AND INDEX_NAME='member_id'), 'ALTER TABLE `sry_push_device` ADD KEY `member_id` (`member_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `sry_chat` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND INDEX_NAME='sender_id'), 'ALTER TABLE `sry_chat` ADD KEY `sender_id` (`sender_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND INDEX_NAME='recipient_id'), 'ALTER TABLE `sry_chat` ADD KEY `recipient_id` (`recipient_id`,`family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND INDEX_NAME='thread_lookup'), 'ALTER TABLE `sry_chat` ADD KEY `thread_lookup` (`family_id`,`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_password_reset' AND INDEX_NAME='PRIMARY'), 'ALTER TABLE `sry_password_reset` ADD PRIMARY KEY (`token_hash`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sry_password_reset' AND INDEX_NAME='user_id'), 'ALTER TABLE `sry_password_reset` ADD KEY `user_id` (`user_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

-- Missing constraints.
SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_family' AND CONSTRAINT_NAME='sry_family_ibfk_1'), 'ALTER TABLE `sry_family` ADD CONSTRAINT `sry_family_ibfk_1` FOREIGN KEY (`owner_user_id`) REFERENCES `user` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND CONSTRAINT_NAME='sry_member_ibfk_1'), 'ALTER TABLE `sry_member` ADD CONSTRAINT `sry_member_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `sry_family` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_member' AND CONSTRAINT_NAME='sry_member_ibfk_2'), 'ALTER TABLE `sry_member` ADD CONSTRAINT `sry_member_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_session' AND CONSTRAINT_NAME='sry_session_ibfk_1'), 'ALTER TABLE `sry_session` ADD CONSTRAINT `sry_session_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `sry_member` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND CONSTRAINT_NAME='sry_invitation_ibfk_1'), 'ALTER TABLE `sry_invitation` ADD CONSTRAINT `sry_invitation_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `sry_family` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND CONSTRAINT_NAME='sry_invitation_ibfk_2'), 'ALTER TABLE `sry_invitation` ADD CONSTRAINT `sry_invitation_ibfk_2` FOREIGN KEY (`child_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_invitation' AND CONSTRAINT_NAME='sry_invitation_ibfk_3'), 'ALTER TABLE `sry_invitation` ADD CONSTRAINT `sry_invitation_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `sry_member` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND CONSTRAINT_NAME='tasks_ibfk_1'), 'ALTER TABLE `tasks` ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `sry_family` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND CONSTRAINT_NAME='tasks_ibfk_2'), 'ALTER TABLE `tasks` ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND CONSTRAINT_NAME='tasks_ibfk_3'), 'ALTER TABLE `tasks` ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND CONSTRAINT_NAME='tasks_ibfk_4'), 'ALTER TABLE `tasks` ADD CONSTRAINT `tasks_ibfk_4` FOREIGN KEY (`enumeration_id`) REFERENCES `enumeration` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND CONSTRAINT_NAME='task_assignment_ibfk_1'), 'ALTER TABLE `task_assignment` ADD CONSTRAINT `task_assignment_ibfk_1` FOREIGN KEY (`task_id`, `family_id`) REFERENCES `tasks` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_assignment' AND CONSTRAINT_NAME='task_assignment_ibfk_2'), 'ALTER TABLE `task_assignment` ADD CONSTRAINT `task_assignment_ibfk_2` FOREIGN KEY (`member_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_media' AND CONSTRAINT_NAME='sry_media_ibfk_1'), 'ALTER TABLE `sry_media` ADD CONSTRAINT `sry_media_ibfk_1` FOREIGN KEY (`member_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_media' AND CONSTRAINT_NAME='task_media_ibfk_1'), 'ALTER TABLE `task_media` ADD CONSTRAINT `task_media_ibfk_1` FOREIGN KEY (`task_id`, `family_id`) REFERENCES `tasks` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_media' AND CONSTRAINT_NAME='task_media_ibfk_2'), 'ALTER TABLE `task_media` ADD CONSTRAINT `task_media_ibfk_2` FOREIGN KEY (`media_id`, `family_id`) REFERENCES `sry_media` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND CONSTRAINT_NAME='task_submission_ibfk_1'), 'ALTER TABLE `task_submission` ADD CONSTRAINT `task_submission_ibfk_1` FOREIGN KEY (`assignment_id`, `family_id`) REFERENCES `task_assignment` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND CONSTRAINT_NAME='task_submission_ibfk_2'), 'ALTER TABLE `task_submission` ADD CONSTRAINT `task_submission_ibfk_2` FOREIGN KEY (`member_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_submission' AND CONSTRAINT_NAME='task_submission_ibfk_3'), 'ALTER TABLE `task_submission` ADD CONSTRAINT `task_submission_ibfk_3` FOREIGN KEY (`media_id`, `family_id`) REFERENCES `sry_media` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND CONSTRAINT_NAME='task_review_ibfk_1'), 'ALTER TABLE `task_review` ADD CONSTRAINT `task_review_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `task_submission` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_review' AND CONSTRAINT_NAME='task_review_ibfk_2'), 'ALTER TABLE `task_review` ADD CONSTRAINT `task_review_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `sry_member` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_points' AND CONSTRAINT_NAME='task_points_ibfk_1'), 'ALTER TABLE `task_points` ADD CONSTRAINT `task_points_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `task_assignment` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='task_points' AND CONSTRAINT_NAME='task_points_ibfk_2'), 'ALTER TABLE `task_points` ADD CONSTRAINT `task_points_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `sry_member` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_notification' AND CONSTRAINT_NAME='sry_notification_ibfk_1'), 'ALTER TABLE `sry_notification` ADD CONSTRAINT `sry_notification_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `sry_member` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_outbox' AND CONSTRAINT_NAME='sry_outbox_ibfk_1'), 'ALTER TABLE `sry_outbox` ADD CONSTRAINT `sry_outbox_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `sry_member` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_push_device' AND CONSTRAINT_NAME='sry_push_device_ibfk_1'), 'ALTER TABLE `sry_push_device` ADD CONSTRAINT `sry_push_device_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `sry_member` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND CONSTRAINT_NAME='sry_chat_ibfk_1'), 'ALTER TABLE `sry_chat` ADD CONSTRAINT `sry_chat_ibfk_1` FOREIGN KEY (`sender_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_chat' AND CONSTRAINT_NAME='sry_chat_ibfk_2'), 'ALTER TABLE `sry_chat` ADD CONSTRAINT `sry_chat_ibfk_2` FOREIGN KEY (`recipient_id`, `family_id`) REFERENCES `sry_member` (`id`, `family_id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_ddl = IF((SELECT COUNT(*)=0 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sry_password_reset' AND CONSTRAINT_NAME='sry_password_reset_ibfk_1'), 'ALTER TABLE `sry_password_reset` ADD CONSTRAINT `sry_password_reset_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)', 'DO 0');
PREPARE schema_stmt FROM @schema_ddl;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;
