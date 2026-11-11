<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Trazabilidad de pedidos (Aviso de Privacidad, 3.2: "Usuario que capturó o modificó el pedido").
 *
 * Tabla `pedido_bitacora` + triggers en `pedido`, `producto_pedido` y `estatus`.
 * Se hace en la base de datos para cubrir TODOS los caminos que escriben pedidos (panel,
 * pedido completo, tienda, estatus, entregas, rutas) y los que se agreguen después, sin
 * depender de que cada controlador se acuerde de registrar.
 *
 * ¿Quién hizo el cambio? App\Libraries\ConexionPdo fija en cada conexión las variables
 * @fruver_id_user y @fruver_usuario con la cuenta de la sesión. Un cambio hecho fuera de
 * la aplicación (phpMyAdmin) queda como "Base de datos (sin sesión)".
 *
 *  - Sin llave foránea a `pedido`: el historial sobrevive al borrado del pedido.
 *  - `usuario` guarda una copia del nombre: sobrevive si la cuenta se elimina.
 *  - Los triggers de UPDATE solo registran si algo cambió de verdad.
 */
class CreatePedidoBitacora extends Migration
{
    /** @var list<string> */
    private const TRIGGERS = [
        'bit_pedido_ai', 'bit_pedido_au', 'bit_pedido_ad',
        'bit_pp_ai', 'bit_pp_au', 'bit_pp_ad',
        'bit_estatus_ai', 'bit_estatus_au', 'bit_estatus_ad',
    ];

    private const QUIEN = "@fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)')";

    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_pedido' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'accion' => [
                'type'       => 'ENUM',
                'constraint' => ['captura', 'modificacion', 'productos', 'estatus', 'eliminacion'],
            ],
            'detalle' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'id_user' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'usuario' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'fecha' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['id_pedido', 'id'], false, false, 'idx_bitacora_pedido');
        $this->forge->createTable('pedido_bitacora', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_general_ci',
        ]);

        $this->borrarTriggers();

        foreach ($this->triggers() as $sql) {
            $this->db->query($sql);
        }
    }

    public function down()
    {
        $this->borrarTriggers();
        $this->forge->dropTable('pedido_bitacora', true);
    }

    private function borrarTriggers(): void
    {
        foreach (self::TRIGGERS as $nombre) {
            $this->db->query("DROP TRIGGER IF EXISTS `{$nombre}`");
        }
    }

    /** @return list<string> */
    private function triggers(): array
    {
        $q = self::QUIEN;

        // Columnas de la cabecera que se vigilan (las de rutas solo si existen).
        $cambios = [
            "IF(NOT (OLD.fecha <=> NEW.fecha), CONCAT('fecha ', OLD.fecha, ' -> ', NEW.fecha), NULL)",
            "IF(NOT (OLD.id_cliente <=> NEW.id_cliente), CONCAT('cliente #', OLD.id_cliente, ' -> #', NEW.id_cliente), NULL)",
            "IF(NOT (OLD.id_repartidor <=> NEW.id_repartidor), CONCAT('repartidor #', IFNULL(OLD.id_repartidor, '-'), ' -> #', IFNULL(NEW.id_repartidor, '-')), NULL)",
        ];
        if ($this->db->fieldExists('id_direccion', 'pedido')) {
            $cambios[] = "IF(NOT (OLD.id_direccion <=> NEW.id_direccion), CONCAT('dirección #', IFNULL(OLD.id_direccion, '-'), ' -> #', IFNULL(NEW.id_direccion, '-')), NULL)";
            $cambios[] = "IF(NOT (OLD.ventana_inicio <=> NEW.ventana_inicio) OR NOT (OLD.ventana_fin <=> NEW.ventana_fin), 'horario de entrega', NULL)";
        }
        $detalleCabecera = "CONCAT_WS(', ', " . implode(', ', $cambios) . ')';

        $producto = static fn (string $fila): string => "IFNULL((SELECT nombre FROM producto WHERE id = {$fila}.id_producto), CONCAT('producto #', {$fila}.id_producto))";
        $linea    = static fn (string $fila): string => "CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM {$fila}.cant)), ' ', {$fila}.unidad_venta, ' a $', {$fila}.precio_venta)";

        return [
            // ── pedido ──
            "CREATE TRIGGER bit_pedido_ai AFTER INSERT ON pedido FOR EACH ROW
                INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
                VALUES (NEW.id, 'captura', CONCAT('Cliente #', NEW.id_cliente), {$q})",

            "CREATE TRIGGER bit_pedido_au AFTER UPDATE ON pedido FOR EACH ROW
             BEGIN
                DECLARE cambio VARCHAR(255) DEFAULT LEFT({$detalleCabecera}, 255);
                IF cambio <> '' THEN
                    INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
                    VALUES (NEW.id, 'modificacion', cambio, {$q});
                END IF;
             END",

            "CREATE TRIGGER bit_pedido_ad AFTER DELETE ON pedido FOR EACH ROW
                INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
                VALUES (OLD.id, 'eliminacion', CONCAT('Pedido eliminado (cliente #', OLD.id_cliente, ')'), {$q})",

            // ── producto_pedido ──
            "CREATE TRIGGER bit_pp_ai AFTER INSERT ON producto_pedido FOR EACH ROW
                INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
                VALUES (NEW.id_pedido, 'productos', LEFT(CONCAT('Agregó ', {$producto('NEW')}, ': ', {$linea('NEW')}), 255), {$q})",

            "CREATE TRIGGER bit_pp_au AFTER UPDATE ON producto_pedido FOR EACH ROW
                INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
                SELECT NEW.id_pedido, 'productos',
                       LEFT(CONCAT('Cambió ', {$producto('NEW')}, ': ', {$linea('OLD')}, ' -> ', {$linea('NEW')}), 255), {$q}
                  FROM DUAL
                 WHERE NOT (OLD.cant <=> NEW.cant) OR NOT (OLD.precio_venta <=> NEW.precio_venta)
                    OR NOT (OLD.unidad_venta <=> NEW.unidad_venta) OR NOT (OLD.id_producto <=> NEW.id_producto)",

            "CREATE TRIGGER bit_pp_ad AFTER DELETE ON producto_pedido FOR EACH ROW
                INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
                VALUES (OLD.id_pedido, 'productos', LEFT(CONCAT('Quitó ', {$producto('OLD')}, ': ', {$linea('OLD')}), 255), {$q})",

            // ── estatus ──
            "CREATE TRIGGER bit_estatus_ai AFTER INSERT ON estatus FOR EACH ROW
                INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
                VALUES (NEW.id_pedido, 'estatus', REPLACE(NEW.estado, '_', ' '), {$q})",

            "CREATE TRIGGER bit_estatus_au AFTER UPDATE ON estatus FOR EACH ROW
                INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
                SELECT NEW.id_pedido, 'estatus',
                       CONCAT('Corrigió estatus: ', REPLACE(OLD.estado, '_', ' '), ' (', OLD.fecha, ') -> ', REPLACE(NEW.estado, '_', ' '), ' (', NEW.fecha, ')'), {$q}
                  FROM DUAL
                 WHERE NOT (OLD.estado <=> NEW.estado) OR NOT (OLD.fecha <=> NEW.fecha)",

            "CREATE TRIGGER bit_estatus_ad AFTER DELETE ON estatus FOR EACH ROW
                INSERT INTO pedido_bitacora (id_pedido, accion, detalle, id_user, usuario)
                VALUES (OLD.id_pedido, 'estatus', CONCAT('Eliminó estatus: ', REPLACE(OLD.estado, '_', ' ')), {$q})",
        ];
    }
}
