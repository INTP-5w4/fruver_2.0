<?php

namespace App\Libraries\Rutas;

use InvalidArgumentException;

/** Coordenada geográfica validada (grados decimales). */
final class Punto
{
    public function __construct(public readonly float $lat, public readonly float $lng)
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || is_nan($lat) || is_nan($lng)) {
            throw new InvalidArgumentException('Coordenadas fuera de rango.');
        }
    }

    /** Crea un punto desde valores que pueden venir como texto (BD, POST); null si no son válidos. */
    public static function desde(mixed $lat, mixed $lng): ?self
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        try {
            return new self((float) $lat, (float) $lng);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** Distancia en línea recta en metros (fórmula de Haversine). */
    public function distanciaA(self $otro): float
    {
        $r    = 6371000.0;
        $dLat = deg2rad($otro->lat - $this->lat);
        $dLng = deg2rad($otro->lng - $this->lng);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($this->lat)) * cos(deg2rad($otro->lat)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    public function texto(): string
    {
        return sprintf('%.7F,%.7F', $this->lat, $this->lng);
    }

    /** Formato de waypoint de Routes API. */
    public function aWaypoint(): array
    {
        return ['location' => ['latLng' => ['latitude' => $this->lat, 'longitude' => $this->lng]]];
    }
}
