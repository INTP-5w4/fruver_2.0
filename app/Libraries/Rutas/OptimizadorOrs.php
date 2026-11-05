<?php

namespace App\Libraries\Rutas;

use InvalidArgumentException;
use JsonException;

/**
 * Rutas con openrouteservice (HeiGIT), sobre datos de OpenStreetMap.
 *
 *  1. Orden óptimo: endpoint /optimization (motor VROOM). Solo cuando la estrategia
 *     reordena (rápida, sin casetas). Si la ruta no regresa al almacén, VROOM elige
 *     también cuál parada conviene dejar al final.
 *  2. Medición: /v2/directions/{perfil}/json con las paradas en ese orden → distancia
 *     y tiempo por tramo y el trazo (polyline codificada, precisión 5).
 *
 * Coordenadas de openrouteservice: SIEMPRE [longitud, latitud].
 * No considera tráfico en tiempo real.
 * Documentación: https://openrouteservice.org/dev/#/api-docs
 */
final class OptimizadorOrs implements OptimizadorInterface
{
    /** directions acepta 50 puntos: origen + 48 paradas + regreso. */
    public const MAX_PARADAS = 48;

    /** openrouteservice no tiene perfil de moto: se usa el de auto. */
    public const PERFILES = [
        'moto'      => 'driving-car',
        'auto'      => 'driving-car',
        'camioneta' => 'driving-car',
        'bicicleta' => 'cycling-regular',
    ];

    /** @var array<string,list<int>> órdenes ya calculados (evita repetir la llamada entre estrategias) */
    private array $memoria = [];

    public function __construct(
        private readonly string $llave,
        private readonly TransporteHttp $http,
        private readonly string $url = 'https://api.heigit.org/openrouteservice',
        private readonly string $idioma = 'es',
        private readonly int $tiempoEsperaSeg = 15,
    ) {
        if (trim($llave) === '') {
            throw new InvalidArgumentException('Falta la llave de openrouteservice (rutas.llaveOrs).');
        }
    }

    public function calcular(Punto $origen, array $paradas, OpcionesRuta $op): ResultadoRuta
    {
        $paradas = array_values($paradas);

        if ($paradas === []) {
            return new ResultadoRuta($op->estrategia, 'ors', [], [], 0, 0, null, $op->regresar);
        }
        if (count($paradas) > self::MAX_PARADAS) {
            throw new InvalidArgumentException('openrouteservice acepta máximo ' . self::MAX_PARADAS . ' paradas por ruta.');
        }

        if ($op->optimizaOrden() && count($paradas) > 1) {
            $paradas = array_map(static fn (int $i) => $paradas[$i], $this->ordenOptimo($origen, $paradas, $op));
        }

        return $this->medir($origen, $paradas, $op);
    }

    /**
     * Índices de $paradas en el orden que propone VROOM.
     *
     * @param list<Parada> $paradas
     * @return list<int>
     */
    private function ordenOptimo(Punto $origen, array $paradas, OpcionesRuta $op): array
    {
        $vehiculo = [
            'id'      => 1,
            'profile' => self::perfil($op),
            'start'   => self::lngLat($origen),
        ];
        if ($op->regresar) {
            $vehiculo['end'] = self::lngLat($op->regresoA ?? $origen);
        }

        $cuerpo = [
            'jobs'     => array_map(static fn (Parada $p, int $i) => ['id' => $i + 1, 'location' => self::lngLat($p->punto)], $paradas, array_keys($paradas)),
            'vehicles' => [$vehiculo],
        ];

        $clave = md5(json_encode($cuerpo));
        if (isset($this->memoria[$clave])) {
            return $this->memoria[$clave];
        }

        $json = $this->pedir($this->url . '/optimization', $cuerpo);

        if ((int) ($json['code'] ?? -1) !== 0) {
            throw new ErrorProveedorRutas('openrouteservice no pudo optimizar la ruta (código ' . ($json['code'] ?? '?') . ').');
        }
        if (! empty($json['unassigned'])) {
            throw new ErrorProveedorRutas('openrouteservice no pudo incluir ' . count($json['unassigned']) . ' parada(s); revisa que estén cerca de una calle.');
        }

        $orden = [];
        foreach ($json['routes'][0]['steps'] ?? [] as $paso) {
            if (($paso['type'] ?? '') === 'job') {
                $i = (int) ($paso['id'] ?? $paso['job'] ?? 0) - 1;
                if (! isset($paradas[$i]) || in_array($i, $orden, true)) {
                    throw new ErrorProveedorRutas('openrouteservice devolvió un orden de paradas inválido.');
                }
                $orden[] = $i;
            }
        }

        if (count($orden) !== count($paradas)) {
            throw new ErrorProveedorRutas('openrouteservice devolvió ' . count($orden) . ' paradas y se esperaban ' . count($paradas) . '.');
        }

        return $this->memoria[$clave] = $orden;
    }

    /**
     * Distancia, tiempo y trazo recorriendo las paradas en el orden dado.
     *
     * @param list<Parada> $paradas
     */
    private function medir(Punto $origen, array $paradas, OpcionesRuta $op): ResultadoRuta
    {
        $puntos = [$origen, ...array_map(static fn (Parada $p) => $p->punto, $paradas)];
        if ($op->regresar) {
            $puntos[] = $op->regresoA ?? $origen;
        }

        $cuerpo = [
            'coordinates' => array_map([self::class, 'lngLat'], $puntos),
            // -1 = sin límite para "pegar" el punto a la calle (domicilios al fondo de un terreno).
            'radiuses'    => array_fill(0, count($puntos), -1),
            'language'    => $this->idioma,
            'units'       => 'm',
            'geometry'    => true,
        ];
        if ($op->evitaCuotas() && self::perfil($op) === 'driving-car') {
            $cuerpo['options'] = ['avoid_features' => ['tollways', 'highways']];
        }

        $json = $this->pedir($this->url . '/v2/directions/' . self::perfil($op) . '/json', $cuerpo);
        $ruta = $json['routes'][0] ?? null;

        if (! is_array($ruta)) {
            throw new ErrorProveedorRutas('openrouteservice no encontró una ruta entre esos puntos.');
        }

        $tramos = [];
        foreach ($ruta['segments'] ?? [] as $s) {
            $tramos[] = [
                'distancia_m' => (int) round((float) ($s['distance'] ?? 0)),
                'duracion_s'  => (int) round((float) ($s['duration'] ?? 0)),
            ];
        }

        if (count($tramos) !== count($puntos) - 1) {
            throw new ErrorProveedorRutas('openrouteservice devolvió ' . count($tramos) . ' tramos y se esperaban ' . (count($puntos) - 1) . '.');
        }

        return new ResultadoRuta(
            $op->estrategia,
            'ors',
            array_map(static fn (Parada $p) => $p->clave, $paradas),
            $tramos,
            (int) round((float) ($ruta['summary']['distance'] ?? array_sum(array_column($tramos, 'distancia_m')))),
            (int) round((float) ($ruta['summary']['duration'] ?? array_sum(array_column($tramos, 'duracion_s')))),
            is_string($ruta['geometry'] ?? null) ? $ruta['geometry'] : null,
            $op->regresar,
        );
    }

    /** POST JSON con la llave en el encabezado Authorization; traduce los errores. */
    private function pedir(string $url, array $cuerpo): array
    {
        $r = $this->http->postJson($url, [
            'Authorization' => $this->llave,
            'Accept'        => 'application/json',
        ], $cuerpo, $this->tiempoEsperaSeg);

        try {
            $json = json_decode($r['cuerpo'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ErrorProveedorRutas('openrouteservice respondió algo que no es JSON (HTTP ' . $r['status'] . ').');
        }

        if ($r['status'] !== 200 || ! is_array($json)) {
            throw new ErrorProveedorRutas('openrouteservice respondió HTTP ' . $r['status'] . ': ' . self::mensajeError($json, $r['status']));
        }

        return $json;
    }

    public static function mensajeError(mixed $json, int $status): string
    {
        $m = null;
        if (is_array($json)) {
            $e = $json['error'] ?? null;
            $m = is_array($e) ? ($e['message'] ?? null) : (is_string($e) ? $e : null);
        }

        return $m ?? match ($status) {
            401     => 'falta la llave',
            403     => 'llave rechazada o cuota diaria agotada',
            429     => 'demasiadas solicitudes por minuto',
            default => 'error desconocido',
        };
    }

    /** @return array{0:float,1:float} [lng, lat] */
    public static function lngLat(Punto $p): array
    {
        return [$p->lng, $p->lat];
    }

    public static function perfil(OpcionesRuta $op): string
    {
        return self::PERFILES[$op->vehiculo] ?? 'driving-car';
    }
}
