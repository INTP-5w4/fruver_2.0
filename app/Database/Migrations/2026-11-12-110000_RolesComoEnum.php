<?php

namespace App\Database\Migrations;

use App\Libraries\Roles;
use CodeIgniter\Database\Migration;

/**
 * auth_groups_users.group pasa de texto libre a ENUM con los roles de Config\AuthGroups.
 *
 * Antes de cambiar el tipo corrige los valores escritos a mano: "Super Admin" → superadmin,
 * "Admin" → admin, etc. Lo que no se reconozca pasa a "user" (cliente). Ver App\Libraries\Roles.
 */
class RolesComoEnum extends Migration
{
    public function up()
    {
        foreach (Roles::sincronizarEnum($this->db) as $cambio) {
            log_message('notice', 'Roles: ' . $cambio);

            if (is_cli()) {
                echo "\t" . $cambio . PHP_EOL;
            }
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `' . Roles::TABLA . '` MODIFY `' . Roles::COLUMNA . '` VARCHAR(255) NOT NULL');
    }
}
