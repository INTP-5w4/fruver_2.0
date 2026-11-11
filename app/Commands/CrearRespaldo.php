<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

/**
 * Respaldo comprimido de la base de datos (Aviso de Privacidad, sección 10).
 *
 * Uso:   php spark respaldo:crear
 *
 *  - Usa mysqldump con --single-transaction (no bloquea a quien está usando el sistema) e
 *    incluye triggers y rutinas, necesarios para que la base restaurada funcione igual.
 *  - La contraseña va en un archivo temporal de opciones (no en la línea de comandos,
 *    donde otros procesos podrían verla) y se borra al terminar.
 *  - Se guarda como fruver_AAAAMMDD_HHMMSS.sql.gz y se borran los de más de
 *    Config\Respaldo::$diasConservar días (plazo de conservación).
 *
 * Restaurar:  gunzip -c archivo.sql.gz | mysql -u USUARIO -p fruver
 */
class CrearRespaldo extends BaseCommand
{
    protected $group       = 'Fruver';
    protected $name        = 'respaldo:crear';
    protected $description = 'Crea un respaldo comprimido de la base de datos y borra los vencidos.';
    protected $usage       = 'respaldo:crear';

    public function run(array $params)
    {
        $cfg     = config('Respaldo');
        $db      = config('Database')->{config('Database')->defaultGroup};
        $carpeta = $cfg->rutaCarpeta();
        $destino = $carpeta . 'fruver_' . date('Ymd_His') . '.sql.gz';
        $opciones = null;

        try {
            if (! is_dir($carpeta) && ! mkdir($carpeta, 0750, true) && ! is_dir($carpeta)) {
                throw new RuntimeException('No se pudo crear la carpeta ' . $carpeta);
            }

            $opciones = $this->archivoOpciones($db);
            $this->volcar($cfg->rutaMysqldump(), $opciones, (string) $db['database'], $destino);

            CLI::write('Respaldo creado: ' . $destino . ' (' . number_format(filesize($destino) / 1024, 1) . ' KB)', 'green');
            CLI::write('Respaldos vencidos borrados: ' . $this->depurar($carpeta, max(1, $cfg->diasConservar)));
        } catch (Throwable $e) {
            if (is_file($destino)) {
                @unlink($destino); // un respaldo a medias no sirve y confunde
            }
            CLI::error('No se pudo crear el respaldo: ' . $e->getMessage());

            return EXIT_ERROR;
        } finally {
            if ($opciones !== null && is_file($opciones)) {
                @unlink($opciones);
            }
        }

        return EXIT_SUCCESS;
    }

    /** Archivo [client] con las credenciales, legible solo por el dueño. */
    private function archivoOpciones(array $db): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'fvr');
        if ($ruta === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal de credenciales.');
        }
        @chmod($ruta, 0600);

        $valor = static fn (mixed $v): string => '"' . addcslashes((string) $v, "\\\"") . '"';

        file_put_contents($ruta, implode("\n", [
            '[client]',
            'user=' . $valor($db['username'] ?? ''),
            'password=' . $valor($db['password'] ?? ''),
            'host=' . $valor($db['hostname'] ?? 'localhost'),
            'port=' . (int) ($db['port'] ?? 3306),
            '',
        ]));

        return $ruta;
    }

    /** Ejecuta mysqldump (sin shell) y comprime la salida al vuelo. */
    private function volcar(string $mysqldump, string $opciones, string $baseDatos, string $destino): void
    {
        $comando = [
            $mysqldump,
            '--defaults-extra-file=' . $opciones, // debe ser la primera opción
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--hex-blob',
            $baseDatos,
        ];

        // Los errores van a un archivo (no a una tubería) para que nunca se bloquee la lectura.
        $archivoErrores = tempnam(sys_get_temp_dir(), 'fve');
        $proceso        = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['file', $archivoErrores, 'w']], $tuberias);
        if (! is_resource($proceso)) {
            @unlink($archivoErrores);

            throw new RuntimeException('No se pudo ejecutar mysqldump (' . $mysqldump . ').');
        }

        $gz = gzopen($destino, 'wb6');
        if ($gz === false) {
            fclose($tuberias[1]);
            proc_close($proceso);
            @unlink($archivoErrores);

            throw new RuntimeException('No se pudo escribir ' . $destino);
        }

        while (! feof($tuberias[1])) {
            $bloque = fread($tuberias[1], 1 << 16);
            if ($bloque !== false && $bloque !== '') {
                gzwrite($gz, $bloque);
            }
        }
        gzclose($gz);
        fclose($tuberias[1]);

        $codigo  = proc_close($proceso);
        $errores = trim((string) @file_get_contents($archivoErrores));
        @unlink($archivoErrores);

        if ($codigo !== 0) {
            throw new RuntimeException('mysqldump terminó con código ' . $codigo . ': ' . mb_substr($errores, 0, 500));
        }
    }

    private function depurar(string $carpeta, int $dias): int
    {
        $limite   = time() - $dias * DAY;
        $borrados = 0;

        foreach (glob($carpeta . 'fruver_*.sql.gz') ?: [] as $archivo) {
            if (filemtime($archivo) < $limite && @unlink($archivo)) {
                $borrados++;
            }
        }

        return $borrados;
    }
}
