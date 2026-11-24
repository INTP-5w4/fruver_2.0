<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabla `entrega`: una fila por INTENTO de entrega de un pedido.
 *
 *  - Un pedido puede tener varias filas (intento 1, 2, 3...) si una entrega falla
 *    y se reprograma. Si un pedido solo se entrega una vez, tendrá una sola fila.
 *  - Cada fila guarda el repartidor asignado, sus tiempos, el resultado, el cobro
 *    contra entrega, la evidencia y las incidencias.
 *
 * Los tipos de `id_pedido` e `id_repartidor` (INT(11) con signo) coinciden con
 * `pedido.id` y `repartidor.id` para que las llaves foráneas funcionen.
 */
class CreateEntregaTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'id_pedido' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'id_repartidor' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'intento' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 1,
            ],
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['asignada', 'en_ruta', 'entregada', 'fallida', 'cancelada'],
                'default'    => 'asignada',
            ],
            'fecha_asignacion' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
            'fecha_salida' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'fecha_entrega' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'monto_cobrado' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'metodo_cobro' => [
                'type'       => 'ENUM',
                'constraint' => ['efectivo', 'transferencia', 'tarjeta'],
                'null'       => true,
            ],
            'evidencia' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'incidencia' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'notas' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['id_pedido', 'intento'], 'uq_entrega_pedido_intento');
        $this->forge->addKey(['id_repartidor', 'estado'], false, false, 'idx_entrega_repartidor_estado');
        $this->forge->addForeignKey('id_pedido', 'pedido', 'id', 'CASCADE', 'RESTRICT', 'fk_entrega_pedido');
        $this->forge->addForeignKey('id_repartidor', 'repartidor', 'id', 'CASCADE', 'RESTRICT', 'fk_entrega_repartidor');

        $this->forge->createTable('entrega', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_general_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('entrega', true);
    }
}
