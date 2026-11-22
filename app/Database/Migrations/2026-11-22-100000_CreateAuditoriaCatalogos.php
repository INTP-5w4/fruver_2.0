<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Auditoría de clientes y productos + bitácoras de solo inserción.
 *
 * 1. Tabla `auditoria` con triggers en `cliente` y `producto` (alta, modificación y
 *    eliminación). Igual que `pedido_bitacora`: se hace en la base de datos para cubrir
 *    todos los caminos que escriben (panel, tienda, comandos, phpMyAdmin) y el autor sale
 *    de @fruver_id_user / @fruver_usuario (App\Libraries\ConexionPdo).
 *
 *    Minimización (LFPDPPP, principio de proporcionalidad): de un cliente se registra QUÉ
 *    campos cambiaron, no sus valores; así la auditoría no se vuelve una segunda copia de
 *    los datos personales y la supresión de un cliente es real. De un producto (no es dato
 *    personal) sí se guardan los valores anterior y nuevo.
 *
 * 2. Bitácoras inalterables: triggers BEFORE UPDATE / BEFORE DELETE que rechazan cualquier
 *    cambio en `auditoria`, `pedido_bitacora` y `rol_bitacora`. Un registro de auditoría
 *    que se puede editar o borrar no prueba nada. Ver docs/AUDITORIA.md para cerrar
 *    también DROP/TRUNCATE con los permisos del usuario de la base en producción.
 *
 * Alternativa sin spark: database_cambios/auditoria_cambios.sql.
 */
class CreateAuditoriaCatalogos extends Migration
{
    private const QUIEN = "@fruver_id_user, COALESCE(@fruver_usuario, 'Base de datos (sin sesión)')";

    /** Tablas de bitácora que quedan de solo inserción. */
    private const BITACORAS = ['auditoria', 'pedido_bitacora', 'rol_bitacora'];

    /** @var list<string> */
    private const TRIGGERS_AUDITORIA = [
        'aud_cliente_ai', 'aud_cliente_au', 'aud_cliente_ad',
        'aud_producto_ai', 'aud_producto_au', 'aud_producto_ad',
    ];

    public function up()
    {
        if (! $this->db->tableExists('auditoria')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'tabla'       => ['type' => 'ENUM', 'constraint' => ['cliente', 'producto']],
                'id_registro' => ['type' => 'INT', 'constraint' => 11],
                'accion'      => ['type' => 'ENUM', 'constraint' => ['alta', 'modificacion', 'eliminacion']],
                'detalle'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'id_user'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'usuario'     => ['type' => 'VARCHAR', 'constraint' => 100],
                'fecha'       => ['type' => 'DATETIME', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['tabla', 'id_registro', 'id'], false, false, 'idx_auditoria_registro');
            $this->forge->addKey('fecha', false, false, 'idx_auditoria_fecha');
            $this->forge->createTable('auditoria', true, [
                'ENGINE'  => 'InnoDB',
                'CHARSET' => 'utf8mb4',
                'COLLATE' => 'utf8mb4_general_ci',
            ]);
        }

        $this->borrarTriggers();

        foreach ($this->triggersAuditoria() as $sql) {
            $this->db->query($sql);
        }

        foreach (self::BITACORAS as $tabla) {
            if ($this->db->tableExists($tabla)) {
                foreach ($this->triggersInmutables($tabla) as $sql) {
                    $this->db->query($sql);
                }
            }
        }
    }

    public function down()
    {
        $this->borrarTriggers();
        // La tabla `auditoria` se conserva a propósito: borrar la migración no debe borrar
        // el historial. Si de verdad se quiere eliminar, hay que hacerlo a mano.
    }

    private function borrarTriggers(): void
    {
        $nombres = self::TRIGGERS_AUDITORIA;
        foreach (self::BITACORAS as $tabla) {
            $nombres[] = "inm_{$tabla}_bu";
            $nombres[] = "inm_{$tabla}_bd";
        }

        foreach ($nombres as $nombre) {
            $this->db->query("DROP TRIGGER IF EXISTS `{$nombre}`");
        }
    }

    /** @return list<string> */
    private function triggersInmutables(string $tabla): array
    {
        $mensaje = "Error: la bitácora ({$tabla}) es de solo consulta; sus registros no se pueden modificar ni borrar.";

        return [
            "CREATE TRIGGER inm_{$tabla}_bu BEFORE UPDATE ON `{$tabla}` FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'",
            "CREATE TRIGGER inm_{$tabla}_bd BEFORE DELETE ON `{$tabla}` FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'",
        ];
    }

    /** @return list<string> */
    private function triggersAuditoria(): array
    {
        $q = self::QUIEN;

        // ── Cliente: solo los NOMBRES de los campos que cambiaron ──
        $cliente = [
            "IF(NOT (OLD.nombre <=> NEW.nombre), 'nombre', NULL)",
            "IF(NOT (OLD.ape_pat <=> NEW.ape_pat), 'apellido paterno', NULL)",
            "IF(NOT (OLD.ape_mat <=> NEW.ape_mat), 'apellido materno', NULL)",
            "IF(NOT (OLD.telefono <=> NEW.telefono), 'teléfono', NULL)",
        ];
        if ($this->db->fieldExists('id_user', 'cliente')) {
            $cliente[] = "IF(NOT (OLD.id_user <=> NEW.id_user), CONCAT('cuenta #', IFNULL(OLD.id_user, '-'), ' -> #', IFNULL(NEW.id_user, '-')), NULL)";
        }
        if ($this->db->fieldExists('preferencial_modo', 'cliente')) {
            $cliente[] = "IF(NOT (OLD.preferencial_modo <=> NEW.preferencial_modo), CONCAT('preferencial ', OLD.preferencial_modo, ' -> ', NEW.preferencial_modo), NULL)";
            $cliente[] = "IF(NOT (OLD.preferencial_motivo <=> NEW.preferencial_motivo), 'motivo del preferencial', NULL)";
        }
        $cambiosCliente = 'CONCAT_WS(\', \', ' . implode(', ', $cliente) . ')';

        // ── Producto: valores anterior y nuevo ──
        $producto = [
            "IF(NOT (OLD.nombre <=> NEW.nombre), CONCAT('nombre «', OLD.nombre, '» -> «', NEW.nombre, '»'), NULL)",
            "IF(NOT (OLD.descripcion <=> NEW.descripcion), 'descripción', NULL)",
            "IF(NOT (OLD.categoria <=> NEW.categoria), CONCAT('categoría ', IF(OLD.categoria = '', '-', OLD.categoria), ' -> ', IF(NEW.categoria = '', '-', NEW.categoria)), NULL)",
            "IF(NOT (OLD.img <=> NEW.img), 'imagen', NULL)",
        ];
        if ($this->db->fieldExists('codigo', 'producto')) {
            $producto[] = "IF(NOT (OLD.codigo <=> NEW.codigo), CONCAT('código ', IFNULL(OLD.codigo, '-'), ' -> ', IFNULL(NEW.codigo, '-')), NULL)";
        }
        $cambiosProducto = 'CONCAT_WS(\', \', ' . implode(', ', $producto) . ')';

        $insertar = static fn (string $tabla, string $id, string $accion, string $detalle): string =>
            "INSERT INTO auditoria (tabla, id_registro, accion, detalle, id_user, usuario)
             VALUES ('{$tabla}', {$id}, '{$accion}', LEFT({$detalle}, 500), {$q})";

        return [
            "CREATE TRIGGER aud_cliente_ai AFTER INSERT ON cliente FOR EACH ROW
                {$insertar('cliente', 'NEW.id', 'alta', "'Alta del cliente'")}",

            "CREATE TRIGGER aud_cliente_au AFTER UPDATE ON cliente FOR EACH ROW
             BEGIN
                DECLARE cambio VARCHAR(500) DEFAULT LEFT({$cambiosCliente}, 480);
                IF cambio <> '' THEN
                    {$insertar('cliente', 'NEW.id', 'modificacion', "CONCAT('Cambió: ', cambio)")};
                END IF;
             END",

            "CREATE TRIGGER aud_cliente_ad AFTER DELETE ON cliente FOR EACH ROW
                {$insertar('cliente', 'OLD.id', 'eliminacion', "'Cliente eliminado'")}",

            "CREATE TRIGGER aud_producto_ai AFTER INSERT ON producto FOR EACH ROW
                {$insertar('producto', 'NEW.id', 'alta', "CONCAT('Alta: «', NEW.nombre, '» (', IF(NEW.categoria = '', 'sin categoría', NEW.categoria), ')')")}",

            "CREATE TRIGGER aud_producto_au AFTER UPDATE ON producto FOR EACH ROW
             BEGIN
                DECLARE cambio VARCHAR(500) DEFAULT LEFT({$cambiosProducto}, 480);
                IF cambio <> '' THEN
                    {$insertar('producto', 'NEW.id', 'modificacion', "CONCAT('Cambió: ', cambio)")};
                END IF;
             END",

            "CREATE TRIGGER aud_producto_ad AFTER DELETE ON producto FOR EACH ROW
                {$insertar('producto', 'OLD.id', 'eliminacion', "CONCAT('Eliminó: «', OLD.nombre, '»')")}",
        ];
    }
}
