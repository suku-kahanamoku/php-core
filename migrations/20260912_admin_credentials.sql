-- Standard administrator credentials for all current php-core tenants.
-- Password for every administrator below: admin

SET NAMES utf8mb4;
START TRANSACTION;

UPDATE `user` AS u
JOIN `role` AS r
  ON r.`id` = u.`role_id`
 AND r.`franchise_code` = u.`franchise_code`
SET
  u.`email` = CASE u.`franchise_code`
    WHEN 'zajeci' THEN 'admin@vinozezajeci.cz'
    WHEN 'zoo' THEN 'admin@zoo.local'
    WHEN 'fun' THEN 'admin@fann.cz'
  END,
  u.`password` = '$2y$12$nmRE/TC4K3OYnBRaqnLfz.IGMYHjt1RVgej7139P7u7ijXz0epGWy',
  u.`status` = 'active',
  u.`deleted` = 0
WHERE r.`name` = 'admin'
  AND r.`deleted` = 0
  AND u.`franchise_code` IN ('zajeci', 'zoo', 'fun');

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
