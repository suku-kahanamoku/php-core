-- Standard administrator credentials for all current php-core tenants.
-- Password for every standard administrator below: admin.
-- Other users with the admin role are preserved with their existing credentials.

SET NAMES utf8mb4;
START TRANSACTION;

SET @zajeci_admin_role_id = (SELECT `id` FROM `role` WHERE `franchise_code` = 'zajeci' AND `name` = 'admin' AND `deleted` = 0 ORDER BY `id` LIMIT 1);
SET @zoo_admin_role_id = (SELECT `id` FROM `role` WHERE `franchise_code` = 'zoo' AND `name` = 'admin' AND `deleted` = 0 ORDER BY `id` LIMIT 1);
SET @fun_admin_role_id = (SELECT `id` FROM `role` WHERE `franchise_code` = 'fun' AND `name` = 'admin' AND `deleted` = 0 ORDER BY `id` LIMIT 1);

SET @zajeci_admin_user_id = (
  SELECT `id` FROM `user`
  WHERE `franchise_code` = 'zajeci'
    AND (`email` = 'admin@vinozezajeci.cz' OR `role_id` = @zajeci_admin_role_id)
  ORDER BY (`email` = 'admin@vinozezajeci.cz') DESC, `id`
  LIMIT 1
);
SET @zoo_admin_user_id = (
  SELECT `id` FROM `user`
  WHERE `franchise_code` = 'zoo'
    AND (`email` = 'admin@zoo.local' OR `role_id` = @zoo_admin_role_id)
  ORDER BY (`email` = 'admin@zoo.local') DESC, `id`
  LIMIT 1
);
SET @fun_admin_user_id = (
  SELECT `id` FROM `user`
  WHERE `franchise_code` = 'fun'
    AND (`email` = 'admin@fann.cz' OR `role_id` = @fun_admin_role_id)
  ORDER BY (`email` = 'admin@fann.cz') DESC, `id`
  LIMIT 1
);

UPDATE `user`
SET `email` = 'admin@vinozezajeci.cz', `password` = '$2y$12$nmRE/TC4K3OYnBRaqnLfz.IGMYHjt1RVgej7139P7u7ijXz0epGWy',
    `role_id` = @zajeci_admin_role_id, `status` = 'active', `deleted` = 0
WHERE `id` = @zajeci_admin_user_id AND @zajeci_admin_role_id IS NOT NULL;

UPDATE `user`
SET `email` = 'admin@zoo.local', `password` = '$2y$12$nmRE/TC4K3OYnBRaqnLfz.IGMYHjt1RVgej7139P7u7ijXz0epGWy',
    `role_id` = @zoo_admin_role_id, `status` = 'active', `deleted` = 0
WHERE `id` = @zoo_admin_user_id AND @zoo_admin_role_id IS NOT NULL;

UPDATE `user`
SET `email` = 'admin@fann.cz', `password` = '$2y$12$nmRE/TC4K3OYnBRaqnLfz.IGMYHjt1RVgej7139P7u7ijXz0epGWy',
    `role_id` = @fun_admin_role_id, `status` = 'active', `deleted` = 0
WHERE `id` = @fun_admin_user_id AND @fun_admin_role_id IS NOT NULL;

COMMIT;

SELECT
  u.`franchise_code`,
  u.`email`,
  u.`status`,
  r.`name` AS `role`
FROM `user` AS u
JOIN `role` AS r ON r.`id` = u.`role_id`
WHERE r.`name` = 'admin'
  AND u.`franchise_code` IN ('zajeci', 'zoo', 'fun')
  AND u.`deleted` = 0
ORDER BY u.`franchise_code`;
