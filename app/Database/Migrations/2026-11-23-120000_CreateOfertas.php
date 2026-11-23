<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Ofertas (promociones) sobre el precio de la tienda.
 *
 * Una oferta baja el precio de venta de ciertos productos o de una categoría completa,
 * durante un rango de fechas y mientras esté activa. El precio con descuento se calcula
 * en el servidor (Modelo_producto::sqlCatalogo), así que se ve en la tienda y se cobra en
 * el pedido del cliente: el navegador nunca decide el precio.
 *
 *  - `oferta`: la promoción (tipo, valor, alcance, vigencia, interruptor).
 *  - `oferta_producto`: qué productos abarca cuando el alcance es «productos» (si el alcance
 *    es «categoria» esta tabla queda vacía para esa oferta).
 */
class CreateOfertas extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('oferta')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'nombre'       => ['type' => 'VARCHAR', 'constraint' => 100],
                'tipo'         => ['type' => 'ENUM', 'constraint' => ['porcentaje', 'precio'], 'default' => 'porcentaje'],
                'valor'        => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'alcance'      => ['type' => 'ENUM', 'constraint' => ['productos', 'categoria'], 'default' => 'productos'],
                'categoria'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'fecha_inicio' => ['type' => 'DATE', 'null' => true],
                'fecha_fin'    => ['type' => 'DATE', 'null' => true],
                'activa'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'creada'       => ['type' => 'DATETIME', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['activa', 'fecha_inicio', 'fecha_fin'], false, false, 'idx_oferta_vigencia');
            $this->forge->createTable('oferta', true, [
                'ENGINE'  => 'InnoDB',
                'CHARSET' => 'utf8mb4',
                'COLLATE' => 'utf8mb4_general_ci',
            ]);
        }

        if (! $this->db->tableExists('oferta_producto')) {
            $this->forge->addField([
                'id_oferta'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'id_producto' => ['type' => 'INT', 'constraint' => 11],
            ]);
            $this->forge->addPrimaryKey(['id_oferta', 'id_producto']);
            $this->forge->addKey('id_producto', false, false, 'idx_oferta_producto_prod');
            $this->forge->addForeignKey('id_oferta', 'oferta', 'id', 'CASCADE', 'CASCADE', 'fk_oferta_producto_oferta');
            $this->forge->createTable('oferta_producto', true, [
                'ENGINE'  => 'InnoDB',
                'CHARSET' => 'utf8mb4',
                'COLLATE' => 'utf8mb4_general_ci',
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('oferta_producto', true);
        $this->forge->dropTable('oferta', true);
    }
}
