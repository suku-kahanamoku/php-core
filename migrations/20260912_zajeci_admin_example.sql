-- Restore the original Zajeci administrator login.
-- Login: admin@example.com
-- Password: admin123

SET NAMES utf8mb4;
START TRANSACTION;

SET @zajeci_admin_role_id = (
  SELECT `id`
  FROM `role`
  WHERE `franchise_code` = 'zajeci'
    AND `name` = 'admin'
    AND `deleted` = 0
  ORDER BY `id`
  LIMIT 1
);

SET @zajeci_admin_user_id = (
  SELECT `id`
  FROM `user`
  WHERE `franchise_code` = 'zajeci'
    AND (
      `email` = 'admin@example.com'
      OR (`email` = 'admin@vinozezajeci.cz' AND `role_id` = @zajeci_admin_role_id)
    )
  ORDER BY (`email` = 'admin@example.com') DESC, `id`
  LIMIT 1
);

UPDATE `user`
SET
  `email` = 'admin@example.com',
  `password` = '$2y$12$iHtrWWa.BMJBFu3d0YA8EuoojjRMXCa0OHuPfBmVoJcT26OLKGbSC',
  `role_id` = @zajeci_admin_role_id,
  `status` = 'active',
  `deleted` = 0
WHERE `id` = @zajeci_admin_user_id
  AND @zajeci_admin_role_id IS NOT NULL;

COMMIT;

SELECT
  u.`id`,
  u.`franchise_code`,
  u.`email`,
  u.`status`,
  r.`name` AS `role`
FROM `user` u
JOIN `role` r ON r.`id` = u.`role_id`
WHERE u.`id` = @zajeci_admin_user_id;
