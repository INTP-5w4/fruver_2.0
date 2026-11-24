-- ============================================================
-- FRUVER 2.0 · Auditoría de clientes y productos, bitácoras inalterables
--              y envíos únicos de pedidos
-- Equivale a las migraciones 2026-11-22-100000_CreateAuditoriaCatalogos y
-- 2026-11-22-110000_CreateEnvioUnico.
-- Usa UNA de las dos opciones: `php spark migrate` o ejecutar este archivo en phpMyAdmin.
-- Requiere pedido_bitacora (trazabilidad_pedidos_cambios.sql) y rol_bitacora
-- (baja_repartidor_cambios.sql).
--
-- El autor lo fija la aplicación en cada conexión (App\Libraries\ConexionPdo):
--   @fruver_id_user, @fruver_usuario. Cambios hechos fuera de FRUVER quedan como
--   "Base de datos (sin sesión)". Detalles en docs/AUDITORIA.md.
-- ============================================================

CREATE TABLE IF NOT EXISTS `auditoria` (
  `id`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tabla`       ENUM('cliente','producto') NOT NULL,
  `id_registro` INT(11)          NOT NULL,
  `accion`      ENUM('alta','modificacion','eliminacion') NOT NULL,
  `detalle`     VARCHAR(500)     NULL DEFAULT NULL,
  `id_user`     INT(10) UNSIGNED NULL DEFAULT NULL,
  `usuario`     VARCHAR(100)     NOT NULL,
  `fecha`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_registro` (`tabla`, `id_registro`, `id`),
  KEY `idx_auditoria_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `envio_unico` (
  `token`      CHAR(32)         NOT NULL,
  `recurso`    VARCHAR(30)      NOT NULL,
  `id_recurso` INT(11)          NULL DEFAULT NULL,
  `id_user`    INT(10) UNSIGNED NULL DEFAULT NULL,
  `fecha`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token`),
  KEY `idx_envio_unico_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `aud_cliente_ai`;
DROP TRIGGER IF EXISTS `aud_cliente_au`;
DROP TRIGGER IF EXISTS `aud_cliente_ad`;
DROP TRIGGER IF EXISTS `aud_producto_ai`;
DROP TRIGGER IF EXISTS `aud_producto_au`;
DROP TRIGGER IF EXISTS `aud_producto_ad`;
DROP TRIGGER IF EXISTS `inm_auditoria_bu`;
DROP TRIGGER IF EXISTS `inm_auditoria_bd`;
DROP TRIGGER IF EXISTS `inm_pedido_bitacora_bu`;
DROP TRIGGER IF EXISTS `inm_pedido_bitacora_bd`;
DROP TRIGGER IF EXISTS `inm_rol_bitacora_bu`;
DROP TRIGGER IF EXISTS `inm_rol_bitacora_bd`;

DELIMITER $$

CREATE TRIGGER `aud_cliente_ai` AFTER INSERT ON `cliente` FOR EACH ROW
INSERT INTO auditoria (tabla, id_registro, accion, detalle, id_user, usuario)
             VALUES ('cliente', NEW.id, 'alta', LEFT('Alta del cliente', 500), @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'))
$$

CREATE TRIGGER `aud_cliente_au` AFTER UPDATE ON `cliente` FOR EACH ROW
BEGIN
                DECLARE cambio VARCHAR(500) DEFAULT LEFT(CONCAT_WS(', ', IF(NOT (OLD.nombre <=> NEW.nombre), 'nombre', NULL), IF(NOT (OLD.ape_pat <=> NEW.ape_pat), 'apellido paterno', NULL), IF(NOT (OLD.ape_mat <=> NEW.ape_mat), 'apellido materno', NULL), IF(NOT (OLD.telefono <=> NEW.telefono), 'teléfono', NULL), IF(NOT (OLD.id_user <=> NEW.id_user), CONCAT('cuenta #', IFNULL(OLD.id_user, '-'), ' -> #', IFNULL(NEW.id_user, '-')), NULL), IF(NOT (OLD.preferencial_modo <=> NEW.preferencial_modo), CONCAT('preferencial ', OLD.preferencial_modo, ' -> ', NEW.preferencial_modo), NULL), IF(NOT (OLD.preferencial_motivo <=> NEW.preferencial_motivo), 'motivo del preferencial', NULL)), 480);
                IF cambio <> '' THEN
                    INSERT INTO auditoria (tabla, id_registro, accion, detalle, id_user, usuario)
             VALUES ('cliente', NEW.id, 'modificacion', LEFT(CONCAT('Cambió: ', cambio), 500), @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'));
                END IF;
             END
$$

CREATE TRIGGER `aud_cliente_ad` AFTER DELETE ON `cliente` FOR EACH ROW
INSERT INTO auditoria (tabla, id_registro, accion, detalle, id_user, usuario)
             VALUES ('cliente', OLD.id, 'eliminacion', LEFT('Cliente eliminado', 500), @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'))
$$

CREATE TRIGGER `aud_producto_ai` AFTER INSERT ON `producto` FOR EACH ROW
INSERT INTO auditoria (tabla, id_registro, accion, detalle, id_user, usuario)
             VALUES ('producto', NEW.id, 'alta', LEFT(CONCAT('Alta: «', NEW.nombre, '» (', IF(NEW.categoria = '', 'sin categoría', NEW.categoria), ')'), 500), @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'))
$$

CREATE TRIGGER `aud_producto_au` AFTER UPDATE ON `producto` FOR EACH ROW
BEGIN
                DECLARE cambio VARCHAR(500) DEFAULT LEFT(CONCAT_WS(', ', IF(NOT (OLD.nombre <=> NEW.nombre), CONCAT('nombre «', OLD.nombre, '» -> «', NEW.nombre, '»'), NULL), IF(NOT (OLD.descripcion <=> NEW.descripcion), 'descripción', NULL), IF(NOT (OLD.categoria <=> NEW.categoria), CONCAT('categoría ', IF(OLD.categoria = '', '-', OLD.categoria), ' -> ', IF(NEW.categoria = '', '-', NEW.categoria)), NULL), IF(NOT (OLD.img <=> NEW.img), 'imagen', NULL), IF(NOT (OLD.codigo <=> NEW.codigo), CONCAT('código ', IFNULL(OLD.codigo, '-'), ' -> ', IFNULL(NEW.codigo, '-')), NULL)), 480);
                IF cambio <> '' THEN
                    INSERT INTO auditoria (tabla, id_registro, accion, detalle, id_user, usuario)
             VALUES ('producto', NEW.id, 'modificacion', LEFT(CONCAT('Cambió: ', cambio), 500), @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'));
                END IF;
             END
$$

CREATE TRIGGER `aud_producto_ad` AFTER DELETE ON `producto` FOR EACH ROW
INSERT INTO auditoria (tabla, id_registro, accion, detalle, id_user, usuario)
             VALUES ('producto', OLD.id, 'eliminacion', LEFT(CONCAT('Eliminó: «', OLD.nombre, '»'), 500), @fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)'))
$$

CREATE TRIGGER `inm_auditoria_bu` BEFORE UPDATE ON `auditoria` FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: la bitácora (auditoria) es de solo consulta; sus registros no se pueden modificar ni borrar.'
$$

CREATE TRIGGER `inm_auditoria_bd` BEFORE DELETE ON `auditoria` FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: la bitácora (auditoria) es de solo consulta; sus registros no se pueden modificar ni borrar.'
$$

CREATE TRIGGER `inm_pedido_bitacora_bu` BEFORE UPDATE ON `pedido_bitacora` FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: la bitácora (pedido_bitacora) es de solo consulta; sus registros no se pueden modificar ni borrar.'
$$

CREATE TRIGGER `inm_pedido_bitacora_bd` BEFORE DELETE ON `pedido_bitacora` FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: la bitácora (pedido_bitacora) es de solo consulta; sus registros no se pueden modificar ni borrar.'
$$

CREATE TRIGGER `inm_rol_bitacora_bu` BEFORE UPDATE ON `rol_bitacora` FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: la bitácora (rol_bitacora) es de solo consulta; sus registros no se pueden modificar ni borrar.'
$$

CREATE TRIGGER `inm_rol_bitacora_bd` BEFORE DELETE ON `rol_bitacora` FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: la bitácora (rol_bitacora) es de solo consulta; sus registros no se pueden modificar ni borrar.'
$$

DELIMITER ;
