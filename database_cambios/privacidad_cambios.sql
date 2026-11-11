-- ============================================================
-- FRUVER 2.0 · Aviso de privacidad y consentimiento (LFPDPPP)
-- Equivale a la migración app/Database/Migrations/2026-11-10-100000_CreateConsentimientoTable.php.
-- Usa UNA de las dos opciones: `php spark migrate` o ejecutar este archivo en phpMyAdmin.
-- ============================================================

-- Constancia de cada aceptación, cambio o revocación (solo se insertan filas).
-- La fila más reciente de cada cuenta es su estado vigente.
CREATE TABLE IF NOT EXISTS `consentimiento` (
  `id`                INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user`           INT(10) UNSIGNED NOT NULL,
  `version_aviso`     VARCHAR(20)      NOT NULL,
  `tipo_relacion`     ENUM('cliente','usuario_interno') NOT NULL,
  `acepta_aviso`      TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `mayor_edad`        TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `v1`                TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `v2`                TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `v3`                TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `v4`                TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `canal_telefono`    TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `canal_mensajeria`  TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `canal_correo`      TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `telefono_contacto` VARCHAR(12)  NULL DEFAULT NULL,
  `origen`            ENUM('registro','aceptacion','preferencias','revocacion') NOT NULL,
  `ip_address`        VARCHAR(45)  NOT NULL,
  `user_agent`        VARCHAR(255) NULL DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_consentimiento_usuario` (`id_user`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
