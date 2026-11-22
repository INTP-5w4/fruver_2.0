-- ============================================================
-- FRUVER 2.0 · Estructura base de la base de datos (SIN DATOS)
--
-- Paso 0 de una instalación nueva: crea las tablas, los triggers, el procedimiento
-- registrar_mermas_caducadas y su evento. Después:
--     php spark migrate      (o php spark bd:migrar con la cuenta de privilegios mínimos)
--
-- Este archivo NO debe contener datos. Los volcados con información de clientes,
-- repartidores o pedidos son datos personales (LFPDPPP): no se guardan en el
-- repositorio (ver .gitignore y docs/PRODUCCION.md).
-- Generado a partir del esquema de fruver.sql (21/05/2026), sin sus INSERT.
-- ============================================================

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 21-05-2026 a las 18:30:14
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `fruver`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `registrar_mermas_caducadas` ()   BEGIN
    DECLARE v_id_entrada    INT;
    DECLARE v_id_producto   INT;
    DECLARE v_cantidad      DECIMAL(10,2);
    DECLARE v_fecha_cad     DATE;
    DECLARE v_u_venta       VARCHAR(50);
    DECLARE v_done          INT DEFAULT 0;

    DECLARE cur_caducadas CURSOR FOR
        SELECT e.id, e.id_producto, e.conversion, e.fecha_cad, e.u_venta
        FROM   entrada e
        WHERE  e.fecha_cad <= CURDATE()
          AND  e.fecha_cad != '0000-00-00'   -- ← corrección
          AND  e.cantidad   > 0
          AND  NOT EXISTS (
              SELECT 1 FROM merma m
              WHERE  m.id_entrada = e.id
                AND  m.notas LIKE '(Automático)%'
          );

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur_caducadas;
    loop_cad: LOOP
        FETCH cur_caducadas INTO v_id_entrada, v_id_producto, v_cantidad, v_fecha_cad, v_u_venta;
        IF v_done THEN LEAVE loop_cad; END IF;

        INSERT INTO merma (cantidad, fecha, notas, id_entrada, u_venta)
        VALUES (
            v_cantidad,
            CURDATE(),
            CONCAT('(Automático) Caducado el: ', DATE_FORMAT(v_fecha_cad, '%d/%m/%Y')),
            v_id_entrada,
            v_u_venta
        );

        -- conversion=0 para que after_update_entrada no descuente de existencia
        UPDATE entrada
        SET cantidad   = 0,
            conversion = 0,
            conv_pc    = 0
        WHERE id = v_id_entrada;

    END LOOP;
    CLOSE cur_caducadas;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente`
--

CREATE TABLE `cliente` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `ape_pat` varchar(100) NOT NULL,
  `ape_mat` varchar(100) DEFAULT NULL,
  `telefono` varchar(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Disparadores `cliente`
--
DELIMITER $$
CREATE TRIGGER `no_duplica_cliente` BEFORE INSERT ON `cliente` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1 FROM cliente
        WHERE nombre   = NEW.nombre
          AND ape_pat  = NEW.ape_pat
          AND telefono = NEW.telefono
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Error: Ya existe un cliente con ese nombre, apellido y teléfono';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `direccion`
--

CREATE TABLE `direccion` (
  `id` int(11) NOT NULL,
  `colonia` varchar(200) NOT NULL,
  `calle` varchar(200) NOT NULL,
  `numero` varchar(50) NOT NULL,
  `municipio` varchar(200) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `id_cliente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entrada`
--

CREATE TABLE `entrada` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `fecha_cad` date DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `u_compra` enum('Caja','Arpilla','Bulto','Tonelada','Mazo') NOT NULL,
  `u_venta` enum('Kilogramo','Litro','Caja','Pieza','Domo','Ramo') NOT NULL,
  `equivalente` decimal(10,0) NOT NULL,
  `conversion` decimal(10,0) DEFAULT NULL,
  `precio_compra_u` decimal(10,2) NOT NULL,
  `conv_pc` decimal(10,0) DEFAULT NULL,
  `precio_venta_u` decimal(10,0) NOT NULL,
  `id_producto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Disparadores `entrada`
--
DELIMITER $$
CREATE TRIGGER `Elimina_existencia` AFTER DELETE ON `entrada` FOR EACH ROW BEGIN
    UPDATE existencia
    SET e_total = GREATEST(0, e_total - OLD.conversion),
        e_venta = GREATEST(0, e_venta - OLD.conversion)
    WHERE id_producto = OLD.id_producto;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `actualiza_total_compra` BEFORE UPDATE ON `entrada` FOR EACH ROW BEGIN
set new.conv_pc=new.cantidad*new.precio_compra_u;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_insert_entrada` AFTER INSERT ON `entrada` FOR EACH ROW BEGIN
    -- Si la entrada NO está caducada, suma normalmente a existencia
    IF NEW.fecha_cad IS NULL
       OR NEW.fecha_cad = '0000-00-00'
       OR NEW.fecha_cad > CURDATE()
    THEN
        INSERT INTO existencia (e_total, e_bloqueado, e_venta, id_producto)
        VALUES (NEW.conversion, 0, NEW.conversion, NEW.id_producto)
        ON DUPLICATE KEY UPDATE
            e_total = e_total + NEW.conversion,
            e_venta = e_venta + NEW.conversion;

    -- Si YA está caducada al momento de insertar: no toca existencia,
    -- genera merma automática inmediatamente.
    ELSE
        -- Asegurar fila de existencia sin sumar nada
        INSERT INTO existencia (e_total, e_bloqueado, e_venta, id_producto)
        VALUES (0, 0, 0, NEW.id_producto)
        ON DUPLICATE KEY UPDATE id_producto = id_producto; -- no-op

        INSERT INTO merma (cantidad, fecha, notas, id_entrada, u_venta)
        VALUES (
            NEW.conversion,
            CURDATE(),
            CONCAT('(Automático) Caducado al ingresar: ',
                   DATE_FORMAT(NEW.fecha_cad, '%d/%m/%Y')),
            NEW.id,
            NEW.u_venta
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_update_entrada` AFTER UPDATE ON `entrada` FOR EACH ROW BEGIN
    DECLARE diff DECIMAL(10,2);
    IF NEW.cantidad = 0 AND OLD.cantidad > 0 AND NEW.conversion = 0 THEN
        SET diff = 0;
    ELSE
        SET diff = NEW.conversion - OLD.conversion;
        IF diff != 0 THEN
            UPDATE existencia
            SET e_total = GREATEST(0, e_total + diff),
                e_venta = GREATEST(0, e_venta + diff)
            WHERE id_producto = NEW.id_producto;
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `caducidad_actualizada` BEFORE UPDATE ON `entrada` FOR EACH ROW BEGIN
    IF NEW.cantidad = 0 AND OLD.cantidad > 0 THEN
        SET NEW.fecha_cad  = OLD.fecha_cad;
        SET NEW.conversion = 0;
        SET NEW.conv_pc    = 0;
    ELSEIF OLD.fecha != NEW.fecha AND NEW.fecha != '0000-00-00' THEN
        SET NEW.fecha_cad = ADDDATE(NEW.fecha, 5);
        IF NEW.equivalente IS NOT NULL AND NEW.equivalente > 0 THEN
            SET NEW.conversion = NEW.cantidad * NEW.equivalente;
            SET NEW.conv_pc    = NEW.conversion * NEW.precio_compra_u;
        END IF;
    ELSE
        SET NEW.fecha_cad = OLD.fecha_cad;
        IF NEW.equivalente IS NOT NULL AND NEW.equivalente > 0 THEN
            SET NEW.conversion = NEW.cantidad * NEW.equivalente;
            SET NEW.conv_pc    = NEW.conversion * NEW.precio_compra_u;
        ELSE
            SET NEW.conversion = OLD.conversion;
            SET NEW.conv_pc    = OLD.conv_pc;
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `calculos_insercion_caducidad` BEFORE INSERT ON `entrada` FOR EACH ROW BEGIN
    IF NEW.fecha_cad IS NULL OR NEW.fecha_cad = '0000-00-00' THEN
        SET NEW.fecha_cad = ADDDATE(NEW.fecha, 5);
    END IF;

    IF NEW.equivalente IS NOT NULL AND NEW.equivalente > 0 THEN
        SET NEW.conversion = NEW.cantidad * NEW.equivalente;
        SET NEW.conv_pc    = NEW.conversion * NEW.precio_compra_u;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estatus`
--

CREATE TABLE `estatus` (
  `id` int(11) NOT NULL,
  `estado` enum('pedido_realizado','pedido_confirmado','pedido_en_transito','pedido_entregado','pedido_a_credito','pedido_pagado','pedido_cancelado','pedido_pendiente') NOT NULL DEFAULT 'pedido_pendiente',
  `fecha` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_pedido` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Disparadores `estatus`
--
DELIMITER $$
CREATE TRIGGER `actualiza_existencia` AFTER INSERT ON `estatus` FOR EACH ROW BEGIN
    DECLARE v_cant       DECIMAL(10,2);
    DECLARE v_id_prod    INT;
    DECLARE v_done       INT DEFAULT 0;
    DECLARE v_estado_ant VARCHAR(50) DEFAULT NULL;

    SELECT estado
    INTO   v_estado_ant
    FROM   estatus
    WHERE  id_pedido = NEW.id_pedido
      AND  id < NEW.id
    ORDER BY id DESC
    LIMIT 1;

    IF NEW.estado = 'pedido_confirmado' THEN
        BEGIN
            DECLARE cur1 CURSOR FOR
                SELECT cant, id_producto FROM producto_pedido WHERE id_pedido = NEW.id_pedido;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;
            OPEN cur1;
            l1: LOOP
                FETCH cur1 INTO v_cant, v_id_prod;
                IF v_done THEN LEAVE l1; END IF;
                UPDATE existencia
                SET e_bloqueado = e_bloqueado + v_cant,
                    e_venta     = GREATEST(0, e_venta - v_cant)
                WHERE id_producto = v_id_prod;
            END LOOP;
            CLOSE cur1;
        END;

    ELSEIF NEW.estado = 'pedido_en_transito' AND v_estado_ant = 'pedido_confirmado' THEN
        BEGIN
            DECLARE cur2 CURSOR FOR
                SELECT cant, id_producto FROM producto_pedido WHERE id_pedido = NEW.id_pedido;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;
            OPEN cur2;
            l2: LOOP
                FETCH cur2 INTO v_cant, v_id_prod;
                IF v_done THEN LEAVE l2; END IF;
                UPDATE existencia
                SET e_bloqueado = GREATEST(0, e_bloqueado - v_cant),
                    e_total     = GREATEST(0, e_total     - v_cant)
                WHERE id_producto = v_id_prod;
            END LOOP;
            CLOSE cur2;
        END;

    ELSEIF NEW.estado = 'pedido_cancelado' AND v_estado_ant = 'pedido_confirmado' THEN
        BEGIN
            DECLARE cur3 CURSOR FOR
                SELECT cant, id_producto FROM producto_pedido WHERE id_pedido = NEW.id_pedido;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;
            OPEN cur3;
            l3: LOOP
                FETCH cur3 INTO v_cant, v_id_prod;
                IF v_done THEN LEAVE l3; END IF;
                UPDATE existencia
                SET e_bloqueado = GREATEST(0, e_bloqueado - v_cant),
                    e_venta     = LEAST(e_total, e_venta + v_cant)
                WHERE id_producto = v_id_prod;
            END LOOP;
            CLOSE cur3;
        END;

    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `existencia`
--

CREATE TABLE `existencia` (
  `id` int(11) NOT NULL,
  `e_total` int(11) NOT NULL DEFAULT 0,
  `e_bloqueado` int(11) NOT NULL DEFAULT 0,
  `e_venta` int(11) DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_producto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `merma`
--

CREATE TABLE `merma` (
  `id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `notas` varchar(500) DEFAULT NULL,
  `id_entrada` int(11) NOT NULL,
  `u_venta` enum('Kilogramo','Mazo','Litro','Caja','Arpilla','Ramo','Bulto','Domo','Pieza') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Disparadores `merma`
--
DELIMITER $$
CREATE TRIGGER `after_insert_merma` AFTER INSERT ON `merma` FOR EACH ROW BEGIN
    DECLARE id_prod INT;
    IF NEW.notas NOT LIKE '(Automático)%' THEN
        SELECT id_producto INTO id_prod
        FROM entrada WHERE id = NEW.id_entrada;
        UPDATE existencia
        SET e_total = GREATEST(0, e_total - NEW.cantidad),
            e_venta = GREATEST(0, e_venta - NEW.cantidad)
        WHERE id_producto = id_prod;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido`
--

CREATE TABLE `pedido` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_repartidor` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

CREATE TABLE `producto` (
  `id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` varchar(300) DEFAULT NULL,
  `img` text NOT NULL,
  `categoria` enum('frutas','verduras','hierbas','') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Disparadores `producto`
--
DELIMITER $$
CREATE TRIGGER `no_duplica_producto` BEFORE INSERT ON `producto` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1 FROM producto
        WHERE nombre = NEW.nombre
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Error: Ya existe un producto con ese nombre';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_pedido`
--

CREATE TABLE `producto_pedido` (
  `id` int(11) NOT NULL,
  `cant` decimal(10,2) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `unidad_venta` enum('Kilogramo','Domo','Ramos','Caja') NOT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Disparadores `producto_pedido`
--
DELIMITER $$
CREATE TRIGGER `calcula_total` BEFORE INSERT ON `producto_pedido` FOR EACH ROW BEGIN
DECLARE total_var decimal;
SET total_var=new.cant*new.precio_venta;
SET new.total=total_var;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `repartidor`
--

CREATE TABLE `repartidor` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `ape_pat` varchar(100) NOT NULL,
  `ape_mat` varchar(100) DEFAULT NULL,
  `telefono` varchar(12) NOT NULL,
  `direccion` varchar(500) NOT NULL,
  `notas` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `direccion`
--
ALTER TABLE `direccion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Indices de la tabla `entrada`
--
ALTER TABLE `entrada`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `estatus`
--
ALTER TABLE `estatus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pedido` (`id_pedido`);

--
-- Indices de la tabla `existencia`
--
ALTER TABLE `existencia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `merma`
--
ALTER TABLE `merma`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_entrada` (`id_entrada`);

--
-- Indices de la tabla `pedido`
--
ALTER TABLE `pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_ibfk_1` (`id_cliente`),
  ADD KEY `pedido_ibfk_2` (`id_repartidor`);

--
-- Indices de la tabla `producto`
--
ALTER TABLE `producto`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `producto_pedido`
--
ALTER TABLE `producto_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`id_pedido`),
  ADD KEY `producto_id` (`id_producto`);

--
-- Indices de la tabla `repartidor`
--
ALTER TABLE `repartidor`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `cliente`
--
ALTER TABLE `cliente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `direccion`
--
ALTER TABLE `direccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `entrada`
--
ALTER TABLE `entrada`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estatus`
--
ALTER TABLE `estatus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `existencia`
--
ALTER TABLE `existencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `merma`
--
ALTER TABLE `merma`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedido`
--
ALTER TABLE `pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `producto`
--
ALTER TABLE `producto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `producto_pedido`
--
ALTER TABLE `producto_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `repartidor`
--
ALTER TABLE `repartidor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `direccion`
--
ALTER TABLE `direccion`
  ADD CONSTRAINT `id_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id`);

--
-- Filtros para la tabla `entrada`
--
ALTER TABLE `entrada`
  ADD CONSTRAINT `id_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id`);

--
-- Filtros para la tabla `estatus`
--
ALTER TABLE `estatus`
  ADD CONSTRAINT `id_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedido` (`id`);

--
-- Filtros para la tabla `existencia`
--
ALTER TABLE `existencia`
  ADD CONSTRAINT `existencia_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id`);

--
-- Filtros para la tabla `merma`
--
ALTER TABLE `merma`
  ADD CONSTRAINT `id_entrada` FOREIGN KEY (`id_entrada`) REFERENCES `entrada` (`id`);

--
-- Filtros para la tabla `pedido`
--
ALTER TABLE `pedido`
  ADD CONSTRAINT `pedido_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id`),
  ADD CONSTRAINT `pedido_ibfk_2` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidor` (`id`);

--
-- Filtros para la tabla `producto_pedido`
--
ALTER TABLE `producto_pedido`
  ADD CONSTRAINT `pedido_id` FOREIGN KEY (`id_pedido`) REFERENCES `pedido` (`id`),
  ADD CONSTRAINT `producto_id` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id`);

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `evt_mermas_caducidad` ON SCHEDULE EVERY 5 MINUTE STARTS '2026-05-19 21:46:05' ON COMPLETION PRESERVE ENABLE DO CALL registrar_mermas_caducadas()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
