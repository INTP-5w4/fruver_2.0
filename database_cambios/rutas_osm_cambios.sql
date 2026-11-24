-- ============================================================
-- FRUVER 2.0 · Rutas de reparto con openrouteservice / OpenStreetMap
--
-- Forma recomendada:   php spark migrate --all
--   (migración 2026-11-05-120000_AddOsmOrigenDireccion)
--
-- Solo hace falta si YA ejecutaste rutas_reparto_cambios.sql antes de este cambio.
-- Si vas a ejecutar rutas_reparto_cambios.sql por primera vez, NO necesitas este archivo.
-- ============================================================

ALTER TABLE `direccion`
  MODIFY `origen_coord` ENUM('google','osm','manual','gps_entrega') NULL DEFAULT NULL;
