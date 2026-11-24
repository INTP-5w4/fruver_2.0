-- ============================================================
-- FRUVER 2.0 · Roles como ENUM
-- Alternativa a la migración 2026-11-12-110000_RolesComoEnum.
-- Si ya corriste `php spark migrate`, NO hace falta ejecutar este archivo.
--
-- auth_groups_users.group era texto libre: un rol escrito a mano ("Super Admin") no
-- lo reconoce Shield y la cuenta se queda sin permisos. Con ENUM, phpMyAdmin solo deja
-- elegir los roles que existen en app/Config/AuthGroups.php.
--
-- Si después agregas un rol en AuthGroups.php, corre:  php spark roles:sincronizar
-- ============================================================

-- 1. Corrige los valores escritos a mano (títulos viejos de Shield).
UPDATE auth_groups_users SET `group` = 'superadmin' WHERE LOWER(REPLACE(`group`, ' ', '')) IN ('superadmin', 'superadministrador');
UPDATE auth_groups_users SET `group` = 'admin'      WHERE LOWER(`group`) IN ('admin', 'administrador');
UPDATE auth_groups_users SET `group` = 'developer'  WHERE LOWER(`group`) IN ('developer', 'desarrollador');
UPDATE auth_groups_users SET `group` = 'repartidor' WHERE LOWER(`group`) = 'repartidor';
-- Lo que no sea un rol válido pasa a cliente.
UPDATE auth_groups_users SET `group` = 'user'
 WHERE `group` NOT IN ('superadmin', 'admin', 'developer', 'repartidor', 'user');

-- 2. Roles repetidos en una misma cuenta: se deja uno.
DELETE g1 FROM auth_groups_users g1
  JOIN auth_groups_users g2 ON g2.user_id = g1.user_id AND g2.`group` = g1.`group` AND g2.id < g1.id;

-- 3. ENUM con los roles de app/Config/AuthGroups.php.
ALTER TABLE auth_groups_users
  MODIFY `group` ENUM('superadmin', 'admin', 'developer', 'repartidor', 'user') NOT NULL DEFAULT 'user';
