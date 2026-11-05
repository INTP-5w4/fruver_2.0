<?php

namespace App\Libraries\Rutas;

use InvalidArgumentException;
use JsonException;

/**
 * Texto de una dirección → coordenadas, con Google Geocoding API.
 *
 * Se usa como respaldo cuando la dirección no se capturó con el autocompletado
 * (direcciones viejas, pedidos de la tienda) y desde el comando
 * `php spark direcciones:geocodificar`.
 *
 * Términos de Google: las lat/lng obtenidas aquí solo pueden guardarse 30 días;
 * el place_id sí puede guardarse sin límite.
 */
final class Geocodificador
{
    public const URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function __construct(
        private readonly string $llave,
        private readonly TransporteHttp $http,
        private readonly string $idioma = 'es',
        private readonly string $region = 'mx',
        private readonly int $tiempoEsperaSeg = 10,
    ) {
        if (trim($llave) === '') {
            throw new InvalidArgumentException('Falta la llave de Google (rutas.llaveServidor).');
        }
    }

    public static function crear(): ?self
    {
        $cfg = config('Rutas');

        return $cfg->conGoogle()
            ? new self($cfg->llaveServidor, new TransporteCurl(), 'es', strtolower($cfg->region))
            : null;
    }

    /** Arma el texto que se manda a Google desde una fila de `direccion`. */
    public static function textoDe(array $d): string
    {
        $calle = trim(($d['calle'] ?? '') . ' ' . ($d['numero'] ?? ''));
        $partes = array_filter([
            $calle,
            $d['colonia'] ?? '',
            trim(($d['codigo_postal'] ?? '') . ' ' . ($d['municipio'] ?? '')),
            $d['estado'] ?? '',
            'México',
        ], static fn ($p) => trim((string) $p) !== '');

        return implode(', ', $partes);
    }

    /**
     * @return null|array{lat:float,lng:float,place_id:?string,direccion_formateada:?string,precision_geo:?string,codigo_postal:?string}
     *         null si Google no encontró la dirección.
     *
     * @throws ErrorProveedorRutas si la llave/cuota/red fallan
     */
    public function geocodificar(string $direccion): ?array
    {
        $direccion = trim($direccion);
        if ($direccion === '') {
            return null;
        }

        $r = $this->http->get(self::URL, [
            'address'    => mb_substr($direccion, 0, 500),
            'key'        => $this->llave,
            'language'   => $this->idioma,
            'region'     => $this->region,
            'components' => 'country:' . strtoupper($this->region),
        ], $this->tiempoEsperaSeg);

        try {
            $json = json_decode($r['cuerpo'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ErrorProveedorRutas('Geocoding API respondió algo que no es JSON (HTTP ' . $r['status'] . ').');
        }

        $estado = (string) ($json['status'] ?? '');

        if ($estado === 'ZERO_RESULTS') {
            return null;
        }
        if ($estado !== 'OK') {
            throw new ErrorProveedorRutas('Geocoding API: ' . $estado . ' ' . ($json['error_message'] ?? ''));
        }

        $res = $json['results'][0] ?? null;
        $loc = $res['geometry']['location'] ?? null;
        $p   = is_array($loc) ? Punto::desde($loc['lat'] ?? null, $loc['lng'] ?? null) : null;

        if ($p === null) {
            return null;
        }

        $cp = null;
        foreach ($res['address_components'] ?? [] as $c) {
            if (in_array('postal_code', $c['types'] ?? [], true)) {
                $cp = mb_substr((string) $c['long_name'], 0, 10);
            }
        }

        return [
            'lat'                  => $p->lat,
            'lng'                  => $p->lng,
            'place_id'             => isset($res['place_id']) ? mb_substr((string) $res['place_id'], 0, 255) : null,
            'direccion_formateada' => isset($res['formatted_address']) ? mb_substr((string) $res['formatted_address'], 0, 400) : null,
            'precision_geo'        => isset($res['geometry']['location_type']) ? mb_substr((string) $res['geometry']['location_type'], 0, 30) : null,
            'codigo_postal'        => $cp,
        ];
    }
}
