<?php

namespace App\Libraries\Rutas;

use App\Models\Modelo_almacen;
use Config\Rutas as ConfigRutas;
use Throwable;

/**
 * Fábrica de los servicios según rutas.proveedor (ver Config\Rutas):
 *  - ors    → OptimizadorOrs + GeocodificadorOrs
 *  - google → OptimizadorGoogle + Geocodificador
 *  - local  → sin optimizador externo ni geocodificador (solo pin y cálculo aproximado)
 */
final class ServiciosMapa
{
    public static function optimizador(?ConfigRutas $cfg = null, ?TransporteHttp $http = null): ?OptimizadorInterface
    {
        $cfg ??= config('Rutas');

        return match ($cfg->proveedorRutas()) {
            'ors'    => new OptimizadorOrs($cfg->llaveOrs, $http ?? new TransporteCurl(), rtrim($cfg->urlOrs, '/'), substr($cfg->idioma, 0, 2), $cfg->tiempoEsperaSeg),
            'google' => new OptimizadorGoogle($cfg->llaveServidor, $http ?? new TransporteCurl(), $cfg->idioma, $cfg->region, $cfg->tiempoEsperaSeg),
            default  => null,
        };
    }

    public static function geocodificador(?ConfigRutas $cfg = null, ?TransporteHttp $http = null): ?GeocodificadorInterface
    {
        $cfg ??= config('Rutas');

        return match ($cfg->proveedorRutas()) {
            'ors'    => new GeocodificadorOrs($cfg->llaveOrs, $http ?? new TransporteCurl(), rtrim($cfg->urlOrs, '/'), $cfg->region, self::foco($cfg)),
            'google' => new Geocodificador($cfg->llaveServidor, $http ?? new TransporteCurl(), 'es', strtolower($cfg->region)),
            default  => null,
        };
    }

    /** Las búsquedas dan preferencia a lo cercano al almacén (o al centro configurado). */
    private static function foco(ConfigRutas $cfg): Punto
    {
        try {
            $a = (new Modelo_almacen())->principal();
            $p = $a !== null ? Modelo_almacen::punto($a) : null;
            if ($p !== null) {
                return $p;
            }
        } catch (Throwable) {
            // Sin base de datos o sin migración: se usa el centro configurado.
        }

        return new Punto($cfg->latCentro, $cfg->lngCentro);
    }
}
