-- Every existing product currently reports stock_quantity = 0, so the AI
-- catalog tool (search_products) tells customers everything is sold out.
-- Gives each zero-stock product a random but plausible quantity across all
-- tenants. Safe to run repeatedly: only rows still at 0 are touched, so
-- stock already adjusted manually or via later orders is left alone.

SET NAMES utf8mb4;

START TRANSACTION;

UPDATE `product`
SET `stock_quantity` = FLOOR(5 + RAND() * 45)
WHERE `stock_quantity` = 0
  AND `deleted` = 0;

COMMIT;

SELECT
  `franchise_code`,
  COUNT(*)              AS products,
  MIN(`stock_quantity`) AS min_stock,
  MAX(`stock_quantity`) AS max_stock
FROM `product`
WHERE `deleted` = 0
GROUP BY `franchise_code`;
