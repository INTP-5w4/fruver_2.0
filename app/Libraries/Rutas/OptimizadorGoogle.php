<?php

namespace App\Libraries\Rutas;

use DateTimeZone;
use InvalidArgumentException;
use JsonException;

/**
 * Optimiza el orden de paradas con Google Routes API (computeRoutes).
 *
 *  - optimizeWaypointOrder = true reordena las paradas intermedias (se cobra en el SKU
 *    "Compute Routes Pro"). Para eso ninguna parada puede ser "via" y no se puede usar
 *    TRAFFIC_AWARE_OPTIMAL, así que se usa TRAFFIC_AWARE.
 *  - Se piden SOLO los campos necesarios (field mask): menos datos y menor costo.
 *  - Hasta 25 paradas intermedias por solicitud.
 *
 * Documentación: https://developers.google.com/maps/documentation/routes/opt-way
 */
final class OptimizadorGoogle implements OptimizadorInterface
{
    public const URL = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public const MAX_INTERMEDIAS = 25;

    private const CAMPOS = [
        'routes.optimizedIntermediateWaypointIndex',
        'routes.distanceMeters',
        'routes.duration',
        'routes.polyline.encodedPolyline',
        'routes.legs.distanceMeters',
        'routes.legs.duration',
    ];

    public function __construct(
        private readonly string $llave,
        private readonly TransporteHttp $http,
        private readonly string $idioma = 'es-MX',
        private readonly string $region = 'MX',
        private readonly int $tiempoEsperaSeg = 15,
    ) {
        if (trim($llave) === '') {
            throw new InvalidArgumentException('Falta la llave de Google (rutas.llaveServidor).');
        }
    }

    public function calcular(Punto $origen, array $paradas, OpcionesRuta $op): ResultadoRuta
    {
        $paradas = array_values($paradas);

        if ($paradas === []) {
            return new ResultadoRuta($op->estrategia, 'google', [], [], 0, 0, null, $op->regresar);
        }

        [$intermedias, $final] = $this->separarFinal($origen, $paradas, $op);

        if (count($intermedias) > self::MAX_INTERMEDIAS) {
            throw new InvalidArgumentException('Google acepta máximo ' . self::MAX_INTERMEDIAS . ' paradas intermedias por ruta.');
        }

        $cuerpo   = $this->cuerpo($origen, $intermedias, $final, $op);
        $respuesta = $this->http->postJson(self::URL, [
            'X-Goog-Api-Key'   => $this->llave,
            'X-Goog-FieldMask' => implode(',', self::CAMPOS),
        ], $cuerpo, $this->tiempoEsperaSeg);

        return $this->interpretar($respuesta, $intermedias, $final, $op);
    }

    /**
     * Arma el cuerpo de la solicitud. Público para poder revisarlo en pruebas.
     *
     * @param list<Parada> $intermedias
     */
    public function cuerpo(Punto $origen, array $intermedias, ?Parada $final, OpcionesRuta $op): array
    {
        $destino = $final !== null ? $final->punto : ($op->regresoA ?? $origen);

        $cuerpo = [
            'origin'            => $origen->aWaypoint(),
            'destination'       => $destino->aWaypoint(),
            'intermediates'     => array_map(static fn (Parada $p) => $p->punto->aWaypoint(), $intermedias),
            'travelMode'        => $op->modoViaje(),
            'languageCode'      => $this->idioma,
            'regionCode'        => $this->region,
            'units'             => 'METRIC',
            'polylineEncoding'  => 'ENCODED_POLYLINE',
            'optimizeWaypointOrder' => $op->optimizaOrden() && count($intermedias) > 1,
        ];

        // Tráfico solo aplica a auto/moto, y departureTime debe estar en el futuro.
        if (in_array($cuerpo['travelMode'], ['DRIVE', 'TWO_WHEELER'], true)) {
            $cuerpo['routingPreference'] = 'TRAFFIC_AWARE';
            $salida = $op->salida->getTimestamp() > time() + 60 ? $op->salida : null;
            if ($salida !== null) {
                $cuerpo['departureTime'] = $salida->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            }
        }

        if ($op->evitaCuotas()) {
            $cuerpo['routeModifiers'] = ['avoidTolls' => true, 'avoidHighways' => true];
        }

        if ($cuerpo['intermediates'] === []) {
            unset($cuerpo['intermediates']);
            $cuerpo['optimizeWaypointOrder'] = false;
        }

        return $cuerpo;
    }

    /**
     * Si la ruta regresa al almacén, todas las paradas son intermedias.
     * Si no regresa, Google necesita un destino: se toma como final la parada más
     * lejana al origen (si se optimiza) o la última del orden recibido.
     *
     * @param list<Parada> $paradas
     * @return array{0:list<Parada>,1:?Parada}
     */
    private function separarFinal(Punto $origen, array $paradas, OpcionesRuta $op): array
    {
        if ($op->regresar) {
            return [$paradas, null];
        }

        $indiceFinal = count($paradas) - 1;

        if ($op->optimizaOrden()) {
            $max = -1.0;
            foreach ($paradas as $i => $p) {
                $d = $origen->distanciaA($p->punto);
                if ($d > $max) {
                    $max         = $d;
                    $indiceFinal = $i;
                }
            }
        }

        $final = $paradas[$indiceFinal];
        unset($paradas[$indiceFinal]);

        return [array_values($paradas), $final];
    }

    /**
     * @param array{status:int,cuerpo:string} $respuesta
     * @param list<Parada>                    $intermedias
     */
    private function interpretar(array $respuesta, array $intermedias, ?Parada $final, OpcionesRuta $op): ResultadoRuta
    {
        try {
            $json = json_decode($respuesta['cuerpo'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ErrorProveedorRutas('Google respondió algo que no es JSON (HTTP ' . $respuesta['status'] . ').');
        }

        if ($respuesta['status'] !== 200) {
            $mensaje = $json['error']['message'] ?? 'error desconocido';
            throw new ErrorProveedorRutas('Google Routes API respondió HTTP ' . $respuesta['status'] . ': ' . $mensaje);
        }

        $ruta = $json['routes'][0] ?? null;
        if (! is_array($ruta)) {
            throw new ErrorProveedorRutas('Google no encontró una ruta entre esos puntos.');
        }

        // Orden: Google devuelve índices de las intermedias en el nuevo orden.
        $indices = $ruta['optimizedIntermediateWaypointIndex'] ?? null;
        $n       = count($intermedias);
        if (! is_array($indices) || count($indices) !== $n || in_array(-1, $indices, true)) {
            $indices = range(0, max(0, $n - 1));
            if ($n === 0) {
                $indices = [];
            }
        }

        $orden = [];
        foreach ($indices as $i) {
            if (! isset($intermedias[$i])) {
                throw new ErrorProveedorRutas('Google devolvió un orden de paradas inválido.');
            }
            $orden[] = $intermedias[$i]->clave;
        }
        if ($final !== null) {
            $orden[] = $final->clave;
        }

        $tramos = [];
        foreach ($ruta['legs'] ?? [] as $leg) {
            $tramos[] = [
                'distancia_m' => (int) ($leg['distanceMeters'] ?? 0),
                'duracion_s'  => self::segundos($leg['duration'] ?? '0s'),
            ];
        }

        $esperados = count($orden) + ($op->regresar ? 1 : 0);
        if (count($tramos) !== $esperados) {
            throw new ErrorProveedorRutas('Google devolvió ' . count($tramos) . ' tramos y se esperaban ' . $esperados . '.');
        }

        return new ResultadoRuta(
            $op->estrategia,
            'google',
            $orden,
            $tramos,
            (int) ($ruta['distanceMeters'] ?? array_sum(array_column($tramos, 'distancia_m'))),
            self::segundos($ruta['duration'] ?? (array_sum(array_column($tramos, 'duracion_s')) . 's')),
            $ruta['polyline']['encodedPolyline'] ?? null,
            $op->regresar,
        );
    }

    /** "1234s" / "12.5s" → 1234 / 13 */
    public static function segundos(string|int|float $duracion): int
    {
        if (is_int($duracion) || is_float($duracion)) {
            return (int) round($duracion);
        }

        return (int) round((float) rtrim(trim($duracion), 's'));
    }
}
