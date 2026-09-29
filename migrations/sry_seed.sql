-- sry bootstrap/reference data, consolidated through 2026-09-28.
-- Insert missing records only; preserve passwords, edits and deleted tombstones.
-- IDs use tenant/natural keys, never fixed AUTO_INCREMENT values.
SET NAMES utf8mb4;
START TRANSACTION;

-- role
INSERT INTO `role` (`franchise_code`,`name`,`label`,`position`,`deleted`)
SELECT 'sry','user','SRY account',0,0
WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE `franchise_code`='sry' AND `name`='user');

-- category
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'sry',NULL,'home','Domácí práce',NULL,0,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='sry' AND `syscode`='home');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'sry',NULL,'outdoor','Venkovní práce',NULL,0,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='sry' AND `syscode`='outdoor');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'sry',NULL,'school','Škola',NULL,0,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='sry' AND `syscode`='school');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'sry',NULL,'clubs','Kroužky',NULL,0,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='sry' AND `syscode`='clubs');

COMMIT;
