<?php

namespace App\Libraries\Termica;

/**
 * Impresora USB/local de Windows COMPARTIDA (recurso \\equipo\nombre).
 *
 * Envía los bytes sin procesar con `copy /B`, igual que hacen las librerías
 * ESC/POS más usadas. Requisitos en Windows:
 *   1. Instalar la impresora (driver genérico "Generic / Text Only" sirve).
 *   2. Propiedades de la impresora → Compartir → marcar "Compartir esta impresora"
 *      y darle un nombre corto sin espacios (ej.: POS80).
 *   3. Que el servicio de Apache tenga permiso de usarla (en XAMPP normalmente
 *      basta con iniciarlo desde el Panel de control de XAMPP).
 *
 * Nota: el spooler de Windows acepta el trabajo aunque la impresora esté
 * apagada o sin papel, por lo que aquí no se puede detectar ese estado.
 */
class ConectorWindows implements Conector
{
    public function __construct(
        private string $equipo,
        private string $nombreCompartido,
        private int $timeout = 8
    ) {
        $this->equipo  = $equipo !== '' ? $equipo : 'localhost';
        $this->timeout = max(2, min(60, $timeout));
    }

    public function destino(): string
    {
        return "\\\\{$this->equipo}\\{$this->nombreCompartido}";
    }

    public function advertencias(): array
    {
        return [];
    }

    public function enviar(string $datos): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw ImpresoraException::configInvalida(
                'La conexión "windows" solo funciona cuando el servidor corre en Windows.',
                'Usa "red", "puerto" o "cups" según tu caso.'
            );
        }

        // Lista blanca de caracteres: evita inyección de comandos en `cmd`.
        if (! preg_match('/^[A-Za-z0-9_.\-]{1,63}$/', $this->equipo)
            || ! preg_match('/^[A-Za-z0-9_.\- ]{1,80}$/', $this->nombreCompartido)) {
            throw ImpresoraException::configInvalida(
                'El nombre de la impresora compartida no es válido.',
                'Configura impresora.nombreCompartido con el nombre exacto con que compartiste la impresora (solo letras, números, guion y punto).'
            );
        }

        if (! function_exists('proc_open')) {
            throw ImpresoraException::sinEjecucion('proc_open no está disponible');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'tk');

        if ($tmp === false || file_put_contents($tmp, $datos) === false) {
            throw ImpresoraException::fallo(
                'No se pudo preparar el ticket para imprimir.',
                'Revisa que la carpeta temporal del sistema tenga espacio y permisos de escritura.',
                'tempnam/file_put_contents falló'
            );
        }

        try {
            $this->copiar($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    private function copiar(string $archivo): void
    {
        $comando = ['cmd', '/C', 'copy', '/Y', '/B', $archivo, $this->destino()];

        $proceso = @proc_open(
            $comando,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
            null,
            null,
            ['bypass_shell' => true]
        );

        if (! is_resource($proceso)) {
            throw ImpresoraException::sinEjecucion('proc_open devolvió false');
        }

        stream_set_blocking($tuberias[1], false);
        stream_set_blocking($tuberias[2], false);

        $salida = '';
        $inicio = microtime(true);
        $codigo = -1;

        while (true) {
            $salida .= (string) stream_get_contents($tuberias[1]);
            $salida .= (string) stream_get_contents($tuberias[2]);

            $estado = proc_get_status($proceso);
            if (! $estado['running']) {
                $codigo = $estado['exitcode'];
                break;
            }

            if (microtime(true) - $inicio > $this->timeout) {
                proc_terminate($proceso);
                fclose($tuberias[1]);
                fclose($tuberias[2]);
                proc_close($proceso);

                throw ImpresoraException::tiempoAgotado($this->destino(), 'copy no terminó a tiempo');
            }

            usleep(50000);
        }

        $salida .= (string) stream_get_contents($tuberias[1]);
        $salida .= (string) stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);
        proc_close($proceso);

        if ($codigo !== 0) {
            // La consola de Windows usa CP850 en español; lo pasamos a UTF-8 solo para el log.
            $detalle = trim((string) @mb_convert_encoding($salida, 'UTF-8', 'CP850'));

            throw ImpresoraException::permiso(
                $this->destino(),
                'Verifica que la impresora esté compartida con ese nombre exacto (Propiedades → Compartir) '
                . 'y que Apache tenga permiso para usarla.',
                "copy /B terminó con código $codigo: $detalle"
            );
        }
    }
}
