<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega el origen "osm" (OpenStreetMap / openrouteservice) a direccion.origen_coord.
 *
 * Necesaria solo si ya se había corrido 2026-11-05-100000_AddRutasReparto antes de
 * agregar openrouteservice; en instalaciones nuevas la columna ya nace con "osm" y
 * esta migración no cambia nada. Es idempotente.
 */
class AddOsmOrigenDireccion extends Migration
{
    private const CON_OSM = "ENUM('google','osm','manual','gps_entrega') NULL DEFAULT NULL";
    private const SIN_OSM = "ENUM('google','manual','gps_entrega') NULL DEFAULT NULL";

    public function up()
    {
        if ($this->db->fieldExists('origen_coord', 'direccion')) {
            $this->db->query('ALTER TABLE `direccion` MODIFY `origen_coord` ' . self::CON_OSM);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('origen_coord', 'direccion')) {
            // Las coordenadas de OpenStreetMap se conservan, pero sin etiqueta de origen.
            $this->db->query("UPDATE `direccion` SET `origen_coord` = NULL WHERE `origen_coord` = 'osm'");
            $this->db->query('ALTER TABLE `direccion` MODIFY `origen_coord` ' . self::SIN_OSM);
        }
    }
}
