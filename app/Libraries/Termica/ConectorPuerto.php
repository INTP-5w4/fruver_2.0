<?php

namespace App\Libraries\Termica;

/**
 * Puertos y dispositivos de escritura directa:
 *   Windows : COM3 (USB virtual, Bluetooth emparejado o serie real), LPT1
 *   Linux   : /dev/usb/lp0, /dev/rfcomm0, /dev/ttyUSB0, /dev/ttyS0
 */
class ConectorPuerto implements Conector
{
    public function __construct(
        private string $puerto,
        private int $baudios = 9600
    ) {
    }

    public function destino(): string
    {
        return $this->puerto;
    }

    public function advertencias(): array
    {
        return [];
    }

    public function enviar(string $datos): void
    {
        $ruta = $this->rutaNormalizada();

        $this->configurarVelocidad();

        $errorPrevio = error_get_last();
        $f = @fopen($ruta, 'wb');

        if ($f === false) {
            throw $this->errorAlAbrir($errorPrevio);
        }

        try {
            stream_set_write_buffer($f, 0);

            $total   = strlen($datos);
            $escrito = 0;

            while ($escrito < $total) {
                $n = @fwrite($f, substr($datos, $escrito, 1024));

                if ($n === false || $n === 0) {
                    throw ImpresoraException::conexionPerdida("Escritos $escrito de $total bytes en {$this->puerto}");
                }

                $escrito += $n;
            }

            @fflush($f);
        } finally {
            fclose($f);
        }
    }

    /** Valida el nombre del puerto (lista blanca) y añade \\.\ a los COM ≥ 10 de Windows. */
    private function rutaNormalizada(): string
    {
        $p = trim($this->puerto);

        $esWindows = PHP_OS_FAMILY === 'Windows';

        if (preg_match('/^COM(\d{1,3}):?$/i', $p, $m)) {
            $this->exigirSistema($esWindows, 'COM', 'Windows');

            return ((int) $m[1] >= 10) ? '\\\\.\\COM' . $m[1] : 'COM' . $m[1];
        }

        if (preg_match('/^LPT[1-9]:?$/i', $p)) {
            $this->exigirSistema($esWindows, 'LPT', 'Windows');

            return rtrim($p, ':');
        }

        if (preg_match('#^/dev/[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*$#', $p) && ! str_contains($p, '..')) {
            $this->exigirSistema(! $esWindows, '/dev/...', 'Linux/macOS');

            // fopen(..., 'wb') crearía un archivo normal si el dispositivo no existe.
            if (! file_exists($p)) {
                throw $this->puertoInexistente("fopen({$p}): el dispositivo no existe");
            }

            return $p;
        }

        throw ImpresoraException::configInvalida(
            'El puerto de la impresora no es válido.',
            'Configura impresora.puerto con algo como COM3, LPT1, /dev/usb/lp0 o /dev/rfcomm0.'
        );
    }

    private function exigirSistema(bool $cumple, string $tipo, string $sistema): void
    {
        if (! $cumple) {
            throw ImpresoraException::configInvalida(
                "Los puertos {$tipo} solo existen en {$sistema}.",
                'Revisa impresora.puerto: usa COM3/LPT1 en Windows o /dev/usb/lp0 en Linux.'
            );
        }
    }

    private function puertoInexistente(string $detalle): ImpresoraException
    {
        return new ImpresoraException(
            'puerto_inexistente',
            "El puerto {$this->puerto} no existe.",
            'Conecta y enciende la impresora, y revisa el número de puerto (Administrador de dispositivos en Windows, o `ls /dev/usb` en Linux).',
            $detalle
        );
    }

    /** Ajusta baudios en puertos serie reales (mejor esfuerzo; no falla si no puede). */
    private function configurarVelocidad(): void
    {
        if (! function_exists('exec') || $this->baudios < 1200 || $this->baudios > 921600) {
            return;
        }

        $p = trim($this->puerto);
        $b = (int) $this->baudios;

        if (PHP_OS_FAMILY === 'Windows' && preg_match('/^COM\d{1,3}:?$/i', $p)) {
            @exec('mode ' . rtrim($p, ':') . ": BAUD=$b PARITY=N DATA=8 STOP=1 2>NUL");
        } elseif (preg_match('#^/dev/(ttyS|ttyUSB|ttyACM)\d+$#', $p)) {
            @exec('stty -F ' . escapeshellarg($p) . " $b raw -echo 2>/dev/null");
        }
    }

    private function errorAlAbrir(?array $errorPrevio): ImpresoraException
    {
        $ultimo  = error_get_last();
        $mensaje = ($ultimo && $ultimo !== $errorPrevio) ? (string) $ultimo['message'] : 'fopen falló';
        $texto   = strtolower($mensaje);
        $detalle = "fopen({$this->puerto}): $mensaje";

        if (str_contains($texto, 'permission denied') || str_contains($texto, 'acceso denegado') || str_contains($texto, 'access is denied')) {
            $sugerencia = PHP_OS_FAMILY === 'Windows'
                ? 'Otro programa podría estar usando el puerto. Ciérralo e inténtalo de nuevo.'
                : 'El usuario del servidor web necesita permiso: agrégalo al grupo del dispositivo (ej. sudo usermod -aG lp www-data) y reinicia Apache.';

            return ImpresoraException::permiso($this->puerto, $sugerencia, $detalle);
        }

        if (str_contains($texto, 'no such file') || str_contains($texto, 'cannot find') || str_contains($texto, 'no se puede encontrar') || str_contains($texto, 'not found')) {
            return $this->puertoInexistente($detalle);
        }

        return ImpresoraException::sinConexion($this->puerto, $detalle);
    }
}
