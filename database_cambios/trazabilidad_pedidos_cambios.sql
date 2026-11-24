-- ============================================================
-- FRUVER 2.0 · Trazabilidad de pedidos (Aviso de Privacidad, 3.2)
-- Equivale a la migración app/Database/Migrations/2026-11-10-110000_CreatePedidoBitacora.php.
-- Usa UNA de las dos opciones: `php spark migrate` o ejecutar este archivo en phpMyAdmin.
-- Requiere las columnas de rutas en `pedido` (rutas_reparto_cambios.sql).
--
-- El usuario lo fija la aplicación en cada conexión (App\Libraries\ConexionPdo):
--   @fruver_id_user, @fruver_usuario. Cambios hechos fuera de FRUVER quedan como
--   "Base de datos (sin sesión)".
-- ============================================================

CREATE TABLE IF NOT EXISTS `pedido_bitacora` (
  `id`        INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pedido` INT(11)          NOT NULL,
  `accion`    ENUM('captura','modificacion','productos','estatus','eliminacion') NOT NULL,
  `detalle`   VARCHAR(255)     NULL DEFAULT NULL,
  `id_user`   INT(10) UNSIGNED NULL DEFAULT NULL,
  `usuario`   VARCHAR(100)     NOT NULL,
  `fecha`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bitacora_pedido` (`id_pedido`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `bit_pedido_ai`;
DROP TRIGGER IF EXISTS `bit_pedido_au`;
DROP TRIGGER IF EXISTS `bit_pedido_ad`;
DROP TRIGGER IF EXISTS `bit_pp_ai`;
DROP TRIGGER IF EXISTS `bit_pp_au`;
DROP TRIGGER IF EXISTS `bit_pp_ad`;
DROP TRIGGER IF EXISTS `bit_estatus_ai`;
DROP TRIGGER IF EXISTS `bit_estatus_au`;
DROP TRIGGER IF EXISTS `bit_estatus_ad`;

DELIMITER $$

-- ── pedido ──
CREATE TRIGGER bit_pedido_ai AFTER INSERT ON pedido FOR EACH ROW
  INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
  VALUES (NEW.id, 'captura', CONCAT('Cliente #', NEW.id_cliente),
          @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'))$$

CREATE TRIGGER bit_pedido_au AFTER UPDATE ON pedido FOR EACH ROW
BEGIN
  DECLARE cambio VARCHAR(255) DEFAULT LEFT(CONCAT_WS(', ',
    IF(NOT (OLD.fecha <=> NEW.fecha), CONCAT('fecha ', OLD.fecha, ' -> ', NEW.fecha), NULL),
    IF(NOT (OLD.id_cliente <=> NEW.id_cliente), CONCAT('cliente #', OLD.id_cliente, ' -> #', NEW.id_cliente), NULL),
    IF(NOT (OLD.id_repartidor <=> NEW.id_repartidor), CONCAT('repartidor #', IFNULL(OLD.id_repartidor, '-'), ' -> #', IFNULL(NEW.id_repartidor, '-')), NULL),
    IF(NOT (OLD.id_direccion <=> NEW.id_direccion), CONCAT('dirección #', IFNULL(OLD.id_direccion, '-'), ' -> #', IFNULL(NEW.id_direccion, '-')), NULL),
    IF(NOT (OLD.ventana_inicio <=> NEW.ventana_inicio) OR NOT (OLD.ventana_fin <=> NEW.ventana_fin), 'horario de entrega', NULL)
  ), 255);
  IF cambio <> '' THEN
    INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
    VALUES (NEW.id, 'modificacion', cambio, @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'));
  END IF;
END$$

CREATE TRIGGER bit_pedido_ad AFTER DELETE ON pedido FOR EACH ROW
  INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
  VALUES (OLD.id, 'eliminacion', CONCAT('Pedido eliminado (cliente #', OLD.id_cliente, ')'),
          @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'))$$

-- ── producto_pedido ──
CREATE TRIGGER bit_pp_ai AFTER INSERT ON producto_pedido FOR EACH ROW
  INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
  VALUES (NEW.id_pedido, 'productos',
          LEFT(CONCAT('Agregó ', IFNULL((SELECT nombre FROM producto WHERE id = NEW.id_producto), CONCAT('producto #', NEW.id_producto)), ': ',
               TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM NEW.cant)), ' ', NEW.unidad_venta, ' a $', NEW.precio_venta), 255),
          @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'))$$

CREATE TRIGGER bit_pp_au AFTER UPDATE ON producto_pedido FOR EACH ROW
  INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
  SELECT NEW.id_pedido, 'productos',
         LEFT(CONCAT('Cambió ', IFNULL((SELECT nombre FROM producto WHERE id = NEW.id_producto), CONCAT('producto #', NEW.id_producto)), ': ',
              TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM OLD.cant)), ' ', OLD.unidad_venta, ' a $', OLD.precio_venta, ' -> ',
              TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM NEW.cant)), ' ', NEW.unidad_venta, ' a $', NEW.precio_venta), 255),
         @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)')
    FROM DUAL
   WHERE NOT (OLD.cant <=> NEW.cant) OR NOT (OLD.precio_venta <=> NEW.precio_venta)
      OR NOT (OLD.unidad_venta <=> NEW.unidad_venta) OR NOT (OLD.id_producto <=> NEW.id_producto)$$

CREATE TRIGGER bit_pp_ad AFTER DELETE ON producto_pedido FOR EACH ROW
  INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
  VALUES (OLD.id_pedido, 'productos',
          LEFT(CONCAT('Quitó ', IFNULL((SELECT nombre FROM producto WHERE id = OLD.id_producto), CONCAT('producto #', OLD.id_producto)), ': ',
               TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM OLD.cant)), ' ', OLD.unidad_venta, ' a $', OLD.precio_venta), 255),
          @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'))$$

-- ── estatus ──
CREATE TRIGGER bit_estatus_ai AFTER INSERT ON estatus FOR EACH ROW
  INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
  VALUES (NEW.id_pedido, 'estatus', REPLACE(NEW.estado, '_', ' '),
          @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'))$$

CREATE TRIGGER bit_estatus_au AFTER UPDATE ON estatus FOR EACH ROW
  INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
  SELECT NEW.id_pedido, 'estatus',
         CONCAT('Corrigió estatus: ', REPLACE(OLD.estado, '_', ' '), ' (', OLD.fecha, ') -> ', REPLACE(NEW.estado, '_', ' '), ' (', NEW.fecha, ')'),
         @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)')
    FROM DUAL
   WHERE NOT (OLD.estado <=> NEW.estado) OR NOT (OLD.fecha <=> NEW.fecha)$$

CREATE TRIGGER bit_estatus_ad AFTER DELETE ON estatus FOR EACH ROW
  INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
  VALUES (OLD.id_pedido, 'estatus', CONCAT('Eliminó estatus: ', REPLACE(OLD.estado, '_', ' ')),
          @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'))$$

DELIMITER ;
