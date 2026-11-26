<?php

namespace App\Libraries\Termica;

use Config\Impresora;

/**
 * Ajustes editables de la impresora, persistidos en writable/impresora_ajustes.json.
 *
 * Se superponen a los valores de Config\Impresora (y a sus overrides de .env): lo que la
 * persona guarda desde /impresora gana; lo que no toca, conserva su valor base. NO se escribe
 * el archivo .env (guarda credenciales de la base de datos): el .env sigue valiendo como valor
 * por defecto por debajo de estos ajustes.
 *
 * Orden de prioridad (de menor a mayor): Config\Impresora → .env → este archivo JSON.
 */
class AjustesImpresora
{
    /** Contenido y formato del ticket: lo edita cualquier personal con acceso al panel. */
    public const CAMPOS_TICKET = [
        'negocioNombre', 'negocioSlogan', 'negocioDireccion', 'negocioTelefono',
        'mensajePie', 'simboloMoneda',
        'papelMm', 'columnas', 'codificacion', 'cancelarKanji',
        'corte', 'lineasFinales', 'copias', 'abrirCajon',
    ];

    /** Conexión/hardware: solo superadmin/developer (es_tecnico()). */
    public const CAMPOS_CONEXION = [
        'conexion', 'host', 'puertoRed', 'nombreCompartido',
        'puerto', 'baudios', 'timeoutSegundos', 'consultarEstado',
    ];

    /** Campos booleanos (un checkbox desmarcado no llega en el POST → se interpreta como false). */
    public const CAMPOS_BOOL = ['cancelarKanji', 'abrirCajon', 'consultarEstado'];

    public const CODIFICACIONES = ['cp858', 'cp850', 'cp437', 'cp1252', 'ascii'];
    public const CORTES         = ['parcial', 'completo', 'ninguno'];
    public const CONEXIONES     = ['navegador', 'red', 'windows', 'puerto', 'cups', 'archivo'];

    /**
     * Valores estándar sugeridos para los campos de conexión que, de fábrica, van vacíos.
     * Así el formulario no se muestra en blanco: se ofrece un valor razonable y editable
     * (no cambia el comportamiento en modo «navegador», donde estos campos no se usan).
     *  - host             : rango privado típico de una impresora de red/WiFi.
     *  - nombreCompartido : nombre habitual de las térmicas genéricas de 80 mm.
     *  - puerto           : primer puerto serie/USB habitual en Windows.
     */
    public const SUGERIDOS_CONEXION = [
        'host'             => '192.168.1.100',
        'nombreCompartido' => 'POS80',
        'puerto'           => 'COM3',
    ];

    /**
     * Valores por defecto de todos los campos editables (el «estándar razonable»).
     * Config\Impresora (más sus overrides de .env) provee la base; los campos de conexión
     * que quedarían vacíos reciben un valor sugerido. NO incluye los ajustes guardados en
     * el JSON: es justo el estado al que vuelve el botón «Restaurar valores por defecto».
     *
     * @return array<string,mixed>
     */
    public static function predeterminados(): array
    {
        $c   = new Impresora();
        $def = [];

        foreach (array_merge(self::CAMPOS_TICKET, self::CAMPOS_CONEXION) as $campo) {
            $def[$campo] = $c->{$campo};
        }

        foreach (self::SUGERIDOS_CONEXION as $campo => $valor) {
            if (($def[$campo] ?? '') === '') {
                $def[$campo] = $valor;
            }
        }

        return $def;
    }

    private static function archivo(): string
    {
        return WRITEPATH . 'impresora_ajustes.json';
    }

    /**
     * Valores guardados (ya validados al escribir). Solo claves conocidas.
     *
     * @return array<string,mixed>
     */
    public static function cargar(): array
    {
        $ruta = self::archivo();

        if (! is_file($ruta)) {
            return [];
        }

        $json  = @file_get_contents($ruta);
        $datos = $json === false ? null : json_decode($json, true);

        if (! is_array($datos)) {
            return [];
        }

        $validos = array_merge(self::CAMPOS_TICKET, self::CAMPOS_CONEXION);

        return array_intersect_key($datos, array_flip($validos));
    }

    /** Aplica los ajustes guardados sobre un objeto de configuración y lo devuelve. */
    public static function aplicar(Impresora $c): Impresora
    {
        foreach (self::cargar() as $campo => $valor) {
            if (property_exists($c, $campo)) {
                $c->{$campo} = $valor;
            }
        }

        return $c;
    }

    /**
     * Valida la entrada del formulario. Solo procesa los campos permitidos según
     * $incluirConexion (los de conexión únicamente para superadmin/developer).
     *
     * @param array<string,mixed> $entrada
     * @return array{0:array<string,mixed>,1:array<string,string>} [valores válidos, errores por campo]
     */
    public static function validar(array $entrada, bool $incluirConexion): array
    {
        $campos = $incluirConexion
            ? array_merge(self::CAMPOS_TICKET, self::CAMPOS_CONEXION)
            : self::CAMPOS_TICKET;

        $out = [];
        $err = [];

        foreach ($campos as $campo) {
            // Checkbox desmarcado: no viaja en el POST → false.
            if (! array_key_exists($campo, $entrada)) {
                if (in_array($campo, self::CAMPOS_BOOL, true)) {
                    $out[$campo] = false;
                }
                continue;
            }

            $v = $entrada[$campo];

            switch ($campo) {
                // ── Texto ──
                case 'negocioNombre':    $out[$campo] = self::texto($v, 40); break;
                case 'negocioSlogan':    $out[$campo] = self::texto($v, 40); break;
                case 'negocioDireccion': $out[$campo] = self::texto($v, 60); break;
                case 'negocioTelefono':  $out[$campo] = self::texto($v, 20); break;
                case 'mensajePie':       $out[$campo] = self::texto($v, 60); break;
                case 'simboloMoneda':    $out[$campo] = self::texto($v, 3) ?: '$'; break;
                case 'host':             $out[$campo] = self::texto($v, 120); break;
                case 'nombreCompartido': $out[$campo] = self::texto($v, 120); break;
                case 'puerto':           $out[$campo] = self::texto($v, 40); break;

                // ── Enteros ──
                case 'papelMm':
                    $out[$campo] = ((int) $v) <= 58 ? 58 : 80;
                    break;
                case 'columnas':
                    $n = (int) $v;
                    if ($n !== 0 && ($n < 16 || $n > 80)) {
                        $err[$campo] = 'Las columnas deben ser 0 (automático) o estar entre 16 y 80.';
                    } else {
                        $out[$campo] = $n;
                    }
                    break;
                case 'lineasFinales': $out[$campo] = max(0, min(10, (int) $v)); break;
                case 'copias':        $out[$campo] = max(1, min(5, (int) $v)); break;
                case 'baudios':       $out[$campo] = max(1200, min(115200, (int) $v)); break;
                case 'timeoutSegundos': $out[$campo] = max(1, min(30, (int) $v)); break;
                case 'puertoRed':
                    $n = (int) $v;
                    if ($n < 1 || $n > 65535) {
                        $err[$campo] = 'El puerto de red debe estar entre 1 y 65535.';
                    } else {
                        $out[$campo] = $n;
                    }
                    break;

                // ── Booleanos ──
                case 'cancelarKanji':
                case 'abrirCajon':
                case 'consultarEstado':
                    $out[$campo] = self::bool($v);
                    break;

                // ── Enumerados ──
                case 'codificacion':
                    $v = strtolower(trim((string) $v));
                    if (! in_array($v, self::CODIFICACIONES, true)) {
                        $err[$campo] = 'Codificación no válida.';
                    } else {
                        $out[$campo] = $v;
                    }
                    break;
                case 'corte':
                    $v = strtolower(trim((string) $v));
                    if (! in_array($v, self::CORTES, true)) {
                        $err[$campo] = 'Tipo de corte no válido.';
                    } else {
                        $out[$campo] = $v;
                    }
                    break;
                case 'conexion':
                    $v = strtolower(trim((string) $v));
                    if (! in_array($v, self::CONEXIONES, true)) {
                        $err[$campo] = 'Tipo de conexión no válido.';
                    } else {
                        $out[$campo] = $v;
                    }
                    break;
            }
        }

        return [$out, $err];
    }

    /**
     * Guarda los ajustes (fusiona con lo ya guardado). Devuelve true si se escribió.
     *
     * @param array<string,mixed> $valores ya validados por validar()
     */
    public static function guardar(array $valores): bool
    {
        $fusion = array_merge(self::cargar(), $valores);

        $json = json_encode($fusion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $dir = dirname(self::archivo());
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return @file_put_contents(self::archivo(), $json, LOCK_EX) !== false;
    }

    private static function texto($v, int $max): string
    {
        return mb_substr(trim((string) $v), 0, $max);
    }

    private static function bool($v): bool
    {
        return in_array($v, [true, 1, '1', 'on', 'true'], true);
    }
}
