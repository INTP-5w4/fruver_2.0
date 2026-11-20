-- ============================================================
-- FRUVER 2.0 · Rutas con árbol de expansión mínima (Prim)
-- Alternativa a la migración 2026-11-19-100000_AddEstrategiaArbol.
-- Si ya corriste `php spark migrate`, NO hace falta ejecutar este archivo.
-- Se puede ejecutar más de una vez sin problema.
-- ============================================================

ALTER TABLE `ruta`
  MODIFY `estrategia` ENUM('rapida', 'prioridad', 'sin_cuotas', 'manual', 'aproximada', 'arbol') NOT NULL DEFAULT 'rapida';
