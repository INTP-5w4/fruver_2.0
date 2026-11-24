<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Envíos únicos (idempotencia) de los formularios que crean pedidos.
 *
 * Cada formulario lleva un token_envio aleatorio. La primera vez que llega se guarda aquí
 * en la MISMA transacción que crea el pedido; si el navegador lo vuelve a mandar (se cortó
 * la red y se reenvió, doble clic, «Reenviar formulario»), la llave primaria lo rechaza y se
 * responde con el pedido que ya existía en lugar de crear otro.
 *
 * Los registros solo sirven unos días; `php spark privacidad:depurar` borra los viejos.
 */
class CreateEnvioUnico extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('envio_unico')) {
            return;
        }

        $this->forge->addField([
            'token'      => ['type' => 'CHAR', 'constraint' => 32],
            'recurso'    => ['type' => 'VARCHAR', 'constraint' => 30],
            'id_recurso' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'id_user'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'fecha'      => ['type' => 'DATETIME', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);
        $this->forge->addPrimaryKey('token');
        $this->forge->addKey('fecha', false, false, 'idx_envio_unico_fecha');
        $this->forge->createTable('envio_unico', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_general_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('envio_unico', true);
    }
}
