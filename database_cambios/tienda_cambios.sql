-- ============================================================
-- FRUVER 2.0 · Cambios de base de datos para la tienda (main_page_4)
-- Ejecutar UNA sola vez en phpMyAdmin (pestaña SQL) sobre la base `fruver`.
-- Requiere que ya existan las tablas de Shield (`users`), es decir, que
-- ya hayas corrido `php spark migrate --all`.
-- ============================================================

-- 1) Los pedidos hechos desde la tienda nacen sin repartidor; el personal lo
--    asigna al confirmar el pedido. Por eso la columna debe aceptar NULL.
ALTER TABLE `pedido`
  MODIFY `id_repartidor` INT(11) NULL DEFAULT NULL;

-- 2) Enlaza la cuenta de acceso (users) con su perfil de cliente.
--    Una cuenta = un cliente (UNIQUE). Si se borra la cuenta, el cliente se conserva.
ALTER TABLE `cliente`
  ADD COLUMN `id_user` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `telefono`,
  ADD UNIQUE KEY `uq_cliente_id_user` (`id_user`),
  ADD CONSTRAINT `fk_cliente_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 3) Las unidades de venta de `entrada` incluyen Litro y Pieza, pero `producto_pedido`
--    no las aceptaba: un pedido de un producto vendido por pieza o litro fallaba.
--    Se deja la misma lista que usa `entrada` (con "Ramos" como ya se guarda).
ALTER TABLE `producto_pedido`
  MODIFY `unidad_venta` ENUM('Kilogramo','Litro','Caja','Pieza','Domo','Ramos') NOT NULL;
