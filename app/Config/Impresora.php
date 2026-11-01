<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración de la impresora térmica de tickets.
 *
 * Todos los valores pueden sobrescribirse desde el archivo .env usando el
 * prefijo "impresora." — por ejemplo:
 *
 *     impresora.conexion = red
 *     impresora.host     = 192.168.1.50
 *     impresora.papelMm  = 58
 *
 * Con la configuración por defecto ("navegador") no hace falta configurar nada:
 * el ticket se abre en una vista de 58/80 mm lista para imprimir con el
 * controlador (driver) normal de Windows/Linux/macOS de la impresora.
 */
class Impresora extends BaseConfig
{
    /**
     * Cómo se envía el ticket a la impresora:
     *
     *  - navegador : vista HTML imprimible (funciona con cualquier impresora con driver).
     *  - red       : socket TCP directo (impresoras Ethernet/WiFi, puerto 9100).
     *  - windows   : impresora USB/local COMPARTIDA en Windows (\\equipo\nombre).
     *  - puerto    : puerto serie/paralelo o dispositivo (COM3, LPT1, /dev/usb/lp0, /dev/rfcomm0).
     *  - cups      : cola CUPS en Linux/macOS (lp -o raw).
     *  - archivo   : guarda los bytes en writable/tickets (solo para pruebas/depuración).
     */
    public string $conexion = 'navegador';

    // ── Parámetros según el tipo de conexión ────────────────────────

    /** red: IP o nombre de host. windows: nombre del equipo (vacío = localhost). */
    public string $host = '';

    /** red: puerto TCP (casi todas las térmicas de red usan 9100). */
    public int $puertoRed = 9100;

    /** windows: nombre con el que se compartió la impresora. cups: nombre de la cola. */
    public string $nombreCompartido = '';

    /** puerto: COM3, LPT1, /dev/usb/lp0, /dev/rfcomm0, etc. */
    public string $puerto = '';

    /** puerto (solo COM/tty reales): velocidad en baudios. */
    public int $baudios = 9600;

    /** Segundos máximos de espera al conectar/escribir. */
    public int $timeoutSegundos = 4;

    /**
     * red: antes de imprimir consulta el estado de la impresora (sin papel, tapa
     * abierta, error). Es opcional en el estándar: si la impresora no responde,
     * simplemente se imprime.
     */
    public bool $consultarEstado = true;

    // ── Formato del papel ───────────────────────────────────────────

    /** Ancho del papel en milímetros: 58 u 80. */
    public int $papelMm = 80;

    /** Caracteres por línea. 0 = automático (58 mm → 32, 80 mm → 48). Algunas de 80 mm usan 42. */
    public int $columnas = 0;

    /**
     * Tabla de caracteres para acentos y ñ:
     *  cp858 (recomendada), cp850, cp437, cp1252 o ascii (quita los acentos; funciona en cualquier impresora).
     */
    public string $codificacion = 'cp858';

    /**
     * Enviar FS . (cancelar modo Kanji/GBK). Actívalo si en tu impresora (muchas
     * marcas genéricas chinas) los acentos y la ñ salen como caracteres extraños.
     */
    public bool $cancelarKanji = false;

    /** Corte de papel: parcial, completo o ninguno (sin cortador). */
    public string $corte = 'parcial';

    /** Líneas en blanco antes de cortar/arrancar el ticket. */
    public int $lineasFinales = 3;

    /** Abrir el cajón de dinero conectado a la impresora al imprimir. */
    public bool $abrirCajon = false;

    /** Copias por impresión (1 a 5). */
    public int $copias = 1;

    // ── Datos del negocio en el ticket ──────────────────────────────

    public string $negocioNombre    = 'FRUVER';
    public string $negocioSlogan    = 'Frutas, verduras y abarrotes';
    public string $negocioDireccion = '';
    public string $negocioTelefono  = '';
    public string $mensajePie       = '¡Gracias por su compra!';
    public string $simboloMoneda    = '$';

    // ── Utilidades ──────────────────────────────────────────────────

    /** Caracteres por línea efectivos según papel o configuración manual. */
    public function columnasEfectivas(): int
    {
        if ($this->columnas >= 16 && $this->columnas <= 80) {
            return $this->columnas;
        }

        return $this->papelMm <= 58 ? 32 : 48;
    }

    /** Número de copias acotado a un rango seguro. */
    public function copiasEfectivas(): int
    {
        return max(1, min(5, $this->copias));
    }
}
