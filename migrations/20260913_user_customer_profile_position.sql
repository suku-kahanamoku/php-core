-- Use the shared ordering column name `position` in user_customer_profile.
-- Safe to run repeatedly. CHANGE COLUMN preserves all assignment values.

SET NAMES utf8mb4;

SET @has_priority_column = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'user_customer_profile'
    AND column_name = 'priority'
);
SET @has_position_column = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'user_customer_profile'
    AND column_name = 'position'
);
SET @sql = IF(
  @has_priority_column = 1 AND @has_position_column = 0,
  'ALTER TABLE `user_customer_profile` CHANGE COLUMN `priority` `position` SMALLINT UNSIGNED NOT NULL DEFAULT 1',
  'SELECT ''user_customer_profile.position already exists'' AS migration_info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CHANGE COLUMN keeps the old index name, so normalize it as well.
SET @has_old_index = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'user_customer_profile'
    AND index_name = 'uq_user_customer_profile_priority'
);
SET @has_new_index = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'user_customer_profile'
    AND index_name = 'uq_user_customer_profile_position'
);
SET @sql = IF(
  @has_old_index > 0 AND @has_new_index = 0,
  'ALTER TABLE `user_customer_profile` RENAME INDEX `uq_user_customer_profile_priority` TO `uq_user_customer_profile_position`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Databases created directly from the final schema already have the column;
-- ensure they also have the expected per-user uniqueness rule.
SET @has_position_column = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'user_customer_profile'
    AND column_name = 'position'
);
SET @has_new_index = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'user_customer_profile'
    AND index_name = 'uq_user_customer_profile_position'
);
SET @sql = IF(
  @has_position_column = 1 AND @has_new_index = 0,
  'ALTER TABLE `user_customer_profile` ADD UNIQUE KEY `uq_user_customer_profile_position` (`user_id`,`position`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
  (SELECT COUNT(*) FROM `user_customer_profile`) AS user_customer_profile_links,
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name = 'user_customer_profile'
     AND column_name = 'position') AS position_column_exists,
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name = 'user_customer_profile'
     AND column_name = 'priority') AS legacy_priority_columns;
