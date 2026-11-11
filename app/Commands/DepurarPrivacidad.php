<?php

namespace App\Commands;

use App\Models\Modelo_consentimiento;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Aplica los plazos de conservación de la sección 9 del Aviso de Privacidad:
 *  - borra los registros de inicio de sesión (auth_logins) más viejos que
 *    Config\Privacidad::$diasRegistroAccesos;
 *  - borra los identificadores de "recordarme" ya vencidos.
 *
 * Uso:   php spark privacidad:depurar
 * Programarlo una vez al día (Programador de tareas de Windows o cron).
 */
class DepurarPrivacidad extends BaseCommand
{
    protected $group       = 'Fruver';
    protected $name        = 'privacidad:depurar';
    protected $description = 'Borra registros de acceso y "recordarme" vencidos según el Aviso de Privacidad.';
    protected $usage       = 'privacidad:depurar';

    public function run(array $params)
    {
        $cfg    = config('Privacidad');
        $modelo = new Modelo_consentimiento();

        try {
            $vencidos = $modelo->depurarRecordarmeVencidos();
            CLI::write("Identificadores \"recordarme\" vencidos borrados: {$vencidos}");

            if ($cfg->diasRegistroAccesos === null) {
                CLI::write(
                    'El plazo del registro de inicios de sesión no está definido (Config\Privacidad::$diasRegistroAccesos); no se borró ninguno.',
                    'yellow'
                );

                return EXIT_SUCCESS;
            }

            $borrados = $modelo->depurarRegistroAccesos($cfg->diasRegistroAccesos);
            CLI::write("Registros de inicio de sesión con más de {$cfg->diasRegistroAccesos} días borrados: {$borrados}", 'green');
        } catch (Throwable $e) {
            CLI::error('No se pudo depurar: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
