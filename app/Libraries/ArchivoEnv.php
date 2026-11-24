<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Cambia valores del archivo .env sin tocar el resto (comentarios, orden, otras llaves).
 * Lo usa `php spark bd:asegurar` para guardar las credenciales de la cuenta de la aplicación.
 */
final class ArchivoEnv
{
    /**
     * Fija cada llave. Si ya existe (activa o comentada con #) se reemplaza esa línea;
     * si no, se agrega al final bajo $titulo.
     *
     * @param array<string, string> $valores
     */
    public static function fijar(string $ruta, array $valores, string $titulo = ''): void
    {
        $texto = is_file($ruta) ? (string) file_get_contents($ruta) : '';
        $nl    = str_contains($texto, "\r\n") ? "\r\n" : "\n";
        $nuevas = [];

        foreach ($valores as $llave => $valor) {
            if (preg_match('/^[A-Za-z0-9_.]+$/', $llave) !== 1 || preg_match('/[\r\n"]/', $valor) === 1) {
                throw new RuntimeException('Llave o valor no válido para .env: ' . $llave);
            }

            $linea  = $llave . ' = ' . ($valor === '' ? '' : '"' . $valor . '"');
            $patron = '/^[ \t]*#?[ \t]*' . preg_quote($llave, '/') . '[ \t]*=.*$/m';

            if (preg_match($patron, $texto) === 1) {
                $texto = preg_replace($patron, str_replace(['\\', '$'], ['\\\\', '\\$'], $linea), $texto, 1);
            } else {
                $nuevas[] = $linea;
            }
        }

        if ($nuevas !== []) {
            $texto = rtrim($texto) . $nl . $nl . ($titulo !== '' ? '# ' . $titulo . $nl : '') . implode($nl, $nuevas) . $nl;
        }

        $temporal = $ruta . '.tmp';
        if (file_put_contents($temporal, $texto, LOCK_EX) === false || ! rename($temporal, $ruta)) {
            @unlink($temporal);

            throw new RuntimeException('No se pudo escribir ' . $ruta);
        }
    }
}
