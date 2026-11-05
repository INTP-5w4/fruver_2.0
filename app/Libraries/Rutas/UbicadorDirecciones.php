<?php

namespace App\Libraries\Rutas;

use App\Models\Modelo_direccion;

/**
 * Pone coordenadas a una dirección después de guardarla.
 *
 * Prioridad:
 *  1. El pin del formulario (autocompletado de Google o pin movido a mano).
 *  2. Geocoding API en el servidor (si hay llave), con el texto de la dirección.
 *  3. Nada: queda "sin ubicar" y se puede reintentar con `php spark direcciones:geocodificar`.
 */
final class UbicadorDirecciones
{
    public function __construct(
        private readonly Modelo_direccion $direcciones,
        private readonly ?Geocodificador $geocodificador,
    ) {
    }

    public static function crear(?Modelo_direccion $m = null): self
    {
        return new self($m ?? new Modelo_direccion(), Geocodificador::crear());
    }

    /**
     * @param array<string,mixed>      $direccion fila con calle, numero, colonia, municipio, estado, codigo_postal
     * @param array<string,mixed>|null $pin       lat, lng, origen (google|manual), place_id, direccion_formateada
     * @param bool                     $cambioTexto si cambió la dirección escrita (las coordenadas viejas ya no sirven)
     *
     * @return string|null aviso para el usuario (o null si quedó ubicada)
     */
    public function ubicar(int $idDireccion, array $direccion, ?array $pin, bool $cambioTexto = true): ?string
    {
        if (! $this->direcciones->tieneColumnasGeo()) {
            return null;
        }

        $punto = $pin !== null ? Punto::desde($pin['lat'] ?? null, $pin['lng'] ?? null) : null;

        if ($punto !== null) {
            $origen = ($pin['origen'] ?? '') === 'manual' ? 'manual' : 'google';
            // El usuario eligió un lugar o movió el pin a propósito: manda sobre lo anterior.
            $this->direcciones->limpiarCoordenadas($idDireccion);
            $this->direcciones->guardarCoordenadas($idDireccion, $punto, $origen, [
                'place_id'             => self::texto($pin['place_id'] ?? null, 255),
                'direccion_formateada' => self::texto($pin['direccion_formateada'] ?? null, 400),
                'precision_geo'        => $origen === 'google' ? 'PLACES' : null,
            ]);

            return null;
        }

        if (! $cambioTexto) {
            return null;
        }

        $this->direcciones->limpiarCoordenadas($idDireccion);

        if ($this->geocodificador === null) {
            return 'La dirección se guardó sin ubicación en el mapa (falta configurar Google Maps).';
        }

        try {
            $geo = $this->geocodificador->geocodificar(Geocodificador::textoDe($direccion));
        } catch (ErrorProveedorRutas $e) {
            log_message('error', 'Geocodificación falló: ' . $e->getMessage());

            return 'La dirección se guardó, pero Google Maps no respondió; se ubicará después.';
        }

        if ($geo === null) {
            return 'Google Maps no encontró la dirección: revisa calle, número y colonia, o acomoda el pin en el mapa.';
        }

        $this->direcciones->guardarCoordenadas($idDireccion, new Punto($geo['lat'], $geo['lng']), 'google', $geo);

        return $geo['precision_geo'] === 'APPROXIMATE'
            ? 'Google ubicó la dirección solo de forma aproximada: conviene acomodar el pin en el mapa.'
            : null;
    }

    private static function texto(mixed $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
