<?php

namespace App\Libraries\Termica;

/**
 * Guarda los bytes en writable/tickets/ en vez de imprimirlos.
 * Útil para probar sin impresora o para depurar lo que se envía
 * (se puede inspeccionar con `xxd` o enviar luego con `copy /B` / `cat > /dev/usb/lp0`).
 */
class ConectorArchivo implements Conector
{
    private const MAX_ARCHIVOS = 50;

    private string $ultimo = '';

    public function __construct(private ?string $directorio = null)
    {
        $this->directorio ??= WRITEPATH . 'tickets';
    }

    public function destino(): string
    {
        return $this->directorio;
    }

    public function advertencias(): array
    {
        return [];
    }

    /** Ruta del último archivo escrito. */
    public function ultimoArchivo(): string
    {
        return $this->ultimo;
    }

    public function enviar(string $datos): void
    {
        if (! is_dir($this->directorio) && ! @mkdir($this->directorio, 0775, true) && ! is_dir($this->directorio)) {
            throw ImpresoraException::permiso(
                $this->directorio,
                'Da permisos de escritura a la carpeta writable.',
                "No se pudo crear {$this->directorio}"
            );
        }

        $ruta = $this->directorio . DIRECTORY_SEPARATOR
            . 'ticket_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.bin';

        if (@file_put_contents($ruta, $datos) === false) {
            throw ImpresoraException::permiso(
                $this->directorio,
                'Da permisos de escritura a la carpeta writable.',
                "No se pudo escribir $ruta"
            );
        }

        $this->ultimo = $ruta;
        $this->limpiar();
    }

    /** Conserva solo los últimos archivos para que la carpeta no crezca sin límite. */
    private function limpiar(): void
    {
        $archivos = glob($this->directorio . DIRECTORY_SEPARATOR . 'ticket_*.bin') ?: [];

        if (count($archivos) <= self::MAX_ARCHIVOS) {
            return;
        }

        sort($archivos);

        foreach (array_slice($archivos, 0, count($archivos) - self::MAX_ARCHIVOS) as $viejo) {
            @unlink($viejo);
        }
    }
}
