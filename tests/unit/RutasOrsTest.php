<?php

use App\Libraries\Rutas\CspMapas;
use App\Libraries\Rutas\ErrorProveedorRutas;
use App\Libraries\Rutas\GeocodificadorOrs;
use App\Libraries\Rutas\OpcionesRuta;
use App\Libraries\Rutas\OptimizadorGoogle;
use App\Libraries\Rutas\OptimizadorOrs;
use App\Libraries\Rutas\Parada;
use App\Libraries\Rutas\PlaneadorRutas;
use App\Libraries\Rutas\Punto;
use App\Libraries\Rutas\ServiciosMapa;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Rutas\TransporteFalso;

/**
 * Proveedor openrouteservice (sin red: respuestas simuladas con el formato real de la API).
 *
 * @internal
 */
final class RutasOrsTest extends CIUnitTestCase
{
    private const URL = 'https://api.heigit.org/openrouteservice';

    private Punto $almacen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->almacen = new Punto(19.0433, -98.1981);
    }

    private function op(string $estrategia = 'rapida', bool $regresar = true, string $vehiculo = 'moto', ?Punto $regresoA = null): OpcionesRuta
    {
        return new OpcionesRuta($estrategia, $vehiculo, new DateTimeImmutable('+1 day 09:00'), $regresar, $regresoA);
    }

    /** p3, p1, p4, p2 en línea hacia el norte (orden óptimo: p1, p2, p3, p4). */
    private function paradas(): array
    {
        return [
            new Parada('p3', new Punto(19.0733, -98.1981)),
            new Parada('p1', new Punto(19.0533, -98.1981)),
            new Parada('p4', new Punto(19.0833, -98.1981)),
            new Parada('p2', new Punto(19.0633, -98.1981)),
        ];
    }

    /** Respuesta de /optimization (VROOM) visitando los jobs en ese orden de ids. */
    private function optimizacion(array $idsJobs, array $sinAsignar = []): array
    {
        $pasos = [['type' => 'start', 'location' => [-98.1981, 19.0433], 'arrival' => 0]];
        foreach ($idsJobs as $i => $id) {
            $pasos[] = ['type' => 'job', 'id' => $id, 'job' => $id, 'location' => [0, 0], 'arrival' => 100 * ($i + 1)];
        }
        $pasos[] = ['type' => 'end', 'location' => [-98.1981, 19.0433], 'arrival' => 999];

        return ['status' => 200, 'cuerpo' => json_encode([
            'code' => 0, 'summary' => ['cost' => 999, 'routes' => 1, 'unassigned' => count($sinAsignar)],
            'unassigned' => $sinAsignar, 'routes' => [['vehicle' => 1, 'steps' => $pasos]],
        ])];
    }

    /** Respuesta de /v2/directions/{perfil}/json con $n segmentos. */
    private function direcciones(int $n): array
    {
        $seg = [];
        for ($i = 0; $i < $n; $i++) {
            $seg[] = ['distance' => 1000.4 + $i, 'duration' => 120.6 + $i, 'steps' => []];
        }

        return ['status' => 200, 'cuerpo' => json_encode(['routes' => [[
            'summary'  => ['distance' => 9876.5, 'duration' => 654.4],
            'segments' => $seg,
            'geometry' => '_p~iF~ps|U_ulLnnqC',
        ]]])];
    }

    private function ors(TransporteFalso $http): OptimizadorOrs
    {
        return new OptimizadorOrs('TOKEN', $http, self::URL, 'es');
    }

    // ── Optimizador ─────────────────────────────────────────

    public function testOptimizaYMideConLasLlamadasCorrectas(): void
    {
        // jobs: 1=p3, 2=p1, 3=p4, 4=p2 → orden óptimo 2,4,1,3
        $http = new TransporteFalso([$this->optimizacion([2, 4, 1, 3]), $this->direcciones(5)]);
        $r    = $this->ors($http)->calcular($this->almacen, $this->paradas(), $this->op());

        $this->assertSame(['p1', 'p2', 'p3', 'p4'], $r->orden);
        $this->assertSame('ors', $r->proveedor);
        $this->assertSame(9877, $r->distanciaM);
        $this->assertSame(654, $r->duracionS);
        $this->assertSame(['distancia_m' => 1000, 'duracion_s' => 121], $r->tramos[0]);
        $this->assertCount(5, $r->tramos);
        $this->assertSame('_p~iF~ps|U_ulLnnqC', $r->polyline);

        [$opt, $dir] = $http->enviados;
        $this->assertSame(self::URL . '/optimization', $opt['url']);
        $this->assertSame('TOKEN', $opt['encabezados']['Authorization']);
        $this->assertSame([-98.1981, 19.0733], $opt['cuerpo']['jobs'][0]['location'], 'Coordenadas en orden [lng, lat]');
        $this->assertSame(1, $opt['cuerpo']['jobs'][0]['id']);
        $this->assertSame('driving-car', $opt['cuerpo']['vehicles'][0]['profile'], 'La moto usa el perfil de auto');
        $this->assertSame([-98.1981, 19.0433], $opt['cuerpo']['vehicles'][0]['start']);
        $this->assertSame([-98.1981, 19.0433], $opt['cuerpo']['vehicles'][0]['end']);

        $this->assertSame(self::URL . '/v2/directions/driving-car/json', $dir['url']);
        $this->assertCount(6, $dir['cuerpo']['coordinates']);                 // almacén + 4 + regreso
        $this->assertSame([-98.1981, 19.0533], $dir['cuerpo']['coordinates'][1]); // p1 primero
        $this->assertSame(array_fill(0, 6, -1), $dir['cuerpo']['radiuses']);
        $this->assertArrayNotHasKey('options', $dir['cuerpo']);
    }

    public function testSinRegresoNoMandaFinNiPuntoDeRegreso(): void
    {
        $http = new TransporteFalso([$this->optimizacion([2, 4, 1, 3]), $this->direcciones(4)]);
        $r    = $this->ors($http)->calcular($this->almacen, $this->paradas(), $this->op('rapida', false));

        $this->assertArrayNotHasKey('end', $http->enviados[0]['cuerpo']['vehicles'][0]);
        $this->assertCount(5, $http->enviados[1]['cuerpo']['coordinates']);
        $this->assertCount(4, $r->tramos);
        $this->assertFalse($r->regresa);
    }

    public function testRegresoAOtroPuntoYBicicleta(): void
    {
        $gps  = new Punto(19.09, -98.20);
        $http = new TransporteFalso([$this->optimizacion([3, 1, 4, 2]), $this->direcciones(5)]);
        $this->ors($http)->calcular($gps, $this->paradas(), $this->op('rapida', true, 'bicicleta', $this->almacen));

        $this->assertSame([-98.20, 19.09], $http->enviados[0]['cuerpo']['vehicles'][0]['start']);
        $this->assertSame([-98.1981, 19.0433], $http->enviados[0]['cuerpo']['vehicles'][0]['end']);
        $this->assertSame('cycling-regular', $http->enviados[0]['cuerpo']['vehicles'][0]['profile']);
        $this->assertStringEndsWith('/v2/directions/cycling-regular/json', $http->enviados[1]['url']);
    }

    public function testManualYPrioridadNoLlamanAlOptimizador(): void
    {
        $http = new TransporteFalso([$this->direcciones(5)]);
        $r    = $this->ors($http)->calcular($this->almacen, $this->paradas(), $this->op('manual'));

        $this->assertCount(1, $http->enviados);
        $this->assertStringContainsString('/v2/directions/', $http->enviados[0]['url']);
        $this->assertSame(['p3', 'p1', 'p4', 'p2'], $r->orden);
    }

    public function testSinCuotasEvitaAutopistasYReusaElOrden(): void
    {
        $http = new TransporteFalso([$this->optimizacion([2, 4, 1, 3]), $this->direcciones(5), $this->direcciones(5)]);
        $ors  = $this->ors($http);

        $ors->calcular($this->almacen, $this->paradas(), $this->op('rapida'));
        $r = $ors->calcular($this->almacen, $this->paradas(), $this->op('sin_cuotas'));

        $this->assertCount(3, $http->enviados, 'El orden óptimo se calcula una sola vez');
        $this->assertSame(['avoid_features' => ['tollways', 'highways']], $http->enviados[2]['cuerpo']['options']);
        $this->assertSame(['p1', 'p2', 'p3', 'p4'], $r->orden);
    }

    public function testParadaSinAsignarEsError(): void
    {
        $http = new TransporteFalso([$this->optimizacion([2, 4, 1], [['id' => 3, 'location' => [0, 0]]])]);

        $this->expectException(ErrorProveedorRutas::class);
        $this->expectExceptionMessage('no pudo incluir 1 parada');
        $this->ors($http)->calcular($this->almacen, $this->paradas(), $this->op());
    }

    public function testErrorDeLlaveOCuota(): void
    {
        $http = new TransporteFalso([['status' => 403, 'cuerpo' => '{"error":"Access to this API has been disallowed"}']]);

        $this->expectException(ErrorProveedorRutas::class);
        $this->expectExceptionMessage('HTTP 403: Access to this API has been disallowed');
        $this->ors($http)->calcular($this->almacen, $this->paradas(), $this->op());
    }

    public function testSegmentosIncompletosEsError(): void
    {
        $http = new TransporteFalso([$this->direcciones(2)]);

        $this->expectException(ErrorProveedorRutas::class);
        $this->ors($http)->calcular($this->almacen, $this->paradas(), $this->op('manual'));
    }

    public function testPlaneadorConOrsYCaidaALocal(): void
    {
        $cfg = new \Config\Rutas();
        $cfg->llaveOrs = 'TOKEN';

        // Opciones: rápida (optimiza + mide) y sin casetas (reusa el orden + mide).
        $http = new TransporteFalso([$this->optimizacion([2, 4, 1, 3]), $this->direcciones(5), $this->direcciones(5)]);
        $plan = PlaneadorRutas::crear($cfg, $http);
        $op   = $plan->opciones($this->almacen, $this->paradas(), $this->op());

        $this->assertSame(['rapida', 'sin_cuotas'], array_keys($op));
        $this->assertSame('ors', $op['rapida']->proveedor);
        $this->assertSame(25, $plan->maxParadas());
        $this->assertTrue($plan->hayServicio());

        // Si openrouteservice falla, la ruta sale aproximada (no se cae la pantalla).
        $falla = PlaneadorRutas::crear($cfg, new TransporteFalso([['status' => 503, 'cuerpo' => '{}']]));
        $op    = $falla->opciones($this->almacen, $this->paradas(), $this->op());
        $this->assertSame(['aproximada'], array_keys($op));
        $this->assertSame('local', $op['aproximada']->proveedor);
    }

    // ── Geocodificador ──────────────────────────────────────

    private function geojson(array $props, array $lngLat = [-98.2063, 19.0414]): array
    {
        return ['status' => 200, 'cuerpo' => json_encode(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => $lngLat], 'properties' => $props,
        ]]])];
    }

    public function testGeocodificaConOrdenLngLatYPrecision(): void
    {
        $http = new TransporteFalso([
            $this->geojson(['label' => '5 Sur 101, Centro, Puebla, PUE, México', 'layer' => 'address', 'match_type' => 'exact',
                            'housenumber' => '101', 'street' => '5 Sur', 'neighbourhood' => 'Centro', 'locality' => 'Puebla',
                            'region' => 'Puebla', 'postalcode' => '72000']),
            $this->geojson(['label' => 'Centro, Puebla', 'layer' => 'neighbourhood', 'match_type' => 'fallback']),
            ['status' => 200, 'cuerpo' => '{"type":"FeatureCollection","features":[]}'],
        ]);
        $g = new GeocodificadorOrs('TOKEN', $http, self::URL, 'MX', $this->almacen);

        $r = $g->geocodificar('5 Sur 101, Centro, Puebla');
        $this->assertSame(19.0414, $r['lat']);
        $this->assertSame(-98.2063, $r['lng']);
        $this->assertSame('EXACTA', $r['precision_geo']);
        $this->assertSame('72000', $r['codigo_postal']);
        $this->assertNull($r['place_id'], 'Un id de OpenStreetMap no sirve para navegar con Google Maps');
        $this->assertSame('osm', $g->origen());

        $env = $http->enviados[0];
        $this->assertSame(self::URL . '/geocode/search', $env['url']);
        $this->assertSame('MX', $env['parametros']['boundary.country']);
        $this->assertSame('19.0433', $env['parametros']['focus.point.lat']);
        $this->assertSame('TOKEN', $env['encabezados']['Authorization']);
        $this->assertArrayNotHasKey('api_key', $env['parametros'], 'La llave va en el encabezado, no en la URL');

        $this->assertSame('APPROXIMATE', $g->geocodificar('Centro')['precision_geo']);
        $this->assertNull($g->geocodificar('no existe'));
    }

    public function testSugerenciasUsanAutocomplete(): void
    {
        $http = new TransporteFalso([$this->geojson(['label' => 'Reforma, Puebla', 'layer' => 'street', 'name' => 'Reforma'])]);
        $r    = (new GeocodificadorOrs('TOKEN', $http, self::URL))->sugerencias('Reforma Pue', 5);

        $this->assertStringEndsWith('/geocode/autocomplete', $http->enviados[0]['url']);
        $this->assertSame('Reforma', $r[0]['calle']);
        $this->assertSame('APPROXIMATE', $r[0]['precision_geo']);
    }

    // ── Configuración y CSP ─────────────────────────────────

    public function testEleccionDeProveedor(): void
    {
        $c = new \Config\Rutas();
        $this->assertSame('local', $c->proveedorRutas());
        $this->assertSame('leaflet', $c->proveedorMapa(), 'Sin llaves hay mapa de OpenStreetMap para ubicar con pin');
        $this->assertNull(ServiciosMapa::optimizador($c));

        $c->llaveServidor = 'G';
        $this->assertSame('google', $c->proveedorRutas());
        $this->assertSame('', $c->proveedorMapa(), 'Datos de Google solo sobre mapas de Google');
        $c->llaveNavegador = 'GN';
        $this->assertSame('google', $c->proveedorMapa());
        $this->assertInstanceOf(OptimizadorGoogle::class, ServiciosMapa::optimizador($c));

        $c->llaveOrs = 'O';
        $this->assertSame('ors', $c->proveedorRutas(), 'openrouteservice tiene preferencia si hay llave');
        $this->assertSame('leaflet', $c->proveedorMapa());
        $this->assertInstanceOf(OptimizadorOrs::class, ServiciosMapa::optimizador($c));
        $this->assertInstanceOf(GeocodificadorOrs::class, ServiciosMapa::geocodificador($c));

        $c->proveedor = 'google';
        $this->assertSame('google', $c->proveedorRutas());
        $c->proveedor = 'local';
        $this->assertSame('local', $c->proveedorRutas());
        $c->proveedor = 'ors';
        $c->llaveOrs  = '';
        $this->assertSame('local', $c->proveedorRutas(), 'ors sin llave cae a local');
    }

    public function testOrigenesDeCapasParaCsp(): void
    {
        $this->assertSame(['https://tile.openstreetmap.org'], CspMapas::origenesDeCapas('https://tile.openstreetmap.org/{z}/{x}/{y}.png'));
        $this->assertSame(['https://*.tile.ejemplo.org'], CspMapas::origenesDeCapas('https://{s}.tile.ejemplo.org/{z}/{x}/{y}.png'));
        $this->assertSame([], CspMapas::origenesDeCapas('http://inseguro.org/{z}/{x}/{y}.png'));
        $this->assertSame([], CspMapas::origenesDeCapas("https://mal'host/{z}"));
    }
}
