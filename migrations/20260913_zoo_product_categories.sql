-- Replace the Zoo-specific product.kind classification with category relations.
-- Safe to run repeatedly; existing animal and product category links are preserved.

SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO `category`
  (`franchise_code`, `parent_id`, `syscode`, `name`, `description`, `position`, `published`, `deleted`)
VALUES
  ('zoo', NULL, 'product-food', 'Krmivo', 'Krmiva pro všechna zvířata.', 100, 1, 0),
  ('zoo', NULL, 'product-treats', 'Pamlsky', 'Pamlsky a odměny pro zvířata.', 110, 1, 0),
  ('zoo', NULL, 'product-toys', 'Hračky', 'Hračky a pomůcky pro zabavení zvířat.', 120, 1, 0),
  ('zoo', NULL, 'product-hygiene', 'Hygiena', 'Hygienické potřeby, steliva a péče.', 130, 1, 0),
  ('zoo', NULL, 'product-equipment', 'Chovatelské potřeby', 'Vybavení a chovatelské potřeby.', 140, 1, 0),
  ('zoo', NULL, 'product-supplements', 'Doplňky stravy', 'Vitamíny, minerály a další doplňky stravy.', 150, 1, 0),
  ('zoo', NULL, 'product-other', 'Ostatní', 'Ostatní produkty.', 160, 1, 0)
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
  ON c.`franchise_code` = 'zoo'
 AND c.`syscode` = CASE p.`kind`
   WHEN 'food' THEN 'product-food'
   WHEN 'treats' THEN 'product-treats'
   WHEN 'toys' THEN 'product-toys'
   WHEN 'hygiene' THEN 'product-hygiene'
   WHEN 'equipment' THEN 'product-equipment'
   WHEN 'supplements' THEN 'product-supplements'
   ELSE 'product-other'
 END
WHERE p.`franchise_code` = 'zoo'
  AND p.`deleted` = 0
  AND p.`kind` IS NOT NULL
  AND TRIM(p.`kind`) <> '';

UPDATE `product`
SET `kind` = NULL
WHERE `franchise_code` = 'zoo'
  AND `kind` IS NOT NULL;

COMMIT;

SELECT
  (SELECT COUNT(*) FROM `category`
   WHERE `franchise_code` = 'zoo'
     AND `syscode` IN ('product-food', 'product-treats', 'product-toys', 'product-hygiene', 'product-equipment', 'product-supplements', 'product-other')
     AND `deleted` = 0) AS zoo_product_categories,
  (SELECT COUNT(DISTINCT pc.`product_id`)
   FROM `product_category` pc
   JOIN `product` p ON p.`id` = pc.`product_id`
   JOIN `category` c ON c.`id` = pc.`category_id`
   WHERE p.`franchise_code` = 'zoo'
     AND p.`deleted` = 0
     AND c.`franchise_code` = 'zoo'
     AND c.`syscode` IN ('product-food', 'product-treats', 'product-toys', 'product-hygiene', 'product-equipment', 'product-supplements', 'product-other')) AS categorized_zoo_products,
  (SELECT COUNT(*) FROM `product`
   WHERE `franchise_code` = 'zoo'
     AND `deleted` = 0
     AND `kind` IS NOT NULL) AS zoo_products_with_legacy_kind;
