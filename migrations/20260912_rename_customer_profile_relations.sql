-- Clarify the names of the two customer-profile relation tables.
-- Safe to run repeatedly. RENAME TABLE preserves all rows and timestamps.

SET NAMES utf8mb4;

SET @has_old_user_table = (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'user_profile'
);
SET @has_new_user_table = (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'user_customer_profile'
);
SET @sql = IF(
  @has_old_user_table = 1 AND @has_new_user_table = 0,
  'RENAME TABLE `user_profile` TO `user_customer_profile`',
  'SELECT ''user_customer_profile already exists'' AS migration_info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_product_table = (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'product_profile_probability'
);
SET @has_new_product_table = (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'product_customer_profile_probability'
);
SET @sql = IF(
  @has_old_product_table = 1 AND @has_new_product_table = 0,
  'RENAME TABLE `product_profile_probability` TO `product_customer_profile_probability`',
  'SELECT ''product_customer_profile_probability already exists'' AS migration_info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rename indexes left behind by RENAME TABLE.
SET @has_old_index = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'user_customer_profile'
    AND index_name = 'uq_user_profile_priority'
);
SET @sql = IF(@has_old_index > 0,
  'ALTER TABLE `user_customer_profile` RENAME INDEX `uq_user_profile_priority` TO `uq_user_customer_profile_priority`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_index = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'user_customer_profile'
    AND index_name = 'idx_user_profile_tenant'
);
SET @sql = IF(@has_old_index > 0,
  'ALTER TABLE `user_customer_profile` RENAME INDEX `idx_user_profile_tenant` TO `idx_user_customer_profile_tenant`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_index = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'user_customer_profile'
    AND index_name = 'idx_user_profile_profile'
);
SET @sql = IF(@has_old_index > 0,
  'ALTER TABLE `user_customer_profile` RENAME INDEX `idx_user_profile_profile` TO `idx_user_customer_profile_profile`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_index = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'product_customer_profile_probability'
    AND index_name = 'idx_product_profile_tenant'
);
SET @sql = IF(@has_old_index > 0,
  'ALTER TABLE `product_customer_profile_probability` RENAME INDEX `idx_product_profile_tenant` TO `idx_product_customer_profile_tenant`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_index = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'product_customer_profile_probability'
    AND index_name = 'idx_product_profile_profile'
);
SET @sql = IF(@has_old_index > 0,
  'ALTER TABLE `product_customer_profile_probability` RENAME INDEX `idx_product_profile_profile` TO `idx_product_customer_profile_profile`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rename foreign-key constraints for a consistent SHOW CREATE TABLE output.
SET @has_old_constraint = (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE() AND table_name = 'user_customer_profile'
    AND constraint_name = 'fk_user_profile_user'
);
SET @sql = IF(@has_old_constraint = 1,
  'ALTER TABLE `user_customer_profile` DROP FOREIGN KEY `fk_user_profile_user`, ADD CONSTRAINT `fk_user_customer_profile_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_constraint = (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE() AND table_name = 'user_customer_profile'
    AND constraint_name = 'fk_user_profile_profile'
);
SET @sql = IF(@has_old_constraint = 1,
  'ALTER TABLE `user_customer_profile` DROP FOREIGN KEY `fk_user_profile_profile`, ADD CONSTRAINT `fk_user_customer_profile_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_constraint = (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE() AND table_name = 'product_customer_profile_probability'
    AND constraint_name = 'fk_product_profile_product'
);
SET @sql = IF(@has_old_constraint = 1,
  'ALTER TABLE `product_customer_profile_probability` DROP FOREIGN KEY `fk_product_profile_product`, ADD CONSTRAINT `fk_product_customer_profile_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_constraint = (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE() AND table_name = 'product_customer_profile_probability'
    AND constraint_name = 'fk_product_profile_profile'
);
SET @sql = IF(@has_old_constraint = 1,
  'ALTER TABLE `product_customer_profile_probability` DROP FOREIGN KEY `fk_product_profile_profile`, ADD CONSTRAINT `fk_product_customer_profile_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_constraint = (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE() AND table_name = 'product_customer_profile_probability'
    AND constraint_name = 'chk_product_profile_probability'
);
SET @sql = IF(@has_old_constraint = 1,
  'ALTER TABLE `product_customer_profile_probability` DROP CHECK `chk_product_profile_probability`, ADD CONSTRAINT `chk_product_customer_profile_probability` CHECK (`probability_percent` BETWEEN 0 AND 100)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
  (SELECT COUNT(*) FROM `user_customer_profile`) AS user_customer_profile_links,
  (SELECT COUNT(*) FROM `product_customer_profile_probability`) AS product_customer_profile_probability_links,
  (SELECT COUNT(*) FROM information_schema.tables
   WHERE table_schema = DATABASE()
     AND table_name IN ('user_profile', 'product_profile_probability')) AS old_tables_remaining;
