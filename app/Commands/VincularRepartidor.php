<?php

namespace App\Commands;

use App\Models\Modelo_repartidor;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Enlaza una cuenta de acceso (Shield) con un repartidor y le da el grupo "repartidor".
 *
 * Uso:   php spark repartidor:vincular correo@ejemplo.com 3
 *        (correo de una cuenta ya registrada, id de la tabla `repartidor`)
 */
class VincularRepartidor extends BaseCommand
{
    protected $group       = 'Fruver';
    protected $name        = 'repartidor:vincular';
    protected $description = 'Vincula una cuenta registrada con un repartidor y le asigna el grupo repartidor.';
    protected $usage       = 'repartidor:vincular <correo> <id_repartidor>';
    protected $arguments   = [
        'correo'        => 'Correo de la cuenta ya registrada.',
        'id_repartidor' => 'Id del repartidor (tabla repartidor).',
    ];

    public function run(array $params)
    {
        $correo = trim((string) ($params[0] ?? ''));
        $idRep  = $params[1] ?? '';

        if ($correo === '' || ! ctype_digit((string) $idRep)) {
            CLI::error('Uso: php spark repartidor:vincular <correo> <id_repartidor>');

            return EXIT_USER_INPUT;
        }

        $m_repartidor = new Modelo_repartidor();

        if ($m_repartidor->find((int) $idRep) === null) {
            CLI::error("No existe el repartidor {$idRep}.");

            return EXIT_ERROR;
        }

        $usuario = auth()->getProvider()->findByCredentials(['email' => $correo]);

        if ($usuario === null) {
            CLI::error("No hay una cuenta registrada con el correo {$correo}.");

            return EXIT_ERROR;
        }

        try {
            $m_repartidor->vincularUsuario((int) $idRep, (int) $usuario->id);
            $usuario->addGroup('repartidor');
        } catch (Throwable $e) {
            CLI::error('No se pudo vincular (¿la cuenta ya pertenece a otro repartidor?): ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write("Listo: {$correo} ahora es el repartidor {$idRep}.", 'green');

        return EXIT_SUCCESS;
    }
}
