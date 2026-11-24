<?php

namespace App\Commands;

use App\Libraries\Rutas\ErrorProveedorRutas;
use App\Libraries\Rutas\Geocodificador;
use App\Libraries\Rutas\ServiciosMapa;
use App\Libraries\Rutas\Punto;
use App\Models\Modelo_direccion;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Ubica en el mapa las direcciones que no tienen coordenadas (con openrouteservice o
 * Google, según rutas.proveedor) y refresca las que vinieron de Google hace más de
 * 30 días (Términos de servicio de Google Maps). Las de OpenStreetMap no vencen.
 *
 * Uso:   php spark direcciones:geocodificar            (pendientes, máx. 200)
 *        php spark direcciones:geocodificar --limite 50
 *        php spark direcciones:geocodificar --todas     (rehace todas las automáticas)
 *        php spark direcciones:geocodificar --prueba    (solo muestra, no guarda)
 *
 * Sugerencia: programarlo una vez por semana (cron / Programador de tareas).
 */
class GeocodificarDirecciones extends BaseCommand
{
    protected $group       = 'Fruver';
    protected $name        = 'direcciones:geocodificar';
    protected $description = 'Obtiene coordenadas para las direcciones sin ubicar (o vencidas, con Google).';
    protected $usage       = 'direcciones:geocodificar [--limite N] [--todas] [--prueba]';
    protected $options     = [
        '--limite' => 'Máximo de direcciones por ejecución (por omisión 200).',
        '--todas'  => 'Incluye todas las direcciones con coordenadas automáticas (no las de pin o GPS).',
        '--prueba' => 'No guarda nada; solo muestra lo que haría.',
    ];

    public function run(array $params)
    {
        $m_direccion = new Modelo_direccion();

        if (! $m_direccion->tieneColumnasGeo()) {
            CLI::error('Falta la migración de rutas. Ejecuta: php spark migrate');

            return EXIT_ERROR;
        }

        $geo = ServiciosMapa::geocodificador();
        if ($geo === null) {
            CLI::error('No hay servicio de búsqueda de direcciones: agrega rutas.llaveOrs (openrouteservice) al archivo .env');

            return EXIT_ERROR;
        }

        $limite = max(1, (int) (CLI::getOption('limite') ?? 200));
        $prueba = CLI::getOption('prueba') !== null;
        $dias   = config('Rutas')->diasVigenciaGoogle;
        $lista  = $m_direccion->porGeocodificar($limite, $dias, CLI::getOption('todas') !== null);

        if ($lista === []) {
            CLI::write('No hay direcciones pendientes.', 'green');

            return EXIT_SUCCESS;
        }

        $ok = $sinResultado = $aproximadas = 0;

        foreach ($lista as $d) {
            $texto = Geocodificador::textoDe($d);

            try {
                $r = $geo->geocodificar($texto);
            } catch (ErrorProveedorRutas $e) {
                CLI::error('El servicio de mapas respondió con error, se detiene: ' . $e->getMessage());

                return EXIT_ERROR;
            }

            if ($r === null) {
                $sinResultado++;
                CLI::write("  #{$d['id']} sin resultado: {$texto}", 'yellow');

                continue;
            }

            if ($r['precision_geo'] === 'APPROXIMATE') {
                $aproximadas++;
                CLI::write("  #{$d['id']} aproximada (revisar pin): {$texto}", 'yellow');
            }

            if (! $prueba) {
                $m_direccion->guardarCoordenadas((int) $d['id'], new Punto($r['lat'], $r['lng']), $geo->origen(), $r);
            }
            $ok++;

            usleep(700000); // ~85 por minuto: por debajo del límite por minuto de openrouteservice (100).
        }

        CLI::write(sprintf('Listo: %d ubicadas, %d sin resultado, %d aproximadas%s.', $ok, $sinResultado, $aproximadas, $prueba ? ' (prueba, sin guardar)' : ''), 'green');

        return EXIT_SUCCESS;
    }
}
