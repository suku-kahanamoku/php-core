-- Normalized customer profiles for every php-core tenant.
-- Safe to run repeatedly. Legacy enumeration/user/product JSON data is copied
-- before the obsolete user columns and client_type enumerations are removed.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `customer_profile` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `franchise_code` VARCHAR(64) NOT NULL,
  `profile_number` SMALLINT UNSIGNED DEFAULT NULL,
  `syscode` VARCHAR(64) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `selection_need` TEXT DEFAULT NULL,
  `summary` TEXT DEFAULT NULL,
  `aura` TEXT DEFAULT NULL,
  `visual` TEXT DEFAULT NULL,
  `behavior` TEXT DEFAULT NULL,
  `business_potential` TEXT DEFAULT NULL,
  `typical_quote` TEXT DEFAULT NULL,
  `average_basket` DECIMAL(12,2) DEFAULT NULL,
  `marketing_note` TEXT DEFAULT NULL,
  `position` SMALLINT NOT NULL DEFAULT 0,
  `published` TINYINT(1) NOT NULL DEFAULT 1,
  `deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_profile_tenant_syscode` (`franchise_code`,`syscode`),
  UNIQUE KEY `uq_customer_profile_tenant_number` (`franchise_code`,`profile_number`),
  KEY `idx_customer_profile_tenant` (`franchise_code`),
  KEY `idx_customer_profile_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_profile_question` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_profile_id` INT UNSIGNED NOT NULL,
  `question` TEXT NOT NULL,
  `position` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_profile_question_position` (`customer_profile_id`,`position`),
  CONSTRAINT `fk_customer_profile_question_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_profile_objection` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_profile_id` INT UNSIGNED NOT NULL,
  `objection` TEXT NOT NULL,
  `position` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_profile_objection_position` (`customer_profile_id`,`position`),
  CONSTRAINT `fk_customer_profile_objection_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_profile_preference` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_profile_id` INT UNSIGNED NOT NULL,
  `preference_type` ENUM('animal','product_kind') NOT NULL,
  `value` VARCHAR(100) NOT NULL,
  `position` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`),
  CONSTRAINT `fk_customer_profile_preference_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_profile` (
  `franchise_code` VARCHAR(64) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `customer_profile_id` INT UNSIGNED NOT NULL,
  `priority` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`customer_profile_id`),
  UNIQUE KEY `uq_user_profile_priority` (`user_id`,`priority`),
  KEY `idx_user_profile_tenant` (`franchise_code`),
  KEY `idx_user_profile_profile` (`customer_profile_id`),
  CONSTRAINT `fk_user_profile_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_profile_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_profile_probability` (
  `franchise_code` VARCHAR(64) NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `customer_profile_id` INT UNSIGNED NOT NULL,
  `probability_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_target` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`,`customer_profile_id`),
  KEY `idx_product_profile_tenant` (`franchise_code`),
  KEY `idx_product_profile_profile` (`customer_profile_id`),
  CONSTRAINT `fk_product_profile_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_profile_profile` FOREIGN KEY (`customer_profile_id`) REFERENCES `customer_profile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_product_profile_probability` CHECK (`probability_percent` BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copy definitions from the legacy client_type enumeration.
INSERT INTO `customer_profile`
  (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`position`,`published`,`deleted`)
SELECT e.`franchise_code`,
       COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(e.`data`, '$.profile_number')) AS UNSIGNED), e.`position` DIV 10),
       e.`syscode`, e.`label`, NULLIF(e.`value`, ''), e.`position`, e.`published`, e.`deleted`
FROM `enumeration` e
WHERE e.`type` = 'client_type'
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`), `selection_need` = VALUES(`selection_need`),
  `position` = VALUES(`position`), `published` = VALUES(`published`), `deleted` = VALUES(`deleted`);

INSERT INTO `customer_profile_question` (`customer_profile_id`,`question`,`position`)
SELECT cp.`id`, jt.`question`, jt.`ord` * 10
FROM `enumeration` e
JOIN `customer_profile` cp ON cp.`franchise_code` = e.`franchise_code` AND cp.`syscode` = e.`syscode`
JOIN JSON_TABLE(COALESCE(JSON_EXTRACT(e.`data`, '$.questions'), JSON_ARRAY()), '$[*]'
  COLUMNS (`ord` FOR ORDINALITY, `question` TEXT PATH '$')) jt
WHERE e.`type` = 'client_type'
ON DUPLICATE KEY UPDATE `question` = VALUES(`question`);

INSERT INTO `customer_profile_objection` (`customer_profile_id`,`objection`,`position`)
SELECT cp.`id`, jt.`objection`, jt.`ord` * 10
FROM `enumeration` e
JOIN `customer_profile` cp ON cp.`franchise_code` = e.`franchise_code` AND cp.`syscode` = e.`syscode`
JOIN JSON_TABLE(COALESCE(JSON_EXTRACT(e.`data`, '$.typical_objections'), JSON_ARRAY()), '$[*]'
  COLUMNS (`ord` FOR ORDINALITY, `objection` TEXT PATH '$')) jt
WHERE e.`type` = 'client_type'
ON DUPLICATE KEY UPDATE `objection` = VALUES(`objection`);

-- Zoo stored rich archetype data on a sample user. Copy it to the profile once.
SET @has_user_profile = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='user' AND column_name='profile');
SET @has_user_client_type = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='user' AND column_name='client_type_id');
SET @sql = IF(@has_user_profile > 0 AND @has_user_client_type > 0,
  'UPDATE customer_profile cp JOIN enumeration e ON e.franchise_code=cp.franchise_code AND e.syscode=cp.syscode AND e.type=''client_type'' JOIN user u ON u.client_type_id=e.id AND u.franchise_code=cp.franchise_code SET cp.summary=COALESCE(JSON_UNQUOTE(JSON_EXTRACT(u.profile,''$.summary'')),cp.summary), cp.aura=COALESCE(JSON_UNQUOTE(JSON_EXTRACT(u.profile,''$.aura'')),cp.aura), cp.visual=COALESCE(JSON_UNQUOTE(JSON_EXTRACT(u.profile,''$.visual'')),cp.visual), cp.behavior=COALESCE(JSON_UNQUOTE(JSON_EXTRACT(u.profile,''$.behavior'')),cp.behavior), cp.business_potential=COALESCE(JSON_UNQUOTE(JSON_EXTRACT(u.profile,''$.business_potential'')),cp.business_potential), cp.typical_quote=COALESCE(JSON_UNQUOTE(JSON_EXTRACT(u.profile,''$.typical_quote'')),cp.typical_quote), cp.average_basket=COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(u.profile,''$.average_basket'')) AS DECIMAL(12,2)),cp.average_basket), cp.marketing_note=COALESCE(JSON_UNQUOTE(JSON_EXTRACT(u.profile,''$.marketing_note'')),cp.marketing_note) WHERE u.profile IS NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_user_profile > 0 AND @has_user_client_type > 0,
  'INSERT INTO customer_profile_preference (customer_profile_id,preference_type,value,position) SELECT cp.id,''animal'',jt.value,jt.ord*10 FROM user u JOIN enumeration e ON e.id=u.client_type_id JOIN customer_profile cp ON cp.franchise_code=u.franchise_code AND cp.syscode=e.syscode JOIN JSON_TABLE(COALESCE(JSON_EXTRACT(u.profile,''$.preferred_animals''),JSON_ARRAY()),''$[*]'' COLUMNS(ord FOR ORDINALITY,value VARCHAR(100) PATH ''$'')) jt WHERE u.profile IS NOT NULL ON DUPLICATE KEY UPDATE position=VALUES(position)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_user_profile > 0 AND @has_user_client_type > 0,
  'INSERT INTO customer_profile_preference (customer_profile_id,preference_type,value,position) SELECT cp.id,''product_kind'',jt.value,jt.ord*10 FROM user u JOIN enumeration e ON e.id=u.client_type_id JOIN customer_profile cp ON cp.franchise_code=u.franchise_code AND cp.syscode=e.syscode JOIN JSON_TABLE(COALESCE(JSON_EXTRACT(u.profile,''$.preferred_product_kinds''),JSON_ARRAY()),''$[*]'' COLUMNS(ord FOR ORDINALITY,value VARCHAR(100) PATH ''$'')) jt WHERE u.profile IS NOT NULL ON DUPLICATE KEY UPDATE position=VALUES(position)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Copy the legacy one-profile user assignment as priority 1.
SET @sql = IF(@has_user_client_type > 0,
  'INSERT INTO user_profile (franchise_code,user_id,customer_profile_id,priority) SELECT u.franchise_code,u.id,cp.id,1 FROM user u JOIN enumeration e ON e.id=u.client_type_id AND e.franchise_code=u.franchise_code AND e.type=''client_type'' JOIN customer_profile cp ON cp.franchise_code=u.franchise_code AND cp.syscode=e.syscode WHERE u.client_type_id IS NOT NULL ON DUPLICATE KEY UPDATE priority=VALUES(priority)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FAnn probabilities, one row for every product/profile pair in the JSON map.
INSERT INTO `product_profile_probability`
  (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`,`is_target`)
SELECT p.`franchise_code`, p.`id`, cp.`id`,
       LEAST(100, GREATEST(0, CAST(JSON_UNQUOTE(JSON_EXTRACT(p.`data`, CONCAT('$.purchase_probability.', jt.`syscode`))) AS UNSIGNED))),
       IF(JSON_CONTAINS(COALESCE(JSON_EXTRACT(p.`data`, '$.target_segments'), JSON_ARRAY()), JSON_QUOTE(jt.`syscode`)), 1, 0)
FROM `product` p
JOIN JSON_TABLE(JSON_KEYS(COALESCE(JSON_EXTRACT(p.`data`, '$.purchase_probability'), JSON_OBJECT())), '$[*]'
  COLUMNS (`syscode` VARCHAR(64) PATH '$')) jt
JOIN `customer_profile` cp ON cp.`franchise_code`=p.`franchise_code` AND cp.`syscode`=jt.`syscode` COLLATE utf8mb4_unicode_ci
WHERE 1
ON DUPLICATE KEY UPDATE `probability_percent`=VALUES(`probability_percent`), `is_target`=VALUES(`is_target`);

-- Zoo target segments and recommended SKU lists have no numeric probability.
-- A target is represented by 100 %, which keeps the relation explicit and editable.
INSERT INTO `product_profile_probability`
  (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`,`is_target`)
SELECT p.`franchise_code`, p.`id`, cp.`id`, 100, 1
FROM `product` p
JOIN JSON_TABLE(COALESCE(JSON_EXTRACT(p.`data`, '$.target_segments'), JSON_ARRAY()), '$[*]'
  COLUMNS (`syscode` VARCHAR(64) PATH '$')) jt
JOIN `customer_profile` cp ON cp.`franchise_code`=p.`franchise_code` AND cp.`syscode`=jt.`syscode` COLLATE utf8mb4_unicode_ci
WHERE NOT JSON_CONTAINS_PATH(p.`data`, 'one', '$.purchase_probability')
ON DUPLICATE KEY UPDATE `probability_percent`=VALUES(`probability_percent`), `is_target`=1;

SET @sql = IF(@has_user_profile > 0 AND @has_user_client_type > 0,
  'INSERT INTO product_profile_probability (franchise_code,product_id,customer_profile_id,probability_percent,is_target) SELECT p.franchise_code,p.id,cp.id,100,1 FROM user u JOIN enumeration e ON e.id=u.client_type_id JOIN customer_profile cp ON cp.franchise_code=u.franchise_code AND cp.syscode=e.syscode JOIN JSON_TABLE(COALESCE(JSON_EXTRACT(u.profile,''$.recommended_product_skus''),JSON_ARRAY()),''$[*]'' COLUMNS(sku VARCHAR(64) PATH ''$'')) jt JOIN product p ON p.franchise_code=u.franchise_code AND p.sku=jt.sku COLLATE utf8mb4_unicode_ci WHERE u.profile IS NOT NULL ON DUPLICATE KEY UPDATE probability_percent=GREATEST(probability_percent,100),is_target=1',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `product`
SET `data` = JSON_REMOVE(`data`, '$.purchase_probability', '$.target_segments')
WHERE JSON_CONTAINS_PATH(`data`, 'one', '$.purchase_probability', '$.target_segments');

DELETE FROM `enumeration` WHERE `type` = 'client_type';

SET @has_index = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='user' AND index_name='idx_user_client_type_id');
SET @sql = IF(@has_index > 0, 'ALTER TABLE `user` DROP INDEX `idx_user_client_type_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_user_client_type > 0, 'ALTER TABLE `user` DROP COLUMN `client_type_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_user_profile > 0, 'ALTER TABLE `user` DROP COLUMN `profile`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

SELECT
  (SELECT COUNT(*) FROM customer_profile WHERE deleted=0) AS profiles,
  (SELECT COUNT(*) FROM customer_profile_question) AS questions,
  (SELECT COUNT(*) FROM customer_profile_objection) AS objections,
  (SELECT COUNT(*) FROM user_profile) AS user_profile_links,
  (SELECT COUNT(*) FROM product_profile_probability) AS product_profile_links;
