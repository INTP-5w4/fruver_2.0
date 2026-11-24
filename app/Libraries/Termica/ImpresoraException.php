<?php

namespace App\Libraries\Termica;

use RuntimeException;

/**
 * Error de impresión con mensaje apto para mostrar al usuario.
 *
 * - getMessage()        : mensaje claro en español (sin detalles técnicos).
 * - sugerencia()        : qué puede hacer la persona para resolverlo.
 * - detalleTecnico()    : información para el log (no se muestra en pantalla).
 * - tipo()              : código estable para que el frontend decida qué hacer.
 */
class ImpresoraException extends RuntimeException
{
    public function __construct(
        private string $tipo,
        string $mensaje,
        private string $sugerencia = '',
        private string $detalle = ''
    ) {
        parent::__construct($mensaje);
    }

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function sugerencia(): string
    {
        return $this->sugerencia;
    }

    public function detalleTecnico(): string
    {
        return $this->detalle;
    }

    // ── Fábricas de errores comunes ─────────────────────────────────

    public static function noConfigurada(string $detalle = ''): self
    {
        return new self(
            'no_configurada',
            'La impresora térmica no está configurada.',
            'Revisa los parámetros "impresora.*" del archivo .env o usa la impresión por navegador.',
            $detalle
        );
    }

    public static function configInvalida(string $mensaje, string $sugerencia = ''): self
    {
        return new self('config_invalida', $mensaje, $sugerencia, $mensaje);
    }

    public static function sinConexion(string $destino, string $detalle = ''): self
    {
        return new self(
            'sin_conexion',
            "No se pudo conectar con la impresora ($destino).",
            'Verifica que esté encendida, conectada a la red/USB y que la dirección configurada sea correcta.',
            $detalle
        );
    }

    public static function tiempoAgotado(string $destino, string $detalle = ''): self
    {
        return new self(
            'timeout',
            "La impresora ($destino) tardó demasiado en responder.",
            'Revisa que esté encendida y en la misma red. Si el problema persiste, reinicia la impresora.',
            $detalle
        );
    }

    public static function conexionPerdida(string $detalle = ''): self
    {
        return new self(
            'conexion_perdida',
            'Se perdió la conexión con la impresora mientras se enviaba el ticket.',
            'Revisa el cable o la red e inténtalo de nuevo. Puede que el ticket haya salido incompleto.',
            $detalle
        );
    }

    public static function sinPapel(): self
    {
        return new self(
            'sin_papel',
            'La impresora se quedó sin papel.',
            'Coloca un rollo nuevo e inténtalo de nuevo.'
        );
    }

    public static function tapaAbierta(): self
    {
        return new self(
            'tapa_abierta',
            'La tapa de la impresora está abierta.',
            'Cierra la tapa e inténtalo de nuevo.'
        );
    }

    public static function errorImpresora(): self
    {
        return new self(
            'error_impresora',
            'La impresora reporta un error (atasco, cabezal sobrecalentado u otro).',
            'Apágala y enciéndela, revisa que el papel esté bien colocado e inténtalo de nuevo.'
        );
    }

    public static function permiso(string $destino, string $sugerencia, string $detalle = ''): self
    {
        return new self(
            'permiso',
            "No hay permiso para usar la impresora ($destino).",
            $sugerencia,
            $detalle
        );
    }

    public static function sinEjecucion(string $detalle = ''): self
    {
        return new self(
            'sin_ejecucion',
            'El servidor no permite ejecutar el comando necesario para imprimir.',
            'Habilita proc_open en php.ini (disable_functions) o usa la conexión "red"/"puerto".',
            $detalle
        );
    }

    public static function fallo(string $mensaje, string $sugerencia = '', string $detalle = ''): self
    {
        return new self('fallo', $mensaje, $sugerencia, $detalle);
    }
}
