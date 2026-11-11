<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Tabla `consentimiento`: constancia del Aviso de Privacidad y de las finalidades
 * voluntarias (V1–V4) de cada cuenta (LFPDPPP; formato de consentimiento, sección 9).
 *
 *  - Solo se insertan filas, nunca se editan: cada aceptación, cambio o revocación es una
 *    fila nueva. El estado vigente de una cuenta es su fila más reciente y las anteriores
 *    son el historial (la revocación no es retroactiva).
 *  - Cada fila guarda fecha y hora, versión del aviso aceptada, IP y navegador, como pide
 *    la "aceptación electrónica" del formato.
 *  - `id_user` enlaza con `users.id` de Shield sin llave foránea (igual que `sugerencia`):
 *    la constancia sobrevive aunque la cuenta se elimine.
 */
class CreateConsentimientoTable extends Migration
{
    public function up()
    {
        $bandera = static fn (): array => [
            'type'       => 'TINYINT',
            'constraint' => 1,
            'unsigned'   => true,
            'null'       => false,
            'default'    => 0,
        ];

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_user' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'version_aviso' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'tipo_relacion' => [
                'type'       => 'ENUM',
                'constraint' => ['cliente', 'usuario_interno'],
            ],
            'acepta_aviso'     => $bandera(),
            'mayor_edad'       => $bandera(),
            'v1'               => $bandera(),
            'v2'               => $bandera(),
            'v3'               => $bandera(),
            'v4'               => $bandera(),
            'canal_telefono'   => $bandera(),
            'canal_mensajeria' => $bandera(),
            'canal_correo'     => $bandera(),
            'telefono_contacto' => [
                'type'       => 'VARCHAR',
                'constraint' => 12,
                'null'       => true,
            ],
            'origen' => [
                'type'       => 'ENUM',
                'constraint' => ['registro', 'aceptacion', 'preferencias', 'revocacion'],
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['id_user', 'id'], false, false, 'idx_consentimiento_usuario');

        $this->forge->createTable('consentimiento', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_general_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('consentimiento', true);
    }
}
