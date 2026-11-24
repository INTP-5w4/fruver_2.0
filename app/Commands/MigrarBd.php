<?php

namespace App\Commands;

use App\Libraries\PrivilegiosBd;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Migraciones con la cuenta de administración y, después, permisos al día para la cuenta
 * de la aplicación (las tablas nuevas no quedan accesibles hasta otorgarlos).
 *
 *   php spark bd:migrar
 *
 * Reemplaza a `php spark migrate`, que con la cuenta de la aplicación no puede crear ni
 * alterar tablas.
 *
 * Por qué no `migrate -g admin`: el historial de migraciones se guarda por grupo; con otro
 * grupo, CodeIgniter creería que ninguna migración se ha corrido e intentaría repetirlas
 * todas. Aquí se usa el grupo "default" de siempre, pero con las credenciales de
 * administración y solo dentro de este proceso.
 */
class MigrarBd extends BaseCommand
{
    protected $group       = 'Fruver';
    protected $name        = 'bd:migrar';
    protected $description = 'Corre las migraciones con la cuenta de administración y actualiza los permisos de la aplicación.';
    protected $usage       = 'bd:migrar';

    public function run(array $params)
    {
        $config     = config('Database');
        $usuarioApp = (string) $config->default['username'];
        $separado   = $config->adminSeparado();

        // Desde aquí, "default" (migraciones, Shield, PDO) se conecta como administración.
        $config->default['username'] = $config->admin['username'];
        $config->default['password'] = $config->admin['password'];

        try {
            echo command('migrate --all');
        } catch (Throwable $e) {
            CLI::error('No se pudieron correr las migraciones: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        if (! $separado) {
            CLI::write('La aplicación todavía usa la cuenta de administración. Para pasarla a una de privilegios mínimos: php spark bd:asegurar', 'yellow');

            return EXIT_SUCCESS;
        }

        try {
            $priv     = new PrivilegiosBd(db_connect(), (string) $config->default['database']);
            $permisos = $priv->aplicar($usuarioApp, $priv->hostsDe($usuarioApp) ?: PrivilegiosBd::HOSTS);
            CLI::write('Permisos de ' . $usuarioApp . ' actualizados en ' . count($permisos) . ' tablas.', 'green');
        } catch (Throwable $e) {
            CLI::error('Las migraciones corrieron, pero no se pudieron actualizar los permisos: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
