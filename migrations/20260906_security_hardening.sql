-- Production-safe, idempotent security hardening for every php-core tenant.
-- Additive only: no DROP/TRUNCATE/DELETE and no existing business rows changed.
-- Run this exact file locally first, then against production before deploying code.

SET NAMES utf8mb4;

SET @has_category_published = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'category' AND column_name = 'published'
);
SET @ddl = IF(
  @has_category_published = 0,
  'ALTER TABLE `category` ADD COLUMN `published` TINYINT(1) NOT NULL DEFAULT 1 AFTER `position`',
  'SELECT ''category.published already exists'' AS migration_info'
);
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @has_file_user = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'file' AND column_name = 'user_id'
);
SET @ddl = IF(
  @has_file_user = 0,
  'ALTER TABLE `file` ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `franchise_code`',
  'SELECT ''file.user_id already exists'' AS migration_info'
);
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @has_file_user_index = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'file' AND index_name = 'idx_file_user'
);
SET @ddl = IF(
  @has_file_user_index = 0,
  'ALTER TABLE `file` ADD INDEX `idx_file_user` (`user_id`)',
  'SELECT ''idx_file_user already exists'' AS migration_info'
);
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @has_order_customer = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'order' AND column_name = 'customer'
);
SET @ddl = IF(
  @has_order_customer = 0,
  'ALTER TABLE `order` ADD COLUMN `customer` JSON NULL COMMENT ''guest customer and address snapshot'' AFTER `user_id`',
  'SELECT ''order.customer already exists'' AS migration_info'
);
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

CREATE TABLE IF NOT EXISTS `oauth_identity` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `franchise_code` VARCHAR(64) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `provider` VARCHAR(32) NOT NULL,
  `provider_subject` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_oauth_tenant_provider_subject` (`franchise_code`, `provider`, `provider_subject`),
  KEY `idx_oauth_user` (`user_id`),
  CONSTRAINT `fk_oauth_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_token` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `franchise_code` VARCHAR(64) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`franchise_code`, `user_id`),
  KEY `idx_password_reset_expiry` (`expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_rate_limit` (
  `franchise_code` VARCHAR(64) NOT NULL,
  `action` VARCHAR(64) NOT NULL,
  `subject_hash` CHAR(64) NOT NULL,
  `window_started_at` DATETIME NOT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`franchise_code`, `action`, `subject_hash`, `window_started_at`),
  KEY `idx_rate_limit_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'category' AND column_name = 'published') AS category_published,
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'file' AND column_name = 'user_id') AS file_owner,
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'order' AND column_name = 'customer') AS order_customer,
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('oauth_identity', 'password_reset_token', 'api_rate_limit')) AS security_tables;
