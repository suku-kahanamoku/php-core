-- Replace the FAnn-specific product.kind classification with category relations.
-- Safe to run repeatedly; existing unrelated product categories are preserved.

SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO `category`
  (`franchise_code`, `parent_id`, `syscode`, `name`, `description`, `position`, `published`, `deleted`)
VALUES
  ('fun', NULL, 'perfumes', 'Parfémy', 'Parfémy a parfémované vůně.', 10, 1, 0),
  ('fun', NULL, 'creams-and-care', 'Krémy a péče', 'Krémy, séra, oleje a další péče o pleť a tělo.', 20, 1, 0),
  ('fun', NULL, 'makeup', 'Líčidla', 'Dekorativní kosmetika a líčidla.', 30, 1, 0),
  ('fun', NULL, 'other', 'Ostatní', 'Ostatní produkty.', 40, 1, 0)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `position` = VALUES(`position`),
  `published` = 1,
  `deleted` = 0;

INSERT IGNORE INTO `product_category` (`product_id`, `category_id`)
SELECT
  p.`id`,
  c.`id`
FROM `product` p
JOIN `category` c
  ON c.`franchise_code` = 'fun'
 AND c.`syscode` = CASE p.`kind`
   WHEN 'fragrance' THEN 'perfumes'
   WHEN 'skincare' THEN 'creams-and-care'
   WHEN 'makeup' THEN 'makeup'
   ELSE 'other'
 END
WHERE p.`franchise_code` = 'fun'
  AND p.`deleted` = 0
  AND p.`kind` IS NOT NULL
  AND TRIM(p.`kind`) <> '';

UPDATE `product`
SET `kind` = NULL
WHERE `franchise_code` = 'fun'
  AND `kind` IS NOT NULL;

COMMIT;

SELECT
  (SELECT COUNT(*) FROM `category`
   WHERE `franchise_code` = 'fun'
     AND `syscode` IN ('perfumes', 'creams-and-care', 'makeup', 'other')
     AND `deleted` = 0) AS fun_product_categories,
  (SELECT COUNT(DISTINCT pc.`product_id`)
   FROM `product_category` pc
   JOIN `product` p ON p.`id` = pc.`product_id`
   JOIN `category` c ON c.`id` = pc.`category_id`
   WHERE p.`franchise_code` = 'fun'
     AND p.`deleted` = 0
     AND c.`franchise_code` = 'fun'
     AND c.`syscode` IN ('perfumes', 'creams-and-care', 'makeup', 'other')) AS categorized_fun_products,
  (SELECT COUNT(*) FROM `product`
   WHERE `franchise_code` = 'fun'
     AND `deleted` = 0
     AND `kind` IS NOT NULL) AS fun_products_with_legacy_kind;
