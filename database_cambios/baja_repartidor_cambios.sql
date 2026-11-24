-- ============================================================
-- FRUVER 2.0 · Baja lógica de repartidores y bitácora de roles
-- Alternativa a la migración 2026-11-20-100000_AddBajaRepartidor.
-- Si ya corriste `php spark migrate`, NO hace falta ejecutar este archivo.
-- (ADD COLUMN IF NOT EXISTS es de MariaDB, la que trae XAMPP.)
-- ============================================================

ALTER TABLE `repartidor`
  ADD COLUMN IF NOT EXISTS `activo`      TINYINT(1)       NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `fecha_baja`  DATETIME         NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `motivo_baja` VARCHAR(255)     NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `baja_por`    INT(11) UNSIGNED NULL DEFAULT NULL,
  ADD INDEX IF NOT EXISTS `idx_repartidor_activo` (`activo`);

CREATE TABLE IF NOT EXISTS `rol_bitacora` (
  `id`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user`     INT(11) UNSIGNED NOT NULL,
  `cuenta`      VARCHAR(255)     NOT NULL,
  `rol_antes`   VARCHAR(100)     NULL DEFAULT NULL,
  `rol_despues` VARCHAR(30)      NOT NULL,
  `motivo`      VARCHAR(255)     NULL DEFAULT NULL,
  `detalle`     VARCHAR(255)     NULL DEFAULT NULL,
  `por_id_user` INT(11) UNSIGNED NULL DEFAULT NULL,
  `por_usuario` VARCHAR(255)     NOT NULL,
  `fecha`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rol_bitacora_cuenta` (`id_user`, `id`),
  KEY `idx_rol_bitacora_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Fichas cuya cuenta ya no es repartidor (o fue borrada): quedan dadas de baja.
UPDATE `repartidor` r
   SET r.activo = 0,
       r.fecha_baja = COALESCE(r.fecha_baja, NOW()),
       r.motivo_baja = COALESCE(r.motivo_baja, 'Su cuenta ya no tenía el rol de repartidor al instalar la baja de repartidores.')
 WHERE r.activo = 1
   AND r.id_user IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM auth_groups_users g
                     JOIN users u ON u.id = g.user_id AND u.deleted_at IS NULL
                    WHERE g.user_id = r.id_user AND g.`group` = 'repartidor');
