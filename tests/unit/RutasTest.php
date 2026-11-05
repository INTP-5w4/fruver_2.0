<?php

use App\Libraries\Rutas\EnlaceNavegacion;
use App\Libraries\Rutas\ErrorProveedorRutas;
use App\Libraries\Rutas\Geocodificador;
use App\Libraries\Rutas\OpcionesRuta;
use App\Libraries\Rutas\OptimizadorGoogle;
use App\Libraries\Rutas\OptimizadorLocal;
use App\Libraries\Rutas\Parada;
use App\Libraries\Rutas\PlaneadorRutas;
use App\Libraries\Rutas\Punto;
use App\Libraries\Rutas\ResultadoRuta;
use App\Models\Modelo_pedido;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Rutas\TransporteFalso;

/**
 * @internal
 */
final class RutasTest extends CIUnitTestCase
{
    private Punto $almacen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->almacen = new Punto(19.0433, -98.1981); // Puebla centro
    }

    private function opciones(string $estrategia = 'rapida', bool $regresar = true, string $vehiculo = 'moto'): OpcionesRuta
    {
        return new OpcionesRuta($estrategia, $vehiculo, new DateTimeImmutable('+1 day 09:00'), $regresar);
    }

    /** Cuatro paradas en línea hacia el norte, entregadas en desorden. */
    private function paradasEnLinea(): array
    {
        return [
            new Parada('p3', new Punto(19.0733, -98.1981)),
            new Parada('p1', new Punto(19.0533, -98.1981)),
            new Parada('p4', new Punto(19.0833, -98.1981)),
            new Parada('p2', new Punto(19.0633, -98.1981)),
        ];
    }

    // ── Punto ─────────────────────────────────────────────

    public function testDistanciaHaversine(): void
    {
        // 0.01° de latitud ≈ 1,112 m
        $d = (new Punto(19.0, -98.0))->distanciaA(new Punto(19.01, -98.0));
        $this->assertEqualsWithDelta(1112, $d, 5);
    }

    public function testPuntoDesdeTextoInvalido(): void
    {
        $this->assertNull(Punto::desde('abc', '1'));
        $this->assertNull(Punto::desde('95', '1'));
        $this->assertNull(Punto::desde(null, null));
        $this->assertSame(19.5, Punto::desde('19.5', '-98.2')->lat);
    }

    // ── Optimizador local ─────────────────────────────────

    public function testLocalOrdenaParadasEnLinea(): void
    {
        $r = (new OptimizadorLocal())->calcular($this->almacen, $this->paradasEnLinea(), $this->opciones('aproximada', false));

        $this->assertSame(['p1', 'p2', 'p3', 'p4'], $r->orden);
        $this->assertSame('local', $r->proveedor);
        $this->assertCount(4, $r->tramos);
        $this->assertNotNull($r->aviso);
    }

    public function testLocalConRegresoAgregaUnTramo(): void
    {
        $r = (new OptimizadorLocal())->calcular($this->almacen, $this->paradasEnLinea(), $this->opciones('aproximada', true));

        $this->assertCount(5, $r->tramos);
        $this->assertSame($r->distanciaM, array_sum(array_column($r->tramos, 'distancia_m')));
        // ida y vuelta ≈ 2 × 4.45 km × factor de calles
        $this->assertEqualsWithDelta(2 * 4448 * OptimizadorLocal::FACTOR_CALLES, $r->distanciaM, 200);
    }

    public function testLocalManualRespetaElOrden(): void
    {
        $r = (new OptimizadorLocal())->calcular($this->almacen, $this->paradasEnLinea(), $this->opciones('manual'));

        $this->assertSame(['p3', 'p1', 'p4', 'p2'], $r->orden);
    }

    public function testDosOptMejoraAlVecinoMasCercano(): void
    {
        // Cuadrado: el vecino más cercano cruza; 2-opt debe dejar un recorrido sin cruces.
        $paradas = [
            new Parada('a', new Punto(19.05, -98.20)),
            new Parada('b', new Punto(19.05, -98.18)),
            new Parada('c', new Punto(19.07, -98.18)),
            new Parada('d', new Punto(19.07, -98.20)),
        ];
        $r = (new OptimizadorLocal())->calcular(new Punto(19.045, -98.20), $paradas, $this->opciones('aproximada'));

        $this->assertContains(implode('', $r->orden), ['abcd', 'dcba', 'adcb', 'bcda']);
    }

    // ── Google (con transporte falso) ─────────────────────

    private function respuestaGoogle(array $indices, int $tramos): array
    {
        $legs = [];
        for ($i = 0; $i < $tramos; $i++) {
            $legs[] = ['distanceMeters' => 1000 + $i, 'duration' => (120 + $i) . 's'];
        }

        return ['status' => 200, 'cuerpo' => json_encode(['routes' => [[
            'optimizedIntermediateWaypointIndex' => $indices,
            'distanceMeters' => 9999,
            'duration'       => '777s',
            'polyline'       => ['encodedPolyline' => 'abc'],
            'legs'           => $legs,
        ]]])];
    }

    public function testGoogleArmaLaSolicitudCorrecta(): void
    {
        $http = new TransporteFalso([$this->respuestaGoogle([1, 3, 0, 2], 5)]);
        $g    = new OptimizadorGoogle('LLAVE', $http);

        $g->calcular($this->almacen, $this->paradasEnLinea(), $this->opciones('rapida'));

        $enviado = $http->enviados[0];
        $this->assertSame(OptimizadorGoogle::URL, $enviado['url']);
        $this->assertSame('LLAVE', $enviado['encabezados']['X-Goog-Api-Key']);
        $this->assertStringContainsString('routes.optimizedIntermediateWaypointIndex', $enviado['encabezados']['X-Goog-FieldMask']);
        $this->assertTrue($enviado['cuerpo']['optimizeWaypointOrder']);
        $this->assertSame('TRAFFIC_AWARE', $enviado['cuerpo']['routingPreference']);
        $this->assertSame('TWO_WHEELER', $enviado['cuerpo']['travelMode']);
        $this->assertCount(4, $enviado['cuerpo']['intermediates']);
        $this->assertSame($enviado['cuerpo']['origin'], $enviado['cuerpo']['destination']);
        $this->assertArrayHasKey('departureTime', $enviado['cuerpo']);
        $this->assertArrayNotHasKey('routeModifiers', $enviado['cuerpo']);
    }

    public function testGoogleAplicaElOrdenDevuelto(): void
    {
        $http = new TransporteFalso([$this->respuestaGoogle([1, 3, 0, 2], 5)]);
        $r    = (new OptimizadorGoogle('LLAVE', $http))->calcular($this->almacen, $this->paradasEnLinea(), $this->opciones('rapida'));

        $this->assertSame(['p1', 'p2', 'p3', 'p4'], $r->orden);
        $this->assertSame('google', $r->proveedor);
        $this->assertSame(9999, $r->distanciaM);
        $this->assertSame(777, $r->duracionS);
        $this->assertSame('abc', $r->polyline);
        $this->assertSame(['distancia_m' => 1000, 'duracion_s' => 120], $r->tramos[0]);
    }

    public function testGoogleSinCuotasYManual(): void
    {
        $http = new TransporteFalso([$this->respuestaGoogle([], 5), $this->respuestaGoogle([], 5)]);
        $g    = new OptimizadorGoogle('LLAVE', $http);

        $g->calcular($this->almacen, $this->paradasEnLinea(), $this->opciones('sin_cuotas', true, 'auto'));
        $this->assertSame(['avoidTolls' => true, 'avoidHighways' => true], $http->enviados[0]['cuerpo']['routeModifiers']);
        $this->assertSame('DRIVE', $http->enviados[0]['cuerpo']['travelMode']);

        $r = $g->calcular($this->almacen, $this->paradasEnLinea(), $this->opciones('manual'));
        $this->assertFalse($http->enviados[1]['cuerpo']['optimizeWaypointOrder']);
        $this->assertSame(['p3', 'p1', 'p4', 'p2'], $r->orden);
    }

    public function testGoogleSinRegresoUsaLaParadaMasLejanaComoDestino(): void
    {
        $http = new TransporteFalso([$this->respuestaGoogle([0, 2, 1], 4)]);
        $r    = (new OptimizadorGoogle('LLAVE', $http))->calcular($this->almacen, $this->paradasEnLinea(), $this->opciones('rapida', false));

        $cuerpo = $http->enviados[0]['cuerpo'];
        $this->assertEqualsWithDelta(19.0833, $cuerpo['destination']['location']['latLng']['latitude'], 0.0001);
        $this->assertCount(3, $cuerpo['intermediates']);
        $this->assertSame('p4', $r->orden[3]);
        $this->assertSame(['p3', 'p2', 'p1', 'p4'], $r->orden);
    }

    public function testGoogleUnaSolaParadaNoOptimiza(): void
    {
        $http = new TransporteFalso([$this->respuestaGoogle([0], 2)]);
        (new OptimizadorGoogle('LLAVE', $http))->calcular($this->almacen, [new Parada('p9', new Punto(19.05, -98.2))], $this->opciones('rapida'));

        $this->assertFalse($http->enviados[0]['cuerpo']['optimizeWaypointOrder']);
    }

    public function testGoogleErrorHttpLanzaExcepcion(): void
    {
        $http = new TransporteFalso([['status' => 403, 'cuerpo' => '{"error":{"message":"API key not valid"}}']]);

        $this->expectException(ErrorProveedorRutas::class);
        $this->expectExceptionMessage('API key not valid');
        (new OptimizadorGoogle('LLAVE', $http))->calcular($this->almacen, $this->paradasEnLinea(), $this->opciones());
    }

    public function testGoogleTramosIncompletosLanzaExcepcion(): void
    {
        $http = new TransporteFalso([$this->respuestaGoogle([0, 1, 2, 3], 2)]);

        $this->expectException(ErrorProveedorRutas::class);
        (new OptimizadorGoogle('LLAVE', $http))->calcular($this->almacen, $this->paradasEnLinea(), $this->opciones());
    }

    public function testSegundos(): void
    {
        $this->assertSame(1234, OptimizadorGoogle::segundos('1234s'));
        $this->assertSame(13, OptimizadorGoogle::segundos('12.6s'));
        $this->assertSame(0, OptimizadorGoogle::segundos('0s'));
    }

    // ── Planeador ─────────────────────────────────────────

    private function planeador(?TransporteFalso $http): PlaneadorRutas
    {
        $cfg = new \Config\Rutas();
        $cfg->llaveServidor = $http !== null ? 'LLAVE' : '';

        return PlaneadorRutas::crear($cfg, $http);
    }

    public function testPlaneadorSinLlaveUsaAproximada(): void
    {
        $op = $this->planeador(null)->opciones($this->almacen, $this->paradasEnLinea(), $this->opciones());

        $this->assertSame(['aproximada'], array_keys($op));
        $this->assertSame('local', $op['aproximada']->proveedor);
    }

    public function testPlaneadorCaeALocalSiGoogleFalla(): void
    {
        $http = new TransporteFalso([['status' => 500, 'cuerpo' => '{"error":{"message":"boom"}}']]);
        $op   = $this->planeador($http)->opciones($this->almacen, $this->paradasEnLinea(), $this->opciones());

        $this->assertSame(['aproximada'], array_keys($op));
        $this->assertCount(1, $http->enviados, 'No debe insistir con Google tras el primer error');
        $this->assertStringContainsString('El servicio de mapas no respondió', (string) $op['aproximada']->aviso);
    }

    public function testPlaneadorOfreceOpcionesConGoogle(): void
    {
        $http = new TransporteFalso([$this->respuestaGoogle([1, 3, 0, 2], 5), $this->respuestaGoogle([1, 3, 0, 2], 5)]);
        $op   = $this->planeador($http)->opciones($this->almacen, $this->paradasEnLinea(), $this->opciones());

        // Sin horarios ni prioridades no se ofrece "prioridad".
        $this->assertSame(['rapida', 'sin_cuotas'], array_keys($op));
    }

    public function testPlaneadorRechazaDemasiadasParadas(): void
    {
        $paradas = [];
        for ($i = 0; $i < 26; $i++) {
            $paradas[] = new Parada('p' . $i, new Punto(19.0 + $i / 1000, -98.2));
        }

        $this->expectException(InvalidArgumentException::class);
        $this->planeador(null)->calcular($this->almacen, $paradas, $this->opciones('aproximada'));
    }

    public function testPorPrioridad(): void
    {
        $p = PlaneadorRutas::porPrioridad([
            new Parada('sin', new Punto(19.05, -98.2)),
            new Parada('tarde', new Punto(19.05, -98.2), '14:00:00'),
            new Parada('vip', new Punto(19.05, -98.2), null, 1),
            new Parada('temprano', new Punto(19.05, -98.2), '11:00:00'),
        ]);

        $this->assertSame(['temprano', 'tarde', 'vip', 'sin'], array_map(static fn ($x) => $x->clave, $p));
    }

    // ── Resultado, enlaces, geocodificador, utilidades ────

    public function testEtasSumanTramosYTiempoDeAtencion(): void
    {
        $r     = new ResultadoRuta('rapida', 'google', ['a', 'b'], [['distancia_m' => 1, 'duracion_s' => 600], ['distancia_m' => 1, 'duracion_s' => 300], ['distancia_m' => 1, 'duracion_s' => 900]], 3, 1800);
        $salida = new DateTimeImmutable('2026-11-06 09:00:00');
        $etas  = $r->etas($salida, 5);

        $this->assertSame(date('Y-m-d H:i:s', $salida->getTimestamp() + 600), $etas['a']);
        $this->assertSame(date('Y-m-d H:i:s', $salida->getTimestamp() + 600 + 300 + 300), $etas['b']);
        $this->assertSame($r->aArreglo(), ResultadoRuta::desdeArreglo($r->aArreglo())->aArreglo());
    }

    public function testEnlaceNavegacion(): void
    {
        $url = EnlaceNavegacion::hacia(new Punto(19.05, -98.21), 'ChIJabc', 'moto');

        $this->assertStringStartsWith('https://www.google.com/maps/dir/?api=1&', $url);
        $this->assertStringContainsString('destination=19.0500000%2C-98.2100000', $url);
        $this->assertStringContainsString('destination_place_id=ChIJabc', $url);
        $this->assertStringContainsString('travelmode=two-wheeler', $url);
        $this->assertNull(EnlaceNavegacion::hacia(null, null));
    }

    public function testGeocodificador(): void
    {
        $http = new TransporteFalso([
            ['status' => 200, 'cuerpo' => json_encode(['status' => 'OK', 'results' => [[
                'place_id' => 'ChIJxyz',
                'formatted_address' => 'Calle 5 Sur 101, Centro, 72000 Puebla, Pue., México',
                'geometry' => ['location' => ['lat' => 19.04, 'lng' => -98.2], 'location_type' => 'ROOFTOP'],
                'address_components' => [['long_name' => '72000', 'types' => ['postal_code']]],
            ]]])],
            ['status' => 200, 'cuerpo' => '{"status":"ZERO_RESULTS","results":[]}'],
            ['status' => 200, 'cuerpo' => '{"status":"REQUEST_DENIED","error_message":"llave inválida"}'],
        ]);
        $g = new Geocodificador('LLAVE', $http);

        $r = $g->geocodificar('Calle 5 Sur 101, Centro, Puebla');
        $this->assertSame(19.04, $r['lat']);
        $this->assertSame('ChIJxyz', $r['place_id']);
        $this->assertSame('ROOFTOP', $r['precision_geo']);
        $this->assertSame('72000', $r['codigo_postal']);
        $this->assertSame('country:MX', $http->enviados[0]['parametros']['components']);

        $this->assertNull($g->geocodificar('no existe'));

        $this->expectException(ErrorProveedorRutas::class);
        $g->geocodificar('otra');
    }

    public function testTextoDeDireccion(): void
    {
        $this->assertSame(
            'Reforma 12, Centro, 72000 Puebla, Puebla, México',
            Geocodificador::textoDe(['calle' => 'Reforma', 'numero' => '12', 'colonia' => 'Centro', 'codigo_postal' => '72000', 'municipio' => 'Puebla', 'estado' => 'Puebla'])
        );
    }

    public function testHoraValida(): void
    {
        $this->assertSame('14:30:00', Modelo_pedido::horaValida('14:30'));
        $this->assertSame('09:05:10', Modelo_pedido::horaValida('09:05:10'));
        $this->assertNull(Modelo_pedido::horaValida('25:00'));
        $this->assertNull(Modelo_pedido::horaValida(''));
        $this->assertNull(Modelo_pedido::horaValida('14:30; DROP'));
    }

    public function testRegresoAOtroPunto(): void
    {
        // Sale desde el GPS (lejos, al norte) y regresa al almacén (sur).
        $gps = new Punto(19.0933, -98.1981);
        $op  = new OpcionesRuta('rapida', 'moto', new DateTimeImmutable('+1 day 09:00'), true, $this->almacen);

        $r = (new OptimizadorLocal())->calcular($gps, $this->paradasEnLinea(), $op);
        $this->assertSame(['p4', 'p3', 'p2', 'p1'], $r->orden);
        $this->assertCount(5, $r->tramos);

        $http = new TransporteFalso([$this->respuestaGoogle([2, 0, 3, 1], 5)]);
        (new OptimizadorGoogle('LLAVE', $http))->calcular($gps, $this->paradasEnLinea(), $op);
        $this->assertEqualsWithDelta(19.0433, $http->enviados[0]['cuerpo']['destination']['location']['latLng']['latitude'], 0.0001);
    }

    public function testSalidaEnZonaHoraria(): void
    {
        $s = PlaneadorRutas::salida('2026-11-06', '09:00', 'America/Mexico_City');
        $this->assertSame('2026-11-06T15:00:00Z', $s->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'));
    }
}
