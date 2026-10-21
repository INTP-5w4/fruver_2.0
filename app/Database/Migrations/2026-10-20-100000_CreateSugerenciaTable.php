<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Tabla `sugerencia`: buzón de sugerencias para mejorar el software.
 *
 *  - `usuario` y `rol` guardan una COPIA del nombre y de los roles al momento de enviar,
 *    así la sugerencia conserva su autoría aunque el usuario cambie de rol o se elimine.
 *  - `id_usuario` enlaza con `users.id` de Shield. No lleva llave foránea a propósito:
 *    así la migración no puede fallar por una diferencia de tipo de columna y la
 *    sugerencia sobrevive si la cuenta se borra.
 *  - `fecha_creacion` la asigna la base de datos; el usuario nunca la ve ni la envía.
 */
class CreateSugerenciaTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'titulo' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'descripcion' => [
                'type' => 'TEXT',
            ],
            'valoracion' => [
                'type'       => 'ENUM',
                'constraint' => ['No muy importante', 'Importante', 'Muy importante'],
            ],
            'fecha_creacion' => [
                'type'    => 'TIMESTAMP',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'usuario' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'rol' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('id_usuario', false, false, 'idx_sugerencia_usuario');
        $this->forge->addKey('fecha_creacion', false, false, 'idx_sugerencia_fecha');

        $this->forge->createTable('sugerencia', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_general_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('sugerencia', true);
    }
}
