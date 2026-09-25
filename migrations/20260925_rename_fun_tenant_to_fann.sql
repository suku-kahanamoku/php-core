-- Rename the legacy FAnn tenant code `fun` to the canonical `fann`.
-- Safe to run repeatedly: after the first successful run no `fun` rows remain.
-- Run only after the earlier FAnn seed, catalog and OpenAI migrations.

SET NAMES utf8mb4;
START TRANSACTION;

-- Legacy seed products used a matching misspelled SKU prefix.
UPDATE `product`
SET `sku` = CONCAT('FANN-', SUBSTRING(`sku`, 5))
WHERE `franchise_code` = 'fun'
  AND `sku` LIKE 'FUN-P%';

UPDATE `enumeration` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `customer_profile` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `role` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `user` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `user_customer_profile` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `address` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `category` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `product` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `product_customer_profile_probability` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `product_alternative` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `openai_vector_store` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `openai_vector_store_product` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `text` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `order` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `invoice` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `file` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `oauth_identity` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `password_reset_token` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';
UPDATE `api_rate_limit` SET `franchise_code` = 'fann' WHERE `franchise_code` = 'fun';

COMMIT;

SELECT
  (SELECT COUNT(*) FROM `user` WHERE `franchise_code` = 'fann') AS fann_users,
  (SELECT COUNT(*) FROM `customer_profile` WHERE `franchise_code` = 'fann') AS fann_profiles,
  (SELECT COUNT(*) FROM `product` WHERE `franchise_code` = 'fann') AS fann_products,
  (SELECT COUNT(*) FROM `product` WHERE `franchise_code` = 'fann' AND `sku` LIKE 'FANN-P%') AS renamed_seed_products,
  (
    (SELECT COUNT(*) FROM `enumeration` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `customer_profile` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `role` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `user` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `user_customer_profile` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `address` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `category` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `product` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `product_customer_profile_probability` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `product_alternative` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `openai_vector_store` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `openai_vector_store_product` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `text` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `order` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `invoice` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `file` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `oauth_identity` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `password_reset_token` WHERE `franchise_code` = 'fun') +
    (SELECT COUNT(*) FROM `api_rate_limit` WHERE `franchise_code` = 'fun')
  ) AS remaining_legacy_rows;
