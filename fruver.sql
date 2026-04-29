-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 24-04-2026 a las 21:00:43
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
-- Volcado de datos para la tabla `cliente`
--

INSERT INTO `cliente` (`id`, `nombre`, `ape_pat`, `ape_mat`, `telefono`) VALUES
(7, 'Joaquin', 'Flores', 'Cadena', '1234567890'),
(8, 'Joaquin ', 'Lopez', 'Cadena ', '1234567890 ');

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

--
-- Volcado de datos para la tabla `direccion`
--

INSERT INTO `direccion` (`id`, `colonia`, `calle`, `numero`, `municipio`, `estado`, `id_cliente`) VALUES
(2, 'Matamoros', 'Inglaterra', '12345', 'Oreja de van Goh', 'Veracruz', 7);

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
  `precio_venta_u` decimal(10,0) NOT NULL,
  `id_producto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entrada`
--

INSERT INTO `entrada` (`id`, `fecha`, `fecha_cad`, `cantidad`, `u_compra`, `u_venta`, `equivalente`, `conversion`, `precio_compra_u`, `precio_venta_u`, `id_producto`) VALUES
(2, '2026-03-23', '2026-03-28', 10.00, 'Caja', 'Kilogramo', 0, 0, 100.00, 0, 5),
(3, '1997-03-21', '1997-03-26', 10.00, 'Caja', 'Kilogramo', 15, 150, 360.00, 0, 5);

--
-- Disparadores `entrada`
--
DELIMITER $$
CREATE TRIGGER `after_insert_entrada` AFTER INSERT ON `entrada` FOR EACH ROW BEGIN
    INSERT INTO existencia (e_total, e_bloqueado, e_venta, id_producto)
    VALUES (NEW.cantidad, 0, NEW.cantidad, NEW.id_producto);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_update_entrada` AFTER UPDATE ON `entrada` FOR EACH ROW BEGIN
    UPDATE existencia
    SET e_total  = NEW.cantidad,
        e_venta  = NEW.cantidad
    WHERE id_producto = NEW.id_producto;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `caducidad` BEFORE UPDATE ON `entrada` FOR EACH ROW BEGIN
DECLARE fecha_c date;

if old.fecha != new.fecha then
set fecha_c=ADDDATE(new.fecha,5);
end if;
set new.fecha_cad= fecha_c;

if old.cantidad != new.cantidad OR old.equivalente != new.equivalente then
    set new.conversion = new.cantidad * new.equivalente;
  end if;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `calculos_automaticos` BEFORE INSERT ON `entrada` FOR EACH ROW BEGIN
DECLARE fecha_c date;
set fecha_c= ADDDATE(new.fecha,5);
set new.fecha_cad=fecha_c;
set new.conversion=new.cantidad*new.equivalente;
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
-- Volcado de datos para la tabla `estatus`
--

INSERT INTO `estatus` (`id`, `estado`, `fecha`, `id_pedido`) VALUES
(1, 'pedido_en_transito', '2026-03-03 06:00:00', 1);

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

--
-- Volcado de datos para la tabla `existencia`
--

INSERT INTO `existencia` (`id`, `e_total`, `e_bloqueado`, `e_venta`, `fecha`, `id_producto`) VALUES
(2, 123, 43, 180, '2026-03-31 03:06:38', 7);

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
-- Volcado de datos para la tabla `merma`
--

INSERT INTO `merma` (`id`, `cantidad`, `fecha`, `notas`, `id_entrada`, `u_venta`) VALUES
(2, 5.00, '2026-04-22', 'Caducidad', 2, NULL);

--
-- Disparadores `merma`
--
DELIMITER $$
CREATE TRIGGER `after_insert_merma` AFTER INSERT ON `merma` FOR EACH ROW BEGIN
    DECLARE id_prod INT;

    -- Obtiene el id_producto a través de la entrada
    SELECT id_producto INTO id_prod
    FROM entrada
    WHERE id = NEW.id_entrada;

    -- Descuenta de existencia
    UPDATE existencia
    SET e_total  = e_total  - NEW.cantidad,
        e_venta  = e_venta  - NEW.cantidad
    WHERE id_producto = id_prod;
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

--
-- Volcado de datos para la tabla `pedido`
--

INSERT INTO `pedido` (`id`, `fecha`, `id_cliente`, `id_repartidor`) VALUES
(1, '2026-03-03', 8, 2);

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
-- Volcado de datos para la tabla `producto`
--

INSERT INTO `producto` (`id`, `nombre`, `descripcion`, `img`, `categoria`) VALUES
(5, 'tomate saladet', 'rojo', '', ''),
(6, 'Plátano Macho', 'rojo', '', ''),
(7, 'Brocoli', 'verde', '', ''),
(8, 'Chayotte', 'Verde', '1774622103_bd08327a00b3e9d04723.jpg', 'verduras'),
(9, 'Zanahoria', 'Naranja', '', ''),
(10, 'pera', 'verde', '', ''),
(11, 'Guisantes', 'Verde', '', 'frutas'),
(12, 'elote', 'amarillo', '1774621516_a947228d4ed4fa0fdca3.jpg', 'frutas'),
(13, 'kiwi', 'fruta neozelandesa de importacion', '1776799343_30f41c9820d50d860a45.jpg', 'frutas'),
(14, 'flor de calabaza', 'Se vende por manojo', '1776799626_d4694eedc0272714a81c.jpeg', ''),
(15, 'flor de calabaza', 'Se vende por manojo', '1776799627_c4608c2e3ba5121d8d01.jpeg', '');

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
-- Volcado de datos para la tabla `producto_pedido`
--

INSERT INTO `producto_pedido` (`id`, `cant`, `precio_venta`, `unidad_venta`, `total`, `id_pedido`, `id_producto`) VALUES
(1, 100.00, 30.00, 'Kilogramo', 250.00, 1, 5);

--
-- Disparadores `producto_pedido`
--
DELIMITER $$
CREATE TRIGGER `calcula_total` BEFORE INSERT ON `producto_pedido` FOR EACH ROW BEGIN
DECLARE total_var decimal;
SET total_var=cantidad*precio_venta;
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
  `notas` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `repartidor`
--

INSERT INTO `repartidor` (`id`, `nombre`, `ape_pat`, `ape_mat`, `telefono`, `direccion`, `notas`) VALUES
(2, 'Joaquin', 'Flores', 'Cadena', '1234567890', 'CERRADA 1 SN, 401', 'alo');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `direccion`
--
ALTER TABLE `direccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `entrada`
--
ALTER TABLE `entrada`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `estatus`
--
ALTER TABLE `estatus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `existencia`
--
ALTER TABLE `existencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `merma`
--
ALTER TABLE `merma`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `pedido`
--
ALTER TABLE `pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `producto`
--
ALTER TABLE `producto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `producto_pedido`
--
ALTER TABLE `producto_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `repartidor`
--
ALTER TABLE `repartidor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
