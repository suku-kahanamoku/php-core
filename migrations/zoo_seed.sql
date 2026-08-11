-- Idempotent starter data for the Zoo CRM tenant.
START TRANSACTION;

INSERT INTO `role` (`franchise_code`, `name`, `label`, `position`) VALUES
  ('zoo', 'admin', 'Administrátor', 10),
  ('zoo', 'manager', 'Manažer', 20),
  ('zoo', 'user', 'Klient', 30)
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `position` = VALUES(`position`),
  `deleted` = 0;

SET @zoo_admin_role_id = (
  SELECT `id` FROM `role`
  WHERE `franchise_code` = 'zoo' AND `name` = 'admin'
  LIMIT 1
);

INSERT INTO `user`
  (`franchise_code`, `first_name`, `last_name`, `email`, `password`, `role_id`, `status`)
VALUES
  ('zoo', 'Zoo', 'Admin', 'admin@zoo.local',
   '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',
   @zoo_admin_role_id, 'active')
ON DUPLICATE KEY UPDATE
  `role_id` = VALUES(`role_id`),
  `status` = 'active',
  `deleted` = 0;

INSERT INTO `category`
  (`franchise_code`, `parent_id`, `syscode`, `name`, `description`, `position`)
VALUES
  ('zoo', NULL, 'dogs', 'Psi', 'Krmivo a potřeby pro psy', 10),
  ('zoo', NULL, 'cats', 'Kočky', 'Krmivo a potřeby pro kočky', 20),
  ('zoo', NULL, 'birds', 'Ptáci', 'Krmivo a potřeby pro ptáky', 30),
  ('zoo', NULL, 'horses', 'Koně', 'Krmivo a potřeby pro koně', 40),
  ('zoo', NULL, 'rodents', 'Hlodavci', 'Krmivo a potřeby pro hlodavce', 50),
  ('zoo', NULL, 'fish', 'Ryby', 'Krmivo a potřeby pro akvarijní ryby', 60),
  ('zoo', NULL, 'reptiles', 'Teraristika', 'Krmivo a potřeby pro terarijní zvířata', 70)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `position` = VALUES(`position`),
  `deleted` = 0;

COMMIT;
