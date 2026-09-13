-- Convert known FAnn product.data.alternative_product_name values to product links.
-- Safe to run repeatedly. Unmatched legacy text remains in product.data.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `product_alternative` (
  `franchise_code` VARCHAR(64) NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `alternative_product_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`, `alternative_product_id`),
  UNIQUE KEY `uq_product_alternative_position` (`product_id`, `position`),
  KEY `idx_product_alternative_tenant` (`franchise_code`),
  KEY `idx_product_alternative_product` (`alternative_product_id`),
  CONSTRAINT `fk_product_alternative_source` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_alternative_target` FOREIGN KEY (`alternative_product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_product_alternative_different` CHECK (`product_id` <> `alternative_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT INTO `product_alternative`
  (`franchise_code`, `product_id`, `alternative_product_id`, `position`)
SELECT 'fun', source_product.`id`, alternative_product.`id`, mapping.`position`
FROM (
  SELECT 'FUN-P001' source_sku, 'FUN-P002' alternative_sku, 1 position UNION ALL
  SELECT 'FUN-P002', 'FUN-P003', 1 UNION ALL
  SELECT 'FUN-P003', 'FUN-P002', 1 UNION ALL
  SELECT 'FUN-P004', 'FUN-P008', 1 UNION ALL
  SELECT 'FUN-P005', 'FUN-P007', 1 UNION ALL
  SELECT 'FUN-P006', 'FUN-P005', 1 UNION ALL
  SELECT 'FUN-P007', 'FUN-P004', 1 UNION ALL
  SELECT 'FUN-P008', 'FUN-P004', 1 UNION ALL
  SELECT 'FUN-P009', 'FUN-P008', 1 UNION ALL
  SELECT 'FUN-P010', 'FUN-P006', 1 UNION ALL
  SELECT 'FUN-P011', 'FUN-P018', 1 UNION ALL
  SELECT 'FUN-P012', 'FUN-P017', 1 UNION ALL
  SELECT 'FUN-P013', 'FUN-P015', 1 UNION ALL
  SELECT 'FUN-P014', 'FUN-P019', 1 UNION ALL
  SELECT 'FUN-P015', 'FUN-P012', 1 UNION ALL
  SELECT 'FUN-P016', 'FUN-P017', 1 UNION ALL
  SELECT 'FUN-P017', 'FUN-P016', 1 UNION ALL
  SELECT 'FUN-P018', 'FUN-P011', 1 UNION ALL
  SELECT 'FUN-P019', 'FUN-P014', 1 UNION ALL
  SELECT 'FUN-P020', 'FUN-P013', 1 UNION ALL
  SELECT 'FUN-P021', 'FUN-P030', 1 UNION ALL
  SELECT 'FUN-P022', 'FUN-P024', 1 UNION ALL
  SELECT 'FUN-P023', 'FUN-P025', 1 UNION ALL
  SELECT 'FUN-P024', 'FUN-P022', 1 UNION ALL
  SELECT 'FUN-P025', 'FUN-P023', 1 UNION ALL
  SELECT 'FUN-P029', 'FUN-P027', 1 UNION ALL
  SELECT 'FUN-P030', 'FUN-P021', 1
) mapping
JOIN `product` source_product
  ON source_product.`franchise_code` = 'fun'
 AND source_product.`sku` = mapping.`source_sku`
 AND source_product.`deleted` = 0
JOIN `product` alternative_product
  ON alternative_product.`franchise_code` = 'fun'
 AND alternative_product.`sku` = mapping.`alternative_sku`
 AND alternative_product.`deleted` = 0
ON DUPLICATE KEY UPDATE `position` = VALUES(`position`);

UPDATE `product` product
SET product.`data` = JSON_REMOVE(product.`data`, '$.alternative_product_name')
WHERE product.`franchise_code` = 'fun'
  AND JSON_CONTAINS_PATH(product.`data`, 'one', '$.alternative_product_name')
  AND EXISTS (
    SELECT 1 FROM `product_alternative` alternative
    WHERE alternative.`franchise_code` = product.`franchise_code`
      AND alternative.`product_id` = product.`id`
  );

COMMIT;

SELECT
  (SELECT COUNT(*) FROM `product_alternative` WHERE `franchise_code` = 'fun') AS fun_alternative_links,
  (SELECT COUNT(*) FROM `product`
   WHERE `franchise_code` = 'fun'
     AND JSON_CONTAINS_PATH(`data`, 'one', '$.alternative_product_name')) AS unmatched_legacy_alternative_names;
