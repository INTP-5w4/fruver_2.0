-- ============================================================
-- FRUVER 2.0 · Rutas de reparto con Google Maps
--
-- Forma recomendada:   php spark migrate
--   (migración app/Database/Migrations/2026-11-05-100000_AddRutasReparto.php)
--
-- Este script hace LO MISMO para quien prefiera phpMyAdmin (pestaña SQL).
-- Ejecutar UNA sola vez sobre la base `fruver`, DESPUÉS de las migraciones de
-- entregas (tabla `entrega` y `repartidor.id_user`). Si luego se corre
-- `php spark migrate`, la migración detecta lo ya creado y no lo duplica.
-- ============================================================

-- 1) Punto de salida de las rutas
CREATE TABLE IF NOT EXISTS `almacen` (
  `id`        INT(11)       NOT NULL AUTO_INCREMENT,
  `nombre`    VARCHAR(100)  NOT NULL,
  `direccion` VARCHAR(300)  NOT NULL,
  `place_id`  VARCHAR(255)  NULL DEFAULT NULL,
  `lat`       DECIMAL(10,7) NOT NULL,
  `lng`       DECIMAL(10,7) NOT NULL,
  `activo`    TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Direcciones con coordenadas
ALTER TABLE `direccion`
  ADD COLUMN `codigo_postal`        VARCHAR(10)   NULL DEFAULT NULL AFTER `numero`,
  ADD COLUMN `referencias`          VARCHAR(300)  NULL DEFAULT NULL,
  ADD COLUMN `direccion_formateada` VARCHAR(400)  NULL DEFAULT NULL,
  ADD COLUMN `place_id`             VARCHAR(255)  NULL DEFAULT NULL,
  ADD COLUMN `lat`                  DECIMAL(10,7) NULL DEFAULT NULL,
  ADD COLUMN `lng`                  DECIMAL(10,7) NULL DEFAULT NULL,
  ADD COLUMN `origen_coord`         ENUM('google','osm','manual','gps_entrega') NULL DEFAULT NULL,
  ADD COLUMN `precision_geo`        VARCHAR(30)   NULL DEFAULT NULL,
  ADD COLUMN `coord_actualizada_en` DATETIME      NULL DEFAULT NULL,
  ADD COLUMN `es_principal`         TINYINT(1)    NOT NULL DEFAULT 0,
  ADD INDEX `idx_direccion_place` (`place_id`);

-- 3) El pedido sabe a qué dirección va
ALTER TABLE `pedido`
  ADD COLUMN `id_direccion`   INT(11) NULL DEFAULT NULL AFTER `id_cliente`,
  ADD COLUMN `ventana_inicio` TIME    NULL DEFAULT NULL,
  ADD COLUMN `ventana_fin`    TIME    NULL DEFAULT NULL,
  ADD CONSTRAINT `fk_pedido_direccion` FOREIGN KEY (`id_direccion`) REFERENCES `direccion` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- 4) Vehículo del repartidor
ALTER TABLE `repartidor`
  ADD COLUMN `vehiculo`     ENUM('moto','auto','camioneta','bicicleta') NOT NULL DEFAULT 'moto',
  ADD COLUMN `capacidad_kg` DECIMAL(8,2) NULL DEFAULT NULL;

-- 5) Rutas
CREATE TABLE IF NOT EXISTS `ruta` (
  `id`              INT(11)    NOT NULL AUTO_INCREMENT,
  `fecha`           DATE       NOT NULL,
  `id_repartidor`   INT(11)    NOT NULL,
  `id_almacen`      INT(11)    NOT NULL,
  `estado`          ENUM('asignada','en_curso','finalizada','cancelada') NOT NULL DEFAULT 'asignada',
  `estrategia`      ENUM('rapida','prioridad','sin_cuotas','manual','aproximada') NOT NULL DEFAULT 'rapida',
  `regresa_almacen` TINYINT(1) NOT NULL DEFAULT 1,
  `distancia_m`     INT(11)    NULL DEFAULT NULL,
  `duracion_s`      INT(11)    NULL DEFAULT NULL,
  `polyline`        MEDIUMTEXT NULL,
  `proveedor`       VARCHAR(20) NOT NULL DEFAULT 'google',
  `creada_por`      INT(11) UNSIGNED NULL DEFAULT NULL,
  `iniciada_en`     DATETIME   NULL DEFAULT NULL,
  `finalizada_en`   DATETIME   NULL DEFAULT NULL,
  `created_at`      DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME   NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ruta_fecha_repartidor` (`fecha`, `id_repartidor`),
  CONSTRAINT `fk_ruta_repartidor` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidor` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ruta_almacen`    FOREIGN KEY (`id_almacen`)    REFERENCES `almacen` (`id`)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6) Cada entrega es una parada de la ruta
ALTER TABLE `entrega`
  ADD COLUMN `id_ruta`           INT(11)              NULL DEFAULT NULL AFTER `id_repartidor`,
  ADD COLUMN `orden_ruta`        SMALLINT(5) UNSIGNED NULL DEFAULT NULL AFTER `id_ruta`,
  ADD COLUMN `eta`               DATETIME             NULL DEFAULT NULL,
  ADD COLUMN `distancia_tramo_m` INT(11)              NULL DEFAULT NULL,
  ADD COLUMN `duracion_tramo_s`  INT(11)              NULL DEFAULT NULL,
  ADD COLUMN `lat_entrega`       DECIMAL(10,7)        NULL DEFAULT NULL,
  ADD COLUMN `lng_entrega`       DECIMAL(10,7)        NULL DEFAULT NULL,
  ADD INDEX `idx_entrega_ruta_orden` (`id_ruta`, `orden_ruta`),
  ADD CONSTRAINT `fk_entrega_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `ruta` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 7) Datos iniciales
-- La dirección más reciente de cada cliente queda como principal.
UPDATE `direccion` d
  JOIN (SELECT id_cliente, MAX(id) AS id_max FROM `direccion` GROUP BY id_cliente) u ON u.id_max = d.id
   SET d.es_principal = 1;

-- Pedidos de clientes con UNA sola dirección: se ligan a ella.
UPDATE `pedido` p
  JOIN (SELECT id_cliente, MIN(id) AS id_dir FROM `direccion` GROUP BY id_cliente HAVING COUNT(*) = 1) unica
    ON unica.id_cliente = p.id_cliente
   SET p.id_direccion = unica.id_dir
 WHERE p.id_direccion IS NULL;
