-- ============================================================
-- FRUVER 2.0 · Código único de producto (requerimiento #8)
-- Alternativa a la migración 2026-10-25-100000_AddCodigoToProducto.
-- Si ya corriste `php spark migrate`, NO hace falta ejecutar este archivo.
--
-- OJO: este archivo solo crea la columna y el índice. Los productos que ya existen
-- se rellenan con la migración (php spark migrate), porque el código sale de la
-- categoría + el nombre y se calcula en PHP. Si usas solo phpMyAdmin, ejecuta este
-- archivo y luego `php spark migrate` una vez para rellenar los códigos pendientes
-- (la migración no vuelve a crear lo que ya existe).
--
-- Usa ADD COLUMN IF NOT EXISTS (MariaDB, que es la que trae XAMPP). En MySQL 8 quita
-- las palabras "IF NOT EXISTS" y no repitas el ADD UNIQUE KEY si ya lo creaste.
-- ============================================================

ALTER TABLE `producto`
  ADD COLUMN IF NOT EXISTS `codigo` VARCHAR(30) NULL DEFAULT NULL;

-- El índice UNIQUE es la garantía final de que no haya dos productos con el mismo código.
-- Si algún código ya estuviera repetido, este paso falla: corrige el duplicado y repite.
ALTER TABLE `producto`
  ADD UNIQUE KEY `uq_producto_codigo` (`codigo`);

-- Formato:  CAT-NOMBRE  (FRU-UVAAZU)  o  CAT-NOMBRE-NN  (FRU-UVAAZU-02) si ya existe.
--   CAT: frutas=FRU, verduras=VER, hierbas=HIE, sin categoría=GEN
