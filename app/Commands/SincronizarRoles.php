<?php

namespace App\Commands;

use App\Libraries\Roles;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Actualiza el ENUM de auth_groups_users.group con los roles de Config\AuthGroups.
 *
 * Uso:   php spark roles:sincronizar
 *
 * Córrelo después de agregar o quitar un rol en Config\AuthGroups. Los valores que ya no
 * correspondan a un rol pasan al rol por omisión (cliente).
 */
class SincronizarRoles extends BaseCommand
{
    protected $group       = 'Fruver';
    protected $name        = 'roles:sincronizar';
    protected $description = 'Actualiza el ENUM de roles de la base con los de Config\AuthGroups.';
    protected $usage       = 'roles:sincronizar';

    public function run(array $params)
    {
        $db = db_connect();

        try {
            if (Roles::enumAlDia($db)) {
                CLI::write('El ENUM ya coincide con los roles configurados: ' . implode(', ', Roles::claves()), 'green');

                return;
            }

            foreach (Roles::sincronizarEnum($db) as $cambio) {
                CLI::write('  ' . $cambio, 'yellow');
            }

            CLI::write('Listo. Roles en la base: ' . implode(', ', Roles::claves()), 'green');
        } catch (Throwable $e) {
            CLI::error('No se pudo actualizar el ENUM: ' . $e->getMessage());
        }
    }
}
