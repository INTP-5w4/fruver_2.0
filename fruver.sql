-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 22-04-2026 a las 22:14:03
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
(8, 'Joaquin ', 'Lopez', 'Cadena ', '1234567890 '),
(9, 'Arcos', 'Salazar', 'Rafael', '2382923'),
(10, 'Marco', 'Salazar', 'Rafael', '2382925'),
(11, 'Salazar Rafael', 'Salazar', 'Rafael', '1111111');

--
-- Disparadores `cliente`
--
DELIMITER $$
CREATE TRIGGER `no_duplica_cliente` BEFORE INSERT ON `cliente` FOR EACH ROW BEGIN
    DECLARE msg VARCHAR(255);
    IF EXISTS (
        SELECT 1 FROM cliente
        WHERE nombre = NEW.nombre
          AND ape_pat = NEW.ape_pat
          AND ape_mat <=> NEW.ape_mat
    ) THEN
        SET msg = CONCAT('Error: Ya existe un cliente con el nombre ', NEW.nombre, ' ', NEW.ape_pat);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = msg;
    END IF;
    IF EXISTS (
        SELECT 1 FROM cliente
        WHERE telefono = NEW.telefono
    ) THEN
        SET msg = CONCAT('Error: El teléfono ', NEW.telefono, ' ya está registrado');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = msg;
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
  `u_compra` enum('Caja','Arpilla','Bulto','Tonelada','') NOT NULL,
  `u_venta` enum('Kilogramo','Litro','Caja','') NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `equivalente` decimal(10,2) NOT NULL,
  `conversion` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entrada`
--

INSERT INTO `entrada` (`id`, `fecha`, `fecha_cad`, `cantidad`, `u_compra`, `u_venta`, `precio_compra`, `id_producto`, `equivalente`, `conversion`) VALUES
(23, '2026-04-21', '2026-04-26', 3.00, 'Caja', 'Kilogramo', 200.00, 6, 10.00, 30.00),
(27, '2026-04-21', '0000-00-00', 3.00, 'Caja', 'Kilogramo', 2299.00, 5, 0.00, NULL),
(28, '2026-04-21', '0000-00-00', 3.00, 'Caja', 'Kilogramo', 2299.00, 5, 0.00, NULL),
(29, '2026-04-21', '0000-00-00', 3.00, 'Caja', 'Kilogramo', 2200.00, 6, 0.00, NULL),
(30, '2026-04-21', '2026-04-26', 3.00, 'Caja', 'Kilogramo', 2200.00, 6, 0.00, 0.00),
(31, '2026-04-21', '2026-04-26', 3.00, 'Caja', 'Kilogramo', 197.00, 5, 0.00, 0.00),
(32, '2026-04-21', '2026-04-26', 3.00, 'Caja', 'Kilogramo', 197.00, 5, 0.00, 0.00),
(33, '2026-04-21', '2026-04-26', 3.00, 'Caja', 'Kilogramo', 197.00, 8, 0.00, 0.00),
(34, '2026-04-22', '2026-04-27', 3.00, 'Caja', 'Kilogramo', 3000.00, 6, 0.00, 0.00),
(35, '2026-04-22', '2026-04-27', 3.00, 'Caja', 'Kilogramo', 1200.00, 11, 0.00, 0.00);

--
-- Disparadores `entrada`
--
DELIMITER $$
CREATE TRIGGER `actualiza_existencia` BEFORE UPDATE ON `entrada` FOR EACH ROW BEGIN

DECLARE acumulado INT;
DECLARE cantidad_dia INT;
  
SELECT existencia.exis_total_dia INTO cantidad_dia FROM existencia WHERE date(existencia.fecha) = old.fecha AND existencia.id_producto1 = old.id_producto;

if old.conversion > new.conversion then
set acumulado = cantidad_dia + (old.conversion - new.conversion); 
else SET acumulado = cantidad_dia - (new.conversion - old.conversion);END IF;

UPDATE existencia SET exis_total_dia = acumulado WHERE date(existencia.fecha) = old.fecha AND existencia.id_producto1 = old.id_producto;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `calculo_automaticos_caducidad` BEFORE UPDATE ON `entrada` FOR EACH ROW BEGIN
  IF OLD.fecha != NEW.fecha THEN
    SET NEW.fecha_cad = ADDDATE(NEW.fecha, 5);
    SET NEW.conversion = NEW.cantidad * NEW.equivalente;
  END IF;

  IF OLD.cantidad != NEW.cantidad OR OLD.equivalente != NEW.equivalente THEN
    SET NEW.conversion = NEW.cantidad * NEW.equivalente;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `calculo_entrada_caducidad` BEFORE INSERT ON `entrada` FOR EACH ROW BEGIN
  SET NEW.fecha_cad = ADDDATE(NEW.fecha, 5);
  SET NEW.conversion = NEW.cantidad * NEW.equivalente;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `inserta_existencia` BEFORE INSERT ON `entrada` FOR EACH ROW BEGIN
DECLARE acumulado int;
DECLARE cantidad_dia int;

SELECT existencia.exis_total_dia INTO cantidad_dia FROM existencia WHERE date(existencia.fecha)=new.fecha AND existencia.id_producto1=new.id_producto;

set acumulado=cantidad_dia+new.conversion;

UPDATE existencia set exis_total_dia = acumulado WHERE date(existencia.fecha)= new.fecha AND existencia.id_producto1=new.id_producto;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `resta_existencia` AFTER DELETE ON `entrada` FOR EACH ROW BEGIN
DECLARE acumulado int;
DECLARE cantidad_dia int;

SELECT existencia.exis_total_dia INTO cantidad_dia FROM existencia WHERE date(existencia.fecha)=old.fecha AND existencia.id_producto1=old.id_producto;

set acumulado=cantidad_dia-old.conversion;

UPDATE existencia set exis_total_dia = acumulado WHERE date(existencia.fecha)= old.fecha AND existencia.id_producto1=old.id_producto;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estatus`
--

CREATE TABLE `estatus` (
  `id` int(11) NOT NULL,
  `estado` enum('Pedido realizado','Pedido confirmado','Pedido en tránsito','Pedido entregado','Pedido a crédito','Pedido pagado','Pedido cancelado') NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_pedido` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `existencia`
--

CREATE TABLE `existencia` (
  `id` int(11) NOT NULL,
  `e_total` int(11) NOT NULL DEFAULT 0,
  `e_bloqueado` int(11) NOT NULL DEFAULT 0,
  `e_venta` int(11) NOT NULL DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_producto1` int(11) NOT NULL,
  `exis_total_dia` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `existencia`
--

INSERT INTO `existencia` (`id`, `e_total`, `e_bloqueado`, `e_venta`, `fecha`, `id_producto1`, `exis_total_dia`) VALUES
(1, 0, 0, 0, '2026-04-14 20:02:58', 6, 1),
(2, 0, 0, 0, '2026-04-22 17:51:21', 10, -30),
(3, 1000, 0, 0, '2026-04-17 02:20:54', 7, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `merma`
--

CREATE TABLE `merma` (
  `id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `notas` varchar(500) DEFAULT NULL,
  `id_entrada` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `merma`
--

INSERT INTO `merma` (`id`, `cantidad`, `fecha`, `notas`, `id_entrada`) VALUES
(1, 3.00, '2026-04-22', 'Perdida', 23),
(2, 3.00, '2026-04-22', 'Perdida', 35);

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
(1, '2026-04-06', 7, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

CREATE TABLE `producto` (
  `id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` varchar(300) DEFAULT NULL,
  `img` text NOT NULL,
  `categoria` enum('frutas','verduras','yerbas') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto`
--

INSERT INTO `producto` (`id`, `nombre`, `descripcion`, `img`, `categoria`) VALUES
(5, 'tomate saladet', 'rojo', '', 'frutas'),
(6, 'Plátano Macho', 'rojo', '', 'frutas'),
(7, 'Brocoli', 'verde', '', 'frutas'),
(8, 'Chayotte', 'Verde', '', 'frutas'),
(9, 'Zanahoria', 'Naranja', '', 'frutas'),
(10, 'pera', 'verde', '', 'frutas'),
(11, 'Rambutanes', 'Son una uva peluda', '1776885004_aa71f75424bedff3c833.jpg', 'frutas');

--
-- Disparadores `producto`
--
DELIMITER $$
CREATE TRIGGER `no_duplica_producto` BEFORE INSERT ON `producto` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1 FROM producto
        WHERE LOWER(nombre) = LOWER(NEW.nombre)
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
  `cantidad` decimal(10,2) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `unidad_venta` enum('Kilogramo','Domo','Ramos','Caja') NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto_pedido`
--

INSERT INTO `producto_pedido` (`id`, `cantidad`, `precio_venta`, `unidad_venta`, `total`, `id_pedido`, `id_producto`) VALUES
(1, 20.00, 100.00, 'Domo', 2000.00, 1, 5),
(2, 20.00, 100.00, 'Domo', 2000.00, 1, 5),
(3, 200.00, 100.00, 'Kilogramo', 20000.00, 1, 5);

--
-- Disparadores `producto_pedido`
--
DELIMITER $$
CREATE TRIGGER `calculo_automatico` BEFORE INSERT ON `producto_pedido` FOR EACH ROW BEGIN
    SET NEW.total = NEW.cantidad * NEW.precio_venta;
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
  ADD KEY `id_producto` (`id_producto1`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `direccion`
--
ALTER TABLE `direccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `entrada`
--
ALTER TABLE `entrada`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de la tabla `estatus`
--
ALTER TABLE `estatus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `existencia`
--
ALTER TABLE `existencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `producto_pedido`
--
ALTER TABLE `producto_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  ADD CONSTRAINT `existencia_ibfk_1` FOREIGN KEY (`id_producto1`) REFERENCES `producto` (`id`);

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
