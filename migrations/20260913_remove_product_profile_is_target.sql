-- A product/profile relation is defined only by its purchase probability.
-- Safe to run repeatedly on an existing database.

SET @has_is_target = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'product_customer_profile_probability'
    AND column_name = 'is_target'
);

SET @sql = IF(
  @has_is_target > 0,
  'ALTER TABLE `product_customer_profile_probability` DROP COLUMN `is_target`',
  'SELECT ''product_customer_profile_probability.is_target already removed'' AS migration_info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT
  (SELECT COUNT(*)
   FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name = 'product_customer_profile_probability'
     AND column_name = 'is_target') AS is_target_columns,
  (SELECT COUNT(*) FROM product_customer_profile_probability) AS probability_links;
