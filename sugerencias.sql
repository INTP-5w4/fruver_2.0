-- ============================================================
-- FRUVER 2.0 — Buzón de sugerencias
-- Ejecutar una sola vez sobre la base de datos `fruver`.
-- ============================================================

CREATE TABLE IF NOT EXISTS `sugerencia` (
  `id`             int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo`         varchar(100)     NOT NULL,
  `descripcion`    text             NOT NULL,
  `valoracion`     enum('No muy importante','Importante','Muy importante') NOT NULL,
  `fecha_creacion` timestamp        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_usuario`     int(10) UNSIGNED DEFAULT NULL,
  `usuario`        varchar(100)     NOT NULL,
  `rol`            varchar(100)     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sugerencia_usuario` (`id_usuario`),
  KEY `idx_sugerencia_fecha` (`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notas de diseño:
--  * `usuario` y `rol` guardan una copia del nombre y rol al momento de enviar,
--    así la sugerencia conserva su autoría aunque el usuario cambie de rol o se elimine.
--  * `id_usuario` enlaza con la tabla `users` de Shield. No lleva FOREIGN KEY a propósito
--    para que este script no falle si el tipo de columna difiere en tu instalación.
--    Si quieres integridad referencial, descomenta:
--
-- ALTER TABLE `sugerencia`
--   ADD CONSTRAINT `fk_sugerencia_usuario`
--   FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE SET NULL;
