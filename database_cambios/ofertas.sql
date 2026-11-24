-- Ofertas (promociones) sobre el precio de la tienda.
-- Equivale a la migración 2026-11-23-120000_CreateOfertas.php, por si se prefiere aplicar a mano.
-- El precio con descuento se calcula en el servidor (Modelo_producto::sqlCatalogo).

CREATE TABLE IF NOT EXISTS `oferta` (
  `id`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`       VARCHAR(100) NOT NULL,
  `tipo`         ENUM('porcentaje','precio') NOT NULL DEFAULT 'porcentaje',
  `valor`        DECIMAL(10,2) NOT NULL,
  `alcance`      ENUM('productos','categoria') NOT NULL DEFAULT 'productos',
  `categoria`    VARCHAR(20) NULL,
  `fecha_inicio` DATE NULL,
  `fecha_fin`    DATE NULL,
  `activa`       TINYINT(1) NOT NULL DEFAULT 1,
  `creada`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oferta_vigencia` (`activa`, `fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oferta_producto` (
  `id_oferta`   INT(11) UNSIGNED NOT NULL,
  `id_producto` INT(11) NOT NULL,
  PRIMARY KEY (`id_oferta`, `id_producto`),
  KEY `idx_oferta_producto_prod` (`id_producto`),
  CONSTRAINT `fk_oferta_producto_oferta` FOREIGN KEY (`id_oferta`)
      REFERENCES `oferta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
