<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Rutas de reparto con Google Maps.
 *
 *  1. `almacen`   : punto de salida (y regreso) de las rutas.
 *  2. `direccion` : coordenadas, place_id de Google, referencias y dirección principal.
 *  3. `pedido`    : a qué dirección va (id_direccion) y horario preferido de entrega.
 *  4. `repartidor`: vehículo (define el modo de viaje en Google) y capacidad.
 *  5. `ruta`      : agrupa las entregas de un repartidor en un día, con el orden óptimo.
 *  6. `entrega`   : cada intento de entrega se vuelve una "parada" de la ruta
 *                   (orden, hora estimada, tramo y GPS real al entregar).
 *
 * Es idempotente (revisa si cada tabla/columna ya existe) para poder correrla
 * aunque alguien haya aplicado parte de los cambios a mano con
 * database_cambios/rutas_reparto_cambios.sql.
 *
 * Tipos: pedido.id, direccion.id, repartidor.id y entrega.id son INT(11) CON signo;
 * users.id (Shield) es INT(11) SIN signo. Las llaves foráneas deben coincidir.
 */
class AddRutasReparto extends Migration
{
    private const OPCIONES_TABLA = [
        'ENGINE'  => 'InnoDB',
        'CHARSET' => 'utf8mb4',
        'COLLATE' => 'utf8mb4_general_ci',
    ];

    public function up()
    {
        $this->crearAlmacen();
        $this->extenderDireccion();
        $this->extenderPedido();
        $this->extenderRepartidor();
        $this->crearRuta();
        $this->extenderEntrega();
        $this->rellenarDatosExistentes();
    }

    public function down()
    {
        $this->quitarLlave('entrega', 'fk_entrega_ruta');
        $this->quitarIndice('entrega', 'idx_entrega_ruta_orden');
        $this->quitarColumnas('entrega', ['id_ruta', 'orden_ruta', 'eta', 'distancia_tramo_m', 'duracion_tramo_s', 'lat_entrega', 'lng_entrega']);

        $this->forge->dropTable('ruta', true);

        $this->quitarColumnas('repartidor', ['vehiculo', 'capacidad_kg']);

        $this->quitarLlave('pedido', 'fk_pedido_direccion');
        $this->quitarColumnas('pedido', ['id_direccion', 'ventana_inicio', 'ventana_fin']);

        $this->quitarIndice('direccion', 'idx_direccion_place');
        $this->quitarColumnas('direccion', [
            'codigo_postal', 'referencias', 'direccion_formateada', 'place_id', 'lat', 'lng',
            'origen_coord', 'precision_geo', 'coord_actualizada_en', 'es_principal',
        ]);

        $this->forge->dropTable('almacen', true);
    }

    // ─────────────────────────────────────────────────────────────

    private function crearAlmacen(): void
    {
        if ($this->db->tableExists('almacen')) {
            return;
        }

        $this->forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'nombre'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'direccion' => ['type' => 'VARCHAR', 'constraint' => 300],
            'place_id'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // Coordenadas capturadas por el negocio (pin en el mapa): dato propio, no caché de Google.
            'lat'       => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'lng'       => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'activo'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('almacen', true, self::OPCIONES_TABLA);
    }

    private function extenderDireccion(): void
    {
        $this->agregarColumnas('direccion', [
            'codigo_postal'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'numero'],
            'referencias'          => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'direccion_formateada' => ['type' => 'VARCHAR', 'constraint' => 400, 'null' => true],
            'place_id'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'lat'                  => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'lng'                  => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            // google = Geocoding/Places (caché máx. 30 días) · osm = OpenStreetMap · manual = pin · gps_entrega = GPS del repartidor
            'origen_coord'         => ['type' => 'ENUM', 'constraint' => ['google', 'osm', 'manual', 'gps_entrega'], 'null' => true],
            'precision_geo'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'coord_actualizada_en' => ['type' => 'DATETIME', 'null' => true],
            'es_principal'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);

        if (! $this->existeIndice('direccion', 'idx_direccion_place')) {
            $this->db->query('ALTER TABLE `direccion` ADD INDEX `idx_direccion_place` (`place_id`)');
        }
    }

    private function extenderPedido(): void
    {
        $this->agregarColumnas('pedido', [
            'id_direccion'   => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'id_cliente'],
            'ventana_inicio' => ['type' => 'TIME', 'null' => true],
            'ventana_fin'    => ['type' => 'TIME', 'null' => true],
        ]);

        if (! $this->existeLlave('pedido', 'fk_pedido_direccion')) {
            // RESTRICT: una dirección usada en pedidos no se borra (se conserva el historial).
            $this->db->query(
                'ALTER TABLE `pedido` ADD CONSTRAINT `fk_pedido_direccion` FOREIGN KEY (`id_direccion`) '
                . 'REFERENCES `direccion` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
            );
        }
    }

    private function extenderRepartidor(): void
    {
        $this->agregarColumnas('repartidor', [
            'vehiculo'     => ['type' => 'ENUM', 'constraint' => ['moto', 'auto', 'camioneta', 'bicicleta'], 'default' => 'moto'],
            'capacidad_kg' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
        ]);
    }

    private function crearRuta(): void
    {
        if ($this->db->tableExists('ruta')) {
            return;
        }

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'fecha'           => ['type' => 'DATE'],
            'id_repartidor'   => ['type' => 'INT', 'constraint' => 11],
            'id_almacen'      => ['type' => 'INT', 'constraint' => 11],
            'estado'          => [
                'type'       => 'ENUM',
                'constraint' => ['asignada', 'en_curso', 'finalizada', 'cancelada'],
                'default'    => 'asignada',
            ],
            'estrategia'      => [
                'type'       => 'ENUM',
                'constraint' => ['rapida', 'prioridad', 'sin_cuotas', 'manual', 'aproximada'],
                'default'    => 'rapida',
            ],
            'regresa_almacen' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'distancia_m'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'duracion_s'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'polyline'        => ['type' => 'MEDIUMTEXT', 'null' => true],
            'proveedor'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'google'],
            'creada_por'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'iniciada_en'     => ['type' => 'DATETIME', 'null' => true],
            'finalizada_en'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['fecha', 'id_repartidor'], false, false, 'idx_ruta_fecha_repartidor');
        $this->forge->addForeignKey('id_repartidor', 'repartidor', 'id', 'CASCADE', 'RESTRICT', 'fk_ruta_repartidor');
        $this->forge->addForeignKey('id_almacen', 'almacen', 'id', 'CASCADE', 'RESTRICT', 'fk_ruta_almacen');
        $this->forge->createTable('ruta', true, self::OPCIONES_TABLA);
    }

    private function extenderEntrega(): void
    {
        $this->agregarColumnas('entrega', [
            'id_ruta'           => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'id_repartidor'],
            'orden_ruta'        => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'after' => 'id_ruta'],
            'eta'               => ['type' => 'DATETIME', 'null' => true],
            'distancia_tramo_m' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'duracion_tramo_s'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'lat_entrega'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'lng_entrega'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
        ]);

        if (! $this->existeIndice('entrega', 'idx_entrega_ruta_orden')) {
            $this->db->query('ALTER TABLE `entrega` ADD INDEX `idx_entrega_ruta_orden` (`id_ruta`, `orden_ruta`)');
        }

        if (! $this->existeLlave('entrega', 'fk_entrega_ruta')) {
            // SET NULL: si se borra una ruta, las entregas (y su historial) se conservan.
            $this->db->query(
                'ALTER TABLE `entrega` ADD CONSTRAINT `fk_entrega_ruta` FOREIGN KEY (`id_ruta`) '
                . 'REFERENCES `ruta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    /**
     * Datos iniciales:
     *  - La dirección más reciente de cada cliente queda como principal.
     *  - Los pedidos de clientes con UNA sola dirección se ligan a ella. Los que tienen
     *    varias quedan en NULL y el sistema usa la principal hasta que alguien elija.
     */
    private function rellenarDatosExistentes(): void
    {
        // Solo si nadie tiene principal todavía (la columna recién se creó).
        // La tabla derivada con GROUP BY se materializa, así MySQL permite leer y
        // actualizar `direccion` en la misma sentencia (evita el error 1093).
        if ((int) $this->db->query('SELECT COUNT(*) AS n FROM `direccion` WHERE es_principal = 1')->getRow()->n === 0) {
            $this->db->query(
                'UPDATE `direccion` d
                   JOIN (SELECT id_cliente, MAX(id) AS id_max FROM `direccion` GROUP BY id_cliente) u
                     ON u.id_max = d.id
                    SET d.es_principal = 1'
            );
        }

        $this->db->query(
            'UPDATE `pedido` p
               JOIN (SELECT id_cliente, MIN(id) AS id_dir FROM `direccion`
                      GROUP BY id_cliente HAVING COUNT(*) = 1) unica
                 ON unica.id_cliente = p.id_cliente
                SET p.id_direccion = unica.id_dir
              WHERE p.id_direccion IS NULL'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Utilidades idempotentes
    // ─────────────────────────────────────────────────────────────

    private function agregarColumnas(string $tabla, array $campos): void
    {
        foreach ($campos as $nombre => $definicion) {
            if (! $this->db->fieldExists($nombre, $tabla)) {
                $this->forge->addColumn($tabla, [$nombre => $definicion]);
            }
        }
    }

    private function quitarColumnas(string $tabla, array $nombres): void
    {
        if (! $this->db->tableExists($tabla)) {
            return;
        }

        foreach ($nombres as $nombre) {
            if ($this->db->fieldExists($nombre, $tabla)) {
                $this->forge->dropColumn($tabla, $nombre);
            }
        }
    }

    private function existeLlave(string $tabla, string $nombre): bool
    {
        return $this->db->query(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = ?',
            [$tabla, $nombre, 'FOREIGN KEY']
        )->getRow() !== null;
    }

    private function existeIndice(string $tabla, string $nombre): bool
    {
        return $this->db->query(
            'SELECT 1 FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$tabla, $nombre]
        )->getRow() !== null;
    }

    private function quitarLlave(string $tabla, string $nombre): void
    {
        if ($this->db->tableExists($tabla) && $this->existeLlave($tabla, $nombre)) {
            $this->db->query("ALTER TABLE `{$tabla}` DROP FOREIGN KEY `{$nombre}`");
        }
    }

    private function quitarIndice(string $tabla, string $nombre): void
    {
        if ($this->db->tableExists($tabla) && $this->existeIndice($tabla, $nombre)) {
            $this->db->query("ALTER TABLE `{$tabla}` DROP INDEX `{$nombre}`");
        }
    }
}
