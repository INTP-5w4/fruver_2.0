<?php

namespace App\Libraries\Rutas;

use InvalidArgumentException;
use JsonException;

/**
 * Direcciones → coordenadas con openrouteservice (motor Pelias, datos de OpenStreetMap
 * y otras fuentes abiertas). Los datos son abiertos: no hay límite de tiempo para
 * guardarlos (origen_coord = 'osm').
 *
 * En México los números exteriores de OpenStreetMap están incompletos: muchos
 * resultados serán de la calle o la colonia (precisión "APPROXIMATE"); por eso el
 * sistema pide acomodar el pin y aprende del GPS de cada entrega.
 */
final class GeocodificadorOrs implements GeocodificadorInterface
{
    public function __construct(
        private readonly string $llave,
        private readonly TransporteHttp $http,
        private readonly string $url = 'https://api.heigit.org/openrouteservice',
        private readonly string $pais = 'MX',
        private readonly ?Punto $foco = null,
        private readonly int $tiempoEsperaSeg = 10,
    ) {
        if (trim($llave) === '') {
            throw new InvalidArgumentException('Falta la llave de openrouteservice (rutas.llaveOrs).');
        }
    }

    public function origen(): string
    {
        return 'osm';
    }

    public function geocodificar(string $direccion): ?array
    {
        $r = $this->buscarEn('/geocode/search', $direccion, 1);

        return $r[0] ?? null;
    }

    /**
     * Sugerencias para el buscador del formulario (máximo $limite).
     *
     * @return list<array<string,mixed>>
     */
    public function sugerencias(string $texto, int $limite = 5): array
    {
        return $this->buscarEn('/geocode/autocomplete', $texto, max(1, min(10, $limite)));
    }

    /** @return list<array<string,mixed>> */
    private function buscarEn(string $ruta, string $texto, int $tamano): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }

        $params = [
            'text'             => mb_substr($texto, 0, 300),
            'boundary.country' => strtoupper($this->pais),
            'size'             => (string) $tamano,
            'lang'             => 'es',
        ];
        if ($this->foco !== null) {
            $params['focus.point.lat'] = (string) $this->foco->lat;
            $params['focus.point.lon'] = (string) $this->foco->lng;
        }

        $r = $this->http->get($this->url . $ruta, $params, $this->tiempoEsperaSeg, [
            'Authorization' => $this->llave,
            'Accept'        => 'application/json',
        ]);

        try {
            $json = json_decode($r['cuerpo'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ErrorProveedorRutas('openrouteservice respondió algo que no es JSON (HTTP ' . $r['status'] . ').');
        }

        if ($r['status'] !== 200 || ! is_array($json)) {
            throw new ErrorProveedorRutas('Geocodificación de openrouteservice, HTTP ' . $r['status'] . ': ' . OptimizadorOrs::mensajeError($json, $r['status']));
        }

        $salida = [];
        foreach ($json['features'] ?? [] as $f) {
            $c = $f['geometry']['coordinates'] ?? null;
            $p = is_array($c) ? Punto::desde($c[1] ?? null, $c[0] ?? null) : null; // [lng, lat]
            if ($p === null) {
                continue;
            }
            $salida[] = self::convertir($p, (array) ($f['properties'] ?? []));
        }

        return $salida;
    }

    /** Propiedades de Pelias → campos de Fruver. */
    private static function convertir(Punto $p, array $pr): array
    {
        $corta = static fn ($v, int $max) => $v === null || $v === '' ? null : mb_substr((string) $v, 0, $max);

        return [
            'lat'                  => $p->lat,
            'lng'                  => $p->lng,
            'place_id'             => null, // no es un id de Google: no se usa para navegar
            'direccion_formateada' => $corta($pr['label'] ?? null, 400),
            'precision_geo'        => self::precision($pr),
            'codigo_postal'        => $corta($pr['postalcode'] ?? null, 10),
            // Para llenar el formulario al elegir una sugerencia:
            'calle'                => $corta($pr['street'] ?? (($pr['layer'] ?? '') === 'street' ? ($pr['name'] ?? null) : null), 200),
            'numero'               => $corta($pr['housenumber'] ?? null, 50),
            'colonia'              => $corta($pr['neighbourhood'] ?? $pr['borough'] ?? null, 200),
            'municipio'            => $corta($pr['locality'] ?? $pr['county'] ?? $pr['localadmin'] ?? null, 200),
            'estado'               => $corta($pr['region'] ?? null, 100),
        ];
    }

    /** EXACTA (número exterior), INTERPOLADA, o APPROXIMATE (calle, colonia, ciudad…). */
    public static function precision(array $pr): string
    {
        $capa = (string) ($pr['layer'] ?? '');
        $tipo = (string) ($pr['match_type'] ?? '');

        if (in_array($capa, ['address', 'venue'], true) && $tipo !== 'fallback') {
            return $tipo === 'interpolated' ? 'INTERPOLADA' : 'EXACTA';
        }

        return 'APPROXIMATE';
    }
}
