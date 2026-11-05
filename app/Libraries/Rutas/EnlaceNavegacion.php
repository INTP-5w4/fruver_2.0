<?php

namespace App\Libraries\Rutas;

/**
 * Enlaces "Maps URLs" para abrir la navegación en Google Maps (app o web).
 * No requieren llave ni tienen costo.
 *
 * Límites de Google: hasta 9 waypoints (solo 3 en navegadores móviles) y 2,048
 * caracteres por URL. Por eso se navega parada por parada.
 * https://developers.google.com/maps/documentation/urls/get-started
 */
final class EnlaceNavegacion
{
    private const MODOS = [
        'moto'      => 'two-wheeler',
        'auto'      => 'driving',
        'camioneta' => 'driving',
        'bicicleta' => 'bicycling',
    ];

    /** Navegación a un destino. Con place_id Google ubica mejor la entrada del lugar. */
    public static function hacia(?Punto $destino, ?string $placeId = null, string $vehiculo = 'moto', ?string $textoDireccion = null): ?string
    {
        if ($destino === null && trim((string) $textoDireccion) === '') {
            return null;
        }

        $params = [
            'api'         => '1',
            'destination' => $destino !== null ? $destino->texto() : trim((string) $textoDireccion),
            'travelmode'  => self::MODOS[$vehiculo] ?? 'driving',
            'dir_action'  => 'navigate',
        ];

        if ($placeId !== null && $placeId !== '') {
            $params['destination_place_id'] = $placeId;
        }

        return 'https://www.google.com/maps/dir/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
