-- Production-safe, idempotent migration for the Zoo CRM tenant.
--
-- This migration assumes that the existing php-core schema is already present.
-- It never drops tables or existing tenant data. It only:
--   1. adds the two Zoo CRM columns and their index when missing,
--   2. creates the required Zoo roles and client-type enumerations,
--   3. creates a bootstrap Zoo administrator when one does not exist.
--
-- Before running this file, set a bcrypt password hash in the SAME MySQL
-- session. The e-mail can be overridden as well:
--
--   SET @zoo_admin_email = 'admin@example.cz';
--   SET @zoo_admin_password_hash = '$2y$12$...';
--   SOURCE migrations/20260906_zoo_production.sql;
--
-- Generate the hash outside MySQL without putting the password into shell
-- history, for example:
--   read -rsp 'Zoo admin password: ' ZOO_ADMIN_PASSWORD
--   printf '%s' "$ZOO_ADMIN_PASSWORD" | php -r '$p = stream_get_contents(STDIN); echo password_hash($p, PASSWORD_BCRYPT, ["cost" => 12]), PHP_EOL;'
--   unset ZOO_ADMIN_PASSWORD
--
-- If the password hash is omitted, the administrator is created with an
-- intentionally unusable password and the final verification reports it.

SET NAMES utf8mb4;

SET @zoo_admin_email = COALESCE(NULLIF(@zoo_admin_email, ''), 'admin@zoo.invalid');
SET @zoo_admin_password_hash = COALESCE(
  NULLIF(@zoo_admin_password_hash, ''),
  '!ZOO_ADMIN_PASSWORD_NOT_CONFIGURED!'
);

-- --------------------------------------------------------------------------
-- Schema upgrade
-- MySQL DDL commits implicitly, therefore make a database backup first.
-- --------------------------------------------------------------------------

SET @zoo_has_client_type = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'user'
    AND column_name = 'client_type_id'
);
SET @zoo_ddl = IF(
  @zoo_has_client_type = 0,
  'ALTER TABLE `user` ADD COLUMN `client_type_id` INT UNSIGNED NULL COMMENT ''logical FK to enumeration.id (type client_type)'' AFTER `phone`',
  'SELECT ''user.client_type_id already exists'' AS migration_info'
);
PREPARE zoo_stmt FROM @zoo_ddl;
EXECUTE zoo_stmt;
DEALLOCATE PREPARE zoo_stmt;

SET @zoo_has_profile = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'user'
    AND column_name = 'profile'
);
SET @zoo_ddl = IF(
  @zoo_has_profile = 0,
  'ALTER TABLE `user` ADD COLUMN `profile` JSON NULL COMMENT ''CRM customer profile and recommendations'' AFTER `client_type_id`',
  'SELECT ''user.profile already exists'' AS migration_info'
);
PREPARE zoo_stmt FROM @zoo_ddl;
EXECUTE zoo_stmt;
DEALLOCATE PREPARE zoo_stmt;

SET @zoo_has_client_type_index = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'user'
    AND index_name = 'idx_user_client_type_id'
);
SET @zoo_ddl = IF(
  @zoo_has_client_type_index = 0,
  'ALTER TABLE `user` ADD INDEX `idx_user_client_type_id` (`client_type_id`)',
  'SELECT ''idx_user_client_type_id already exists'' AS migration_info'
);
PREPARE zoo_stmt FROM @zoo_ddl;
EXECUTE zoo_stmt;
DEALLOCATE PREPARE zoo_stmt;

-- --------------------------------------------------------------------------
-- Required tenant data
-- --------------------------------------------------------------------------

START TRANSACTION;

INSERT INTO `role` (`franchise_code`, `name`, `label`, `position`)
VALUES
  ('zoo', 'admin', 'Administrátor', 10),
  ('zoo', 'manager', 'Manažer', 20),
  ('zoo', 'user', 'Klient', 30)
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `position` = VALUES(`position`),
  `deleted` = 0;

INSERT INTO `enumeration`
  (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`, `published`, `data`)
VALUES
  ('zoo', 'client_type', 'premium_shopper', 'Rozmaznávač', 'Prémiový nakupující', 10, 1,
   JSON_OBJECT('color', 'emerald', 'icon', 'sparkles')),
  ('zoo', 'client_type', 'beginner_aquarist', 'Zmatený prvoakvarista', 'Impulzivní začátečník', 20, 1,
   JSON_OBJECT('color', 'blue', 'icon', 'waves')),
  ('zoo', 'client_type', 'professional_breeder', 'Profesionální chovatel', 'Expert a specialista', 30, 1,
   JSON_OBJECT('color', 'violet', 'icon', 'badge-check')),
  ('zoo', 'client_type', 'puppy_rescuer', 'Záchranář se štěnětem', 'Řešitel krizových situací', 40, 1,
   JSON_OBJECT('color', 'orange', 'icon', 'heart-handshake')),
  ('zoo', 'client_type', 'family_visitor', 'Sobotní tatínek', 'Rekreační rodinný návštěvník', 50, 1,
   JSON_OBJECT('color', 'amber', 'icon', 'users')),
  ('zoo', 'client_type', 'terrarium_specialist', 'Pan terarista', 'Specializovaný nákupčí', 60, 1,
   JSON_OBJECT('color', 'lime', 'icon', 'bug')),
  ('zoo', 'client_type', 'cat_loyalist', 'Babička s kočičím královstvím', 'Věrný vztahový zákazník', 70, 1,
   JSON_OBJECT('color', 'pink', 'icon', 'heart')),
  ('zoo', 'client_type', 'experiential_tester', 'Tester všeho', 'Zážitkový zákazník', 80, 1,
   JSON_OBJECT('color', 'cyan', 'icon', 'party-popper')),
  ('zoo', 'client_type', 'discount_specialist', 'Lovec slev', 'Cenově citlivý věrnostní specialista', 90, 1,
   JSON_OBJECT('color', 'red', 'icon', 'badge-percent')),
  ('zoo', 'client_type', 'community_rescuer', 'Záchranář z balkonu', 'Objemový komunitní nakupující', 100, 1,
   JSON_OBJECT('color', 'teal', 'icon', 'bird'))
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `value` = VALUES(`value`),
  `position` = VALUES(`position`),
  `published` = 1,
  `data` = VALUES(`data`),
  `deleted` = 0;

SET @zoo_admin_role_id = (
  SELECT `id`
  FROM `role`
  WHERE `franchise_code` = 'zoo'
    AND `name` = 'admin'
  LIMIT 1
);

-- Do not overwrite an existing administrator or its password.
INSERT INTO `user`
  (`franchise_code`, `first_name`, `last_name`, `email`, `password`, `role_id`, `status`)
SELECT
  'zoo', 'Zoo', 'Admin', @zoo_admin_email,
  @zoo_admin_password_hash, @zoo_admin_role_id, 'active'
WHERE NOT EXISTS (
  SELECT 1
  FROM `user`
  WHERE `franchise_code` = 'zoo'
    AND `role_id` = @zoo_admin_role_id
)
ON DUPLICATE KEY UPDATE
  `id` = `id`;

-- Allow a safe second run with a real hash after an earlier run created the
-- intentionally locked bootstrap account. Other passwords are never changed.
UPDATE `user`
SET `password` = @zoo_admin_password_hash
WHERE `franchise_code` = 'zoo'
  AND `role_id` = @zoo_admin_role_id
  AND `password` = '!ZOO_ADMIN_PASSWORD_NOT_CONFIGURED!'
  AND @zoo_admin_password_hash <> '!ZOO_ADMIN_PASSWORD_NOT_CONFIGURED!';

COMMIT;

-- --------------------------------------------------------------------------
-- Verification summary
-- admin_password_ready = 0 means the migration was run without a supplied
-- bcrypt hash and the newly created bootstrap administrator cannot log in.
-- --------------------------------------------------------------------------

SELECT
  (SELECT COUNT(*)
   FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name = 'user'
     AND column_name IN ('client_type_id', 'profile')) AS zoo_columns,
  (SELECT COUNT(*)
   FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name = 'user'
     AND index_name = 'idx_user_client_type_id') AS zoo_indexes,
  (SELECT COUNT(*)
   FROM `role`
   WHERE `franchise_code` = 'zoo'
     AND `name` IN ('admin', 'manager', 'user')
     AND `deleted` = 0) AS zoo_roles,
  (SELECT COUNT(*)
   FROM `enumeration`
   WHERE `franchise_code` = 'zoo'
     AND `type` = 'client_type'
     AND `deleted` = 0) AS zoo_client_types,
  (SELECT COUNT(*)
   FROM `user`
   WHERE `franchise_code` = 'zoo'
     AND `role_id` = @zoo_admin_role_id
     AND `deleted` = 0) AS zoo_admins,
  (SELECT COUNT(*)
   FROM `user`
   WHERE `franchise_code` = 'zoo'
     AND `role_id` = @zoo_admin_role_id
     AND `password` <> '!ZOO_ADMIN_PASSWORD_NOT_CONFIGURED!'
     AND `status` = 'active'
     AND `deleted` = 0) AS admin_password_ready;
