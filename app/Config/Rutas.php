<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Rutas de reparto: proveedor de mapas, rutas y direcciones.
 *
 * Tres proveedores posibles (rutas.proveedor en .env):
 *
 *  - ors    : openrouteservice (HeiGIT) + mapas de OpenStreetMap con Leaflet.
 *             Gratuito, sin tarjeta ni RFC: solo una cuenta en https://account.heigit.org
 *               rutas.llaveOrs = "..."
 *  - google : Google Maps Platform (requiere cuenta de facturación).
 *               rutas.llaveServidor  = "AIza..."   # Routes API + Geocoding API (solo PHP)
 *               rutas.llaveNavegador = "AIza..."   # Maps JavaScript API + Places API (HTML)
 *               rutas.mapId          = "..."       # opcional
 *  - local  : sin servicio externo. Mapa de OpenStreetMap para ubicar con pin y rutas
 *             aproximadas (línea recta, sin tráfico).
 *
 * Si rutas.proveedor se deja vacío se elige solo: ors si hay rutas.llaveOrs, google si
 * hay rutas.llaveServidor, local en otro caso.
 *
 * Los botones "Navegar" abren Google Maps con enlaces públicos (Maps URLs): no usan llave
 * ni facturación, funcionan con cualquier proveedor.
 */
class Rutas extends BaseConfig
{
    /** ors | google | local | '' (automático). */
    public string $proveedor = '';

    // ── openrouteservice ──────────────────────────────────────────

    /** Llave (token) de https://account.heigit.org. Se usa SOLO en PHP; nunca llega al navegador. */
    public string $llaveOrs = '';

    /** Dirección del servicio (la antigua api.openrouteservice.org se retira en noviembre de 2026). */
    public string $urlOrs = 'https://api.heigit.org/openrouteservice';

    // ── Mapa en el navegador (Leaflet) para ors y local ──────────

    /** Capas del mapa. Los servidores de OpenStreetMap piden uso moderado y mostrar el crédito. */
    public string $mapaTiles = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

    public string $mapaAtribucion = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

    // ── Google Maps Platform (opcional) ──────────────────────────

    /** Llave para llamadas desde PHP (Routes API, Geocoding API). */
    public string $llaveServidor = '';

    /** Llave para el navegador (Maps JavaScript API, Places API). */
    public string $llaveNavegador = '';

    /** Map ID de Google Cloud (habilita pines con número). Vacío = pines clásicos. */
    public string $mapId = '';

    // ── Comunes ──────────────────────────────────────────────────

    /** Región e idioma de los resultados. */
    public string $region = 'MX';
    public string $idioma = 'es-MX';

    /** Centro del mapa cuando aún no hay almacén (Zócalo de Puebla). */
    public float $latCentro = 19.0433;
    public float $lngCentro = -98.1981;

    /** Máximo de paradas por ruta (Google acepta 25 intermedias; openrouteservice, 48). */
    public int $maxParadas = 25;

    /** Hora de salida por omisión (HH:MM) para calcular las horas estimadas. */
    public string $horaSalida = '09:00';

    /** Minutos que se estiman por parada (bajar, cobrar, entregar). */
    public int $minutosPorParada = 5;

    /** Segundos máximos de espera al servicio externo. */
    public int $tiempoEsperaSeg = 15;

    /**
     * Días que se conservan coordenadas obtenidas de Google (sus Términos permiten
     * guardarlas máximo 30 días). Las de OpenStreetMap, del pin manual o del GPS del
     * repartidor no vencen.
     */
    public int $diasVigenciaGoogle = 30;

    // ── Combustible (costo de traslado) ──────────────────────────

    /**
     * Precio por litro (MXN) de fábrica: verde = Magna (regular), roja = Premium y diésel.
     * Son aproximados (promedio nacional, noviembre de 2026). El personal los actualiza en
     * Rutas → Combustible y esos tienen prioridad (se guardan en la tabla `settings`).
     */
    public array $preciosCombustible = [
        'magna'   => 23.90,
        'premium' => 25.90,
        'diesel'  => 26.50,
    ];

    // ── Paradas "de paso" (estrategia por prioridad) ─────────────

    /**
     * Al ordenar por prioridad, un pedido normal que queda de camino a un preferencial se
     * entrega antes si desviarse para dejarlo cuesta poco. Ejemplo: de Grajales a Puebla
     * se pasa por Acajete; si hay pedidos en Acajete, se dejan al pasar.
     *
     * Se considera "de paso" si el kilometraje extra es como máximo el MENOR de:
     *  - desvioMaxKm kilómetros, y
     *  - desvioMaxPorcentaje % del tramo hacia el preferencial.
     * Así, en un tramo corto de ciudad solo entra lo que de verdad está en el camino.
     */
    public float $desvioMaxKm = 5.0;

    public float $desvioMaxPorcentaje = 15.0;

    /** Velocidad media (km/h) para el optimizador local, por vehículo. */
    public array $velocidadLocal = [
        'moto'      => 25,
        'auto'      => 22,
        'camioneta' => 20,
        'bicicleta' => 13,
    ];

    /** Proveedor efectivo: ors | google | local. */
    public function proveedorRutas(): string
    {
        $elegido = strtolower(trim($this->proveedor));

        if ($elegido === 'ors') {
            return $this->conOrs() ? 'ors' : 'local';
        }
        if ($elegido === 'google') {
            return trim($this->llaveServidor) !== '' ? 'google' : 'local';
        }
        if ($elegido === 'local') {
            return 'local';
        }

        if ($this->conOrs()) {
            return 'ors';
        }

        return trim($this->llaveServidor) !== '' ? 'google' : 'local';
    }

    /**
     * Mapa del navegador: leaflet | google | '' (sin mapa).
     * Con Google sus Términos exigen mostrar sus datos en un mapa de Google; sin llave
     * de navegador no hay mapa. Con ors o local se usa Leaflet + OpenStreetMap (sin llave).
     */
    public function proveedorMapa(): string
    {
        if ($this->proveedorRutas() === 'google') {
            return trim($this->llaveNavegador) !== '' ? 'google' : '';
        }

        return 'leaflet';
    }

    public function conOrs(): bool
    {
        return trim($this->llaveOrs) !== '';
    }

    /** ¿Hay un servicio externo de rutas (no solo el cálculo aproximado)? */
    public function conServicio(): bool
    {
        return $this->proveedorRutas() !== 'local';
    }

    /** ¿Se usan las APIs de Google en el servidor? */
    public function conGoogle(): bool
    {
        return $this->proveedorRutas() === 'google';
    }

    /** ¿Hay mapa en el navegador? */
    public function conMapa(): bool
    {
        return $this->proveedorMapa() !== '';
    }

    /** Nombre para mostrar al usuario. */
    public function nombreProveedor(): string
    {
        return match ($this->proveedorRutas()) {
            'ors'    => 'openrouteservice + OpenStreetMap',
            'google' => 'Google Maps',
            default  => 'cálculo aproximado (sin servicio de mapas)',
        };
    }
}
