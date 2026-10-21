-- ============================================================
-- FRUVER 2.0 · Buzón de sugerencias
-- Ejecutar UNA sola vez en phpMyAdmin (pestaña SQL) sobre la base `fruver`.
-- Es equivalente a la migración 2026-10-20-100000_CreateSugerenciaTable:
-- si ya corriste `php spark migrate`, no hace falta ejecutar este archivo
-- (y si lo ejecutas de todos modos, no pasa nada: usa IF NOT EXISTS).
-- ============================================================

CREATE TABLE IF NOT EXISTS `sugerencia` (
  `id`             INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo`         VARCHAR(100)     NOT NULL,
  `descripcion`    TEXT             NOT NULL,
  `valoracion`     ENUM('No muy importante','Importante','Muy importante') NOT NULL,
  `fecha_creacion` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_usuario`     INT(10) UNSIGNED DEFAULT NULL,
  `usuario`        VARCHAR(100)     NOT NULL,
  `rol`            VARCHAR(100)     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sugerencia_usuario` (`id_usuario`),
  KEY `idx_sugerencia_fecha` (`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notas de diseño:
--  * `usuario` y `rol` guardan una copia del nombre y de los roles al momento de enviar,
--    así la sugerencia conserva su autoría aunque el usuario cambie de rol o se elimine.
--  * `id_usuario` enlaza con `users.id` de Shield. No lleva llave foránea a propósito
--    (la sugerencia sobrevive si se borra la cuenta). Si prefieres integridad referencial:
--
-- ALTER TABLE `sugerencia`
--   ADD CONSTRAINT `fk_sugerencia_user` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`)
--     ON DELETE SET NULL ON UPDATE CASCADE;
