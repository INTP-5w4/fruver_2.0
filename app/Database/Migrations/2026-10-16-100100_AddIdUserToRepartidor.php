<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Enlaza al repartidor con su cuenta de acceso (tabla `users` de Shield).
 *
 * Una cuenta = un repartidor (UNIQUE). Si se borra la cuenta, el repartidor
 * se conserva (ON DELETE SET NULL), igual que se hizo con `cliente.id_user`.
 * Con este enlace el sistema sabe qué entregas pertenecen al usuario logueado.
 *
 * Requiere que ya existan las tablas de Shield (`php spark migrate --all`).
 */
class AddIdUserToRepartidor extends Migration
{
    public function up()
    {
        $this->forge->addColumn('repartidor', [
            'id_user' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'notas',
            ],
        ]);

        $this->db->query('ALTER TABLE `repartidor` ADD UNIQUE KEY `uq_repartidor_id_user` (`id_user`)');
        $this->db->query(
            'ALTER TABLE `repartidor` ADD CONSTRAINT `fk_repartidor_user` FOREIGN KEY (`id_user`) '
            . 'REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `repartidor` DROP FOREIGN KEY `fk_repartidor_user`');
        $this->db->query('ALTER TABLE `repartidor` DROP INDEX `uq_repartidor_id_user`');
        $this->forge->dropColumn('repartidor', 'id_user');
    }
}
