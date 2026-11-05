<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Rutas de reparto con Google Maps Platform.
 *
 * Todos los valores se sobrescriben desde .env con el prefijo "rutas.":
 *
 *     rutas.llaveServidor  = "AIza..."   # Routes API + Geocoding API (se usa SOLO en PHP)
 *     rutas.llaveNavegador = "AIza..."   # Maps JavaScript API + Places API (va en el HTML)
 *     rutas.mapId          = "abc123"    # Map ID para pines numerados (opcional)
 *
 * IMPORTANTE: son DOS llaves distintas.
 *  - La del servidor se restringe por IP del servidor y solo a Routes API y Geocoding API.
 *  - La del navegador es visible para cualquiera: restríngela por "HTTP referrer"
 *    (tu dominio) y solo a Maps JavaScript API y Places API (New).
 *
 * Sin llaves el módulo sigue funcionando: las rutas se calculan con el optimizador
 * local (aproximado, distancia en línea recta) y los formularios no muestran el mapa.
 */
class Rutas extends BaseConfig
{
    /** Llave para llamadas desde PHP (Routes API, Geocoding API). */
    public string $llaveServidor = '';

    /** Llave para el navegador (Maps JavaScript API, Places API). */
    public string $llaveNavegador = '';

    /** Map ID de Google Cloud (habilita pines con número). Vacío = pines clásicos. */
    public string $mapId = '';

    /** Región e idioma para resultados de Google. */
    public string $region = 'MX';
    public string $idioma = 'es-MX';

    /** Centro del mapa cuando aún no hay almacén (Zócalo de Puebla). */
    public float $latCentro = 19.0433;
    public float $lngCentro = -98.1981;

    /**
     * Máximo de paradas por ruta. Routes API acepta hasta 25 paradas intermedias
     * por solicitud; con más hay que dividir la ruta.
     */
    public int $maxParadas = 25;

    /** Hora de salida por omisión (HH:MM) para calcular las horas estimadas. */
    public string $horaSalida = '09:00';

    /** Minutos que se estiman por parada (bajar, cobrar, entregar). */
    public int $minutosPorParada = 5;

    /** Segundos máximos de espera a Google (la optimización tarda más que una ruta simple). */
    public int $tiempoEsperaSeg = 15;

    /**
     * Días que se conservan coordenadas obtenidas de Google (Términos de servicio:
     * lat/lng de Geocoding/Places se pueden guardar máximo 30 días). Las coordenadas
     * del pin manual o del GPS del repartidor son datos propios y no vencen.
     */
    public int $diasVigenciaGoogle = 30;

    /** Velocidad media (km/h) para el optimizador local sin Google, por vehículo. */
    public array $velocidadLocal = [
        'moto'      => 25,
        'auto'      => 22,
        'camioneta' => 20,
        'bicicleta' => 13,
    ];

    /** ¿Hay llave de servidor para Routes/Geocoding? */
    public function conGoogle(): bool
    {
        return trim($this->llaveServidor) !== '';
    }

    /** ¿Hay llave para mostrar mapas en el navegador? */
    public function conMapa(): bool
    {
        return trim($this->llaveNavegador) !== '';
    }
}
