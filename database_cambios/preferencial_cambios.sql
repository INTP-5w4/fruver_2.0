-- ============================================================
-- FRUVER 2.0 · Cliente preferencial modificable (reversible) y con motivo
-- Ejecutar UNA sola vez en phpMyAdmin (pestaña SQL) sobre la base `fruver`.
-- Es equivalente a la migración 2026-10-24-100000_AddPreferencialToCliente:
-- si ya corriste `php spark migrate`, no hace falta ejecutar este archivo.
--
-- Usa ADD COLUMN IF NOT EXISTS (MariaDB, que es la que trae XAMPP): si lo
-- ejecutas de nuevo no pasa nada. En MySQL 8 quita las palabras "IF NOT EXISTS".
-- ============================================================

ALTER TABLE `cliente`
  ADD COLUMN IF NOT EXISTS `preferencial_modo`   ENUM('automatico','si','no') NOT NULL DEFAULT 'automatico',
  ADD COLUMN IF NOT EXISTS `preferencial_motivo` VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `preferencial_fecha`  DATETIME     NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `preferencial_por`    VARCHAR(100) NULL DEFAULT NULL;

-- Significado de `preferencial_modo`:
--   'automatico' -> se decide por las compras del cliente (como hasta ahora)
--   'si'         -> preferencial siempre (cambio manual, con motivo)
--   'no'         -> nunca preferencial (cambio manual, con motivo)
-- Volver a 'automatico' deshace el cambio manual. Los clientes que ya existen
-- quedan en 'automatico', así que nada cambia hasta que alguien lo modifique.
