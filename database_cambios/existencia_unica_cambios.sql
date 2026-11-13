-- ============================================================
-- FRUVER 2.0 · Una sola fila de existencia por producto
-- Alternativa a la migración 2026-11-12-100000_UnificaExistenciaPorProducto.
-- Si ya corriste `php spark migrate`, NO hace falta ejecutar este archivo.
--
-- Por qué: el trigger after_insert_entrada usa "ON DUPLICATE KEY UPDATE" para sumar
-- cada entrada a la existencia del producto, pero existencia.id_producto no era UNIQUE:
-- cada entrada creaba otra fila y el sistema solo leía la más reciente
-- (3800 kg de kiwi + 400 kg nuevos = solo 400 disponibles).
-- ============================================================

START TRANSACTION;

-- 1. Suma las filas repetidas de cada producto en la más antigua.
UPDATE existencia x
  JOIN (SELECT id_producto, MIN(id) AS conservar,
               SUM(e_total) AS t, SUM(e_bloqueado) AS b, SUM(COALESCE(e_venta, 0)) AS v
          FROM existencia
      GROUP BY id_producto
        HAVING COUNT(*) > 1) s ON s.conservar = x.id
   SET x.e_total = s.t, x.e_bloqueado = s.b, x.e_venta = s.v;

-- 2. Borra las sobrantes.
DELETE x FROM existencia x
  JOIN (SELECT id_producto, MIN(id) AS conservar FROM existencia GROUP BY id_producto) s
    ON s.id_producto = x.id_producto AND x.id <> s.conservar;

COMMIT;

-- 3. Desde aquí el trigger suma sobre la fila existente en lugar de crear otra.
ALTER TABLE `existencia` ADD UNIQUE KEY `uq_existencia_producto` (`id_producto`);
