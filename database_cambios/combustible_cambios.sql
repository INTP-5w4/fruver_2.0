-- ============================================================
-- FRUVER 2.0 · Consumo de combustible y costo de traslado
-- Alternativa a la migración 2026-11-12-120000_AddCombustible.
-- Si ya corriste `php spark migrate`, NO hace falta ejecutar este archivo.
-- (ADD COLUMN IF NOT EXISTS es de MariaDB, la que trae XAMPP.)
-- ============================================================

ALTER TABLE `repartidor`
  ADD COLUMN IF NOT EXISTS `rendimiento_km_l` DECIMAL(5,2) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `combustible` ENUM('magna', 'premium', 'diesel') NULL DEFAULT NULL;

ALTER TABLE `ruta`
  ADD COLUMN IF NOT EXISTS `litros` DECIMAL(8,2) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `costo_combustible` DECIMAL(10,2) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `precio_litro` DECIMAL(6,2) NULL DEFAULT NULL;
