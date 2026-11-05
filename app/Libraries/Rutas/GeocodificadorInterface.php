<?php

namespace App\Libraries\Rutas;

/**
 * Texto de una dirección → coordenadas (Google, openrouteservice…).
 */
interface GeocodificadorInterface
{
    /**
     * @return null|array{lat:float,lng:float,place_id:?string,direccion_formateada:?string,precision_geo:?string,codigo_postal:?string}
     *         null si no se encontró la dirección
     *
     * @throws ErrorProveedorRutas si la llave/cuota/red fallan
     */
    public function geocodificar(string $direccion): ?array;

    /** Valor de direccion.origen_coord para lo que devuelve: google | osm. */
    public function origen(): string;
}
