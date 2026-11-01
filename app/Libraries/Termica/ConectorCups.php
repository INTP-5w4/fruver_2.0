<?php

namespace App\Libraries\Termica;

/**
 * Cola CUPS (Linux/macOS): `lp -d <cola> -o raw`.
 * La cola debe crearse como impresora "Raw" (o con el driver "Generic / Raw Queue").
 */
class ConectorCups implements Conector
{
    public function __construct(
        private string $cola,
        private int $timeout = 8
    ) {
        $this->timeout = max(2, min(60, $timeout));
    }

    public function destino(): string
    {
        return "cola CUPS {$this->cola}";
    }

    public function advertencias(): array
    {
        return [];
    }

    public function enviar(string $datos): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            throw ImpresoraException::configInvalida(
                'La conexión "cups" no funciona en Windows.',
                'Usa "windows", "red" o "puerto".'
            );
        }

        if (! preg_match('/^[A-Za-z0-9_.\-]{1,100}$/', $this->cola)) {
            throw ImpresoraException::configInvalida(
                'El nombre de la cola CUPS no es válido.',
                'Configura impresora.nombreCompartido con el nombre de la cola (`lpstat -p` los lista).'
            );
        }

        if (! function_exists('proc_open')) {
            throw ImpresoraException::sinEjecucion('proc_open no está disponible');
        }

        // Comando como arreglo: no pasa por el shell, no hay inyección posible.
        $proceso = @proc_open(
            ['lp', '-d', $this->cola, '-o', 'raw'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias
        );

        if (! is_resource($proceso)) {
            throw ImpresoraException::fallo(
                'No se pudo ejecutar el comando de impresión (lp).',
                'Instala CUPS (sudo apt install cups) y verifica que el comando `lp` exista.',
                'proc_open(lp) devolvió false'
            );
        }

        fwrite($tuberias[0], $datos);
        fclose($tuberias[0]);

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

                throw ImpresoraException::tiempoAgotado($this->destino(), 'lp no terminó a tiempo');
            }

            usleep(50000);
        }

        $salida .= (string) stream_get_contents($tuberias[1]);
        $salida .= (string) stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);
        proc_close($proceso);

        if ($codigo !== 0) {
            $detalle = trim($salida);
            $texto   = strtolower($detalle);

            if (str_contains($texto, 'does not exist') || str_contains($texto, 'unknown destination') || str_contains($texto, 'no existe')) {
                throw new ImpresoraException(
                    'cola_inexistente',
                    "La cola de impresión \"{$this->cola}\" no existe en CUPS.",
                    'Revisa el nombre con `lpstat -p` o créala en http://localhost:631.',
                    "lp código $codigo: $detalle"
                );
            }

            throw ImpresoraException::fallo(
                'CUPS rechazó el trabajo de impresión.',
                'Revisa que la impresora esté activa y aceptando trabajos (`lpstat -t`).',
                "lp código $codigo: $detalle"
            );
        }
    }
}
