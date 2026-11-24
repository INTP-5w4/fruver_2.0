<?php

namespace App\Libraries\Rutas;

use InvalidArgumentException;
use JsonException;

/**
 * Grafo dirigido de calles construido con datos de OpenStreetMap (servicio Overpass).
 *
 *  - Nodos  : puntos de OpenStreetMap que forman las calles (intersecciones y quiebres).
 *  - Aristas: cada tramo entre dos nodos consecutivos de una calle. Una calle de doble
 *             sentido aporta dos aristas (ida y vuelta); una de un solo sentido, solo una.
 *  - Pesos  : metros (Haversine entre los dos nodos) y segundos (metros ÷ velocidad del tipo
 *             de vía, o su límite «maxspeed» si es menor).
 *
 * Restricciones que se respetan: oneway=yes/-1, glorietas y autopistas (de un sentido aunque
 * no lo digan) y vías con access=no/private o motor_vehicle=no (no se descargan). Las vueltas prohibidas
 * (relaciones de OpenStreetMap) NO se consideran.
 *
 * Privacidad: a Overpass solo se mandan coordenadas redondeadas a 2 decimales (~1 km) para
 * pedir las calles de la zona; la ubicación exacta del cliente nunca sale del servidor.
 */
final class GrafoCalles
{
    /**
     * Calles locales y caminos rurales: en trayectos largos solo se piden cerca del origen y del
     * destino (en el resto, vías principales); en trayectos cortos, en todo el rectángulo.
     */
    public const LOCALES = ['residential', 'living_street', 'service', 'road', 'track'];

    /** Estados HTTP de Overpass que suelen ser pasajeros (saturación): se reintenta una vez. */
    private const REINTENTABLES = [0, 429, 502, 503, 504];

    /** @var array<int, array{0:float,1:float}> nodo => [lat, lng] (solo nodos que están en alguna arista) */
    private array $coords = [];

    /** @var array<int, list<array{0:int,1:float,2:float}>> nodo => [[vecino, metros, segundos], ...] */
    private array $aristas = [];

    private int $numAristas = 0;

    /** @param array<string,int|float> $velocidades km/h por tipo de vía (highway=*) */
    private function __construct(private readonly array $velocidades)
    {
    }

    // ── Descarga ─────────────────────────────────────────────

    /**
     * Consulta de Overpass: vías principales en un rectángulo que cubre ambos puntos (con
     * margen) y calles locales alrededor de cada uno. Si los puntos están a $kmTodasLasCalles
     * o menos en línea recta, se piden todas las calles del rectángulo (así no faltan los
     * caminos menores que conectan pueblos cercanos).
     *
     * @param array<string,int|float> $velocidades
     */
    public static function consulta(
        Punto $a,
        Punto $b,
        array $velocidades,
        int $radioLocalesM = 2000,
        ?float $margenGrados = null,
        float $kmTodasLasCalles = 10.0,
    ): string {
        // Margen alrededor de los puntos: crece con la distancia, porque el mejor camino puede
        // rodear (una fábrica, un río, una autopista sin cruce) y salirse de un rectángulo justo.
        $margenGrados ??= max(0.03, 0.5 * max(abs($a->lat - $b->lat), abs($a->lng - $b->lng)));

        if ($a->distanciaA($b) <= $kmTodasLasCalles * 1000) {
            return self::consultaRectangulo($a, $b, array_keys($velocidades), $margenGrados);
        }

        $principales = array_values(array_diff(array_keys($velocidades), self::LOCALES));
        $locales     = array_values(array_intersect(array_keys($velocidades), self::LOCALES));

        // Redondeo hacia afuera a 2 decimales: el rectángulo solo crece.
        $sur   = floor((min($a->lat, $b->lat) - $margenGrados) * 100) / 100;
        $norte = ceil((max($a->lat, $b->lat) + $margenGrados) * 100) / 100;
        $oeste = floor((min($a->lng, $b->lng) - $margenGrados) * 100) / 100;
        $este  = ceil((max($a->lng, $b->lng) + $margenGrados) * 100) / 100;

        // El redondeo mueve el centro hasta ~800 m: el radio se amplía para seguir cubriendo el punto.
        $radio   = $radioLocalesM + 800;
        $acceso  = '["access"!~"^(no|private)$"]["motor_vehicle"!~"^(no|private)$"]';
        $filtroP = '["highway"~"^(' . implode('|', $principales) . ')$"]' . $acceso;
        $filtroL = '["highway"~"^(' . implode('|', $locales) . ')$"]' . $acceso . '["service"!~"^(parking_aisle|driveway)$"]';

        $q = '[out:json][timeout:90];(';
        $q .= sprintf('way%s(%.2F,%.2F,%.2F,%.2F);', $filtroP, $sur, $oeste, $norte, $este);
        if ($locales !== []) {
            foreach ([$a, $b] as $p) {
                $q .= sprintf('way%s(around:%d,%.2F,%.2F);', $filtroL, $radio, round($p->lat, 2), round($p->lng, 2));
            }
        }

        return $q . ');(._;>;);out body qt;';
    }

    /** @param list<string> $tipos */
    private static function consultaRectangulo(Punto $a, Punto $b, array $tipos, float $margenGrados): string
    {
        $sur   = floor((min($a->lat, $b->lat) - $margenGrados) * 100) / 100;
        $norte = ceil((max($a->lat, $b->lat) + $margenGrados) * 100) / 100;
        $oeste = floor((min($a->lng, $b->lng) - $margenGrados) * 100) / 100;
        $este  = ceil((max($a->lng, $b->lng) + $margenGrados) * 100) / 100;

        $filtro = '["highway"~"^(' . implode('|', $tipos) . ')$"]["access"!~"^(no|private)$"]'
            . '["motor_vehicle"!~"^(no|private)$"]["service"!~"^(parking_aisle|driveway)$"]';

        return sprintf('[out:json][timeout:90];(way%s(%.2F,%.2F,%.2F,%.2F););(._;>;);out body qt;', $filtro, $sur, $oeste, $norte, $este);
    }

    /**
     * Descarga las calles entre dos puntos y arma el grafo.
     *
     * @param callable(string):?array $cache   opcional: recibe la consulta y devuelve el JSON guardado (o null)
     * @param callable(string,array):void $guardar opcional: guarda el JSON de esa consulta
     */
    public static function descargar(
        Punto $a,
        Punto $b,
        TransporteHttp $http,
        string $url,
        array $velocidades,
        int $radioLocalesM = 2000,
        int $tiempoEsperaSeg = 40,
        ?callable $cache = null,
        ?callable $guardar = null,
        float $kmTodasLasCalles = 10.0,
        int $esperaReintentoSeg = 3,
    ): self {
        $consulta = self::consulta($a, $b, $velocidades, $radioLocalesM, null, $kmTodasLasCalles);
        $json     = $cache !== null ? $cache($consulta) : null;

        if ($json === null) {
            // El servidor público se satura a ratos (502/503/504/429): un reintento suele bastar.
            for ($intento = 1; ; $intento++) {
                try {
                    $r = $http->get($url, ['data' => $consulta], $tiempoEsperaSeg, [
                        'Accept'     => 'application/json',
                        'User-Agent' => 'Fruver/2.0 (planeador de rutas)',
                    ]);
                } catch (ErrorProveedorRutas) {
                    $r = ['status' => 0, 'cuerpo' => ''];
                }

                if ($r['status'] === 200 || $intento >= 2 || ! in_array($r['status'], self::REINTENTABLES, true)) {
                    break;
                }
                sleep(max(0, $esperaReintentoSeg));
            }

            if ($r['status'] !== 200) {
                throw new ErrorProveedorRutas(match ($r['status']) {
                    0                 => 'No se pudo conectar con el servicio de calles (Overpass). Revisa la conexión a internet e intenta de nuevo.',
                    429, 502, 503     => 'El servicio de calles (Overpass) está saturado en este momento; intenta de nuevo en un minuto.',
                    504               => 'El servicio de calles (Overpass) tardó demasiado; intenta de nuevo o con puntos más cercanos.',
                    default           => 'El servicio de calles (Overpass) respondió HTTP ' . $r['status'] . '.',
                });
            }

            try {
                $json = json_decode($r['cuerpo'], true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new ErrorProveedorRutas('El servicio de calles (Overpass) respondió algo que no es JSON.');
            }

            if ($guardar !== null) {
                $guardar($consulta, $json);
            }
        }

        return self::desdeOverpass(is_array($json) ? $json : [], $velocidades);
    }

    // ── Construcción ─────────────────────────────────────────

    /**
     * @param array{elements?: list<array<string,mixed>>} $json respuesta de Overpass (out body)
     * @param array<string,int|float>                     $velocidades
     */
    public static function desdeOverpass(array $json, array $velocidades): self
    {
        $g     = new self($velocidades);
        $nodos = [];
        $vias  = [];

        foreach ($json['elements'] ?? [] as $e) {
            if (($e['type'] ?? '') === 'node' && isset($e['id'], $e['lat'], $e['lon'])) {
                $nodos[(int) $e['id']] = [(float) $e['lat'], (float) $e['lon']];
            } elseif (($e['type'] ?? '') === 'way' && isset($e['nodes'])) {
                $vias[] = $e;
            }
        }

        foreach ($vias as $via) {
            $tags = $via['tags'] ?? [];
            $tipo = (string) ($tags['highway'] ?? '');
            if (! isset($velocidades[$tipo])) {
                continue;
            }

            $kmh     = self::velocidad($tags, (float) $velocidades[$tipo]);
            $sentido = self::sentido($tags);
            $ids     = array_map('intval', $via['nodes']);

            for ($i = 0; $i < count($ids) - 1; $i++) {
                [$u, $v] = [$ids[$i], $ids[$i + 1]];
                if (! isset($nodos[$u], $nodos[$v]) || $u === $v) {
                    continue;
                }

                $metros   = self::metros($nodos[$u], $nodos[$v]);
                $segundos = $metros / ($kmh / 3.6);

                if ($sentido >= 0) {
                    $g->agregar($u, $v, $metros, $segundos, $nodos);
                }
                if ($sentido <= 0) {
                    $g->agregar($v, $u, $metros, $segundos, $nodos);
                }
            }
        }

        return $g;
    }

    /** 1 = solo en el orden de los nodos, -1 = solo al revés, 0 = doble sentido. */
    public static function sentido(array $tags): int
    {
        $oneway = strtolower((string) ($tags['oneway'] ?? ''));

        return match (true) {
            in_array($oneway, ['yes', 'true', '1'], true)        => 1,
            in_array($oneway, ['-1', 'reverse'], true)           => -1,
            $oneway === 'no'                                     => 0,
            ($tags['junction'] ?? '') === 'roundabout'           => 1,
            in_array($tags['highway'] ?? '', ['motorway'], true) => 1,
            default                                              => 0,
        };
    }

    /** Velocidad del tipo de vía, o su límite (maxspeed) si es menor. */
    private static function velocidad(array $tags, float $porTipo): float
    {
        $max = (string) ($tags['maxspeed'] ?? '');
        if (preg_match('/^\d+(\.\d+)?$/', $max) === 1 && (float) $max > 0) {
            return min($porTipo, (float) $max);
        }

        return max(1.0, $porTipo);
    }

    private function agregar(int $u, int $v, float $metros, float $segundos, array $nodos): void
    {
        $this->aristas[$u][] = [$v, $metros, $segundos];
        $this->coords[$u]    = $nodos[$u];
        $this->coords[$v]    = $nodos[$v];
        $this->numAristas++;
    }

    /** @param array{0:float,1:float} $a */
    private static function metros(array $a, array $b): float
    {
        return (new Punto($a[0], $a[1]))->distanciaA(new Punto($b[0], $b[1]));
    }

    // ── Consulta ─────────────────────────────────────────────

    public function numNodos(): int
    {
        return count($this->coords);
    }

    public function numAristas(): int
    {
        return $this->numAristas;
    }

    /**
     * Nodo de calle más cercano a un punto (así se «pega» el origen o el destino a la calle).
     *
     * @return array{0:int,1:float}|null [nodo, metros de distancia]
     */
    public function nodoMasCercano(Punto $p): ?array
    {
        $mejor = null;
        foreach ($this->coords as $id => [$lat, $lng]) {
            $d = $p->distanciaA(new Punto($lat, $lng));
            if ($mejor === null || $d < $mejor[1]) {
                $mejor = [$id, $d];
            }
        }

        return $mejor;
    }

    /**
     * Lista de adyacencia para Dijkstra con el peso elegido.
     *
     * @param 'distancia'|'tiempo' $criterio
     * @return array<int, list<array{0:int,1:float}>>
     */
    public function adyacencia(string $criterio): array
    {
        $i = match ($criterio) {
            'distancia' => 1,
            'tiempo'    => 2,
            default     => throw new InvalidArgumentException('Criterio no válido: usa distancia o tiempo.'),
        };

        $lista = [];
        foreach ($this->aristas as $u => $salientes) {
            foreach ($salientes as $a) {
                $lista[$u][] = [$a[0], $a[$i]];
            }
        }

        return $lista;
    }

    /**
     * Metros y segundos de un camino (si hay dos aristas entre los mismos nodos, la que
     * gane según el criterio, igual que en Dijkstra).
     *
     * @param list<int> $camino
     * @return array{metros:float, segundos:float}
     */
    public function medir(array $camino, string $criterio): array
    {
        $i = $criterio === 'distancia' ? 1 : 2;
        $metros = $segundos = 0.0;

        for ($k = 0; $k < count($camino) - 1; $k++) {
            $mejor = null;
            foreach ($this->aristas[$camino[$k]] ?? [] as $a) {
                if ($a[0] === $camino[$k + 1] && ($mejor === null || $a[$i] < $mejor[$i])) {
                    $mejor = $a;
                }
            }
            if ($mejor === null) {
                throw new InvalidArgumentException('El camino usa una arista que no existe en el grafo.');
            }
            $metros   += $mejor[1];
            $segundos += $mejor[2];
        }

        return ['metros' => $metros, 'segundos' => $segundos];
    }

    /**
     * @param list<int> $camino
     * @return list<array{lat:float,lng:float}>
     */
    public function coordenadas(array $camino): array
    {
        return array_map(fn (int $n): array => ['lat' => $this->coords[$n][0], 'lng' => $this->coords[$n][1]], $camino);
    }
}
