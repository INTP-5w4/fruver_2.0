<?php

use App\Libraries\Rutas\Dijkstra;
use App\Libraries\Rutas\ErrorProveedorRutas;
use App\Libraries\Rutas\GrafoCalles;
use App\Libraries\Rutas\Punto;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Rutas\TransporteFalso;

/**
 * Dijkstra básico, su verificación con Bellman-Ford y el grafo de calles de OpenStreetMap.
 *
 * @internal
 */
final class DijkstraTest extends CIUnitTestCase
{
    /**
     * Grafo dirigido de ejemplo (nodo => [[vecino, peso], ...]):
     *
     *   A→B 7, A→C 9, A→F 14, B→C 10, B→D 15, C→D 11, C→F 2, D→E 6, F→E 9
     */
    private const GRAFO = [
        'A' => [['B', 7], ['C', 9], ['F', 14]],
        'B' => [['C', 10], ['D', 15]],
        'C' => [['D', 11], ['F', 2]],
        'D' => [['E', 6]],
        'F' => [['E', 9]],
    ];

    // ── Algoritmo ───────────────────────────────────────────

    public function testCaminoMasCortoEnElGrafoDeEjemplo(): void
    {
        $r = Dijkstra::caminoMasCorto(self::GRAFO, 'A', 'E');

        $this->assertSame(20.0, $r['costo']); // A→C (9) → F (2) → E (9)
        $this->assertSame(['A', 'C', 'F', 'E'], $r['camino']);

        $this->assertSame(11.0, Dijkstra::caminoMasCorto(self::GRAFO, 'A', 'F')['costo']); // mejor que A→F directo (14)
        $this->assertSame(['A', 'C', 'F'], Dijkstra::caminoMasCorto(self::GRAFO, 'A', 'F')['camino']);
    }

    public function testRespetaElSentidoDeLasAristas(): void
    {
        // E no tiene salidas: de E no se llega a A aunque de A sí se llegue a E.
        $this->assertNull(Dijkstra::caminoMasCorto(self::GRAFO, 'E', 'A'));
    }

    public function testCasosLimite(): void
    {
        $this->assertSame(['costo' => 0.0, 'camino' => ['A'], 'visitados' => 1], Dijkstra::caminoMasCorto(self::GRAFO, 'A', 'A'));
        $this->assertNull(Dijkstra::caminoMasCorto(self::GRAFO, 'A', 'Z'));
        $this->assertNull(Dijkstra::caminoMasCorto([], 'A', 'B'));
        $this->assertSame(0.0, Dijkstra::caminoMasCorto(['A' => [['B', 0]]], 'A', 'B')['costo']);

        $this->expectException(InvalidArgumentException::class);
        Dijkstra::caminoMasCorto(['A' => [['B', -1]]], 'A', 'B');
    }

    public function testEmpatesDanSiempreElMismoCamino(): void
    {
        // Dos caminos de costo 2: A→B→D y A→C→D. Se queda el primero encontrado.
        $g = ['A' => [['B', 1], ['C', 1]], 'B' => [['D', 1]], 'C' => [['D', 1]]];

        $r = Dijkstra::caminoMasCorto($g, 'A', 'D');
        $this->assertSame(2.0, $r['costo']);
        $this->assertSame(['A', 'B', 'D'], $r['camino']);
        $this->assertSame($r, Dijkstra::caminoMasCorto($g, 'A', 'D'));
    }

    // ── Verificación contra Bellman-Ford (otro algoritmo, el más simple) ──

    /** Bellman-Ford básico: relaja TODAS las aristas n − 1 veces. */
    private function bellmanFord(array $vecinos, int $n, int $origen): array
    {
        $costo           = array_fill(0, $n, INF);
        $costo[$origen]  = 0.0;
        for ($vuelta = 0; $vuelta < $n - 1; $vuelta++) {
            foreach ($vecinos as $u => $salientes) {
                foreach ($salientes as [$v, $w]) {
                    if ($costo[$u] + $w < $costo[$v]) {
                        $costo[$v] = $costo[$u] + $w;
                    }
                }
            }
        }

        return $costo;
    }

    public function testCoincideConBellmanFordEnGrafosAlAzar(): void
    {
        foreach (range(1, 150) as $semilla) {
            mt_srand($semilla);
            $n       = mt_rand(2, 25);
            $vecinos = [];
            for ($u = 0; $u < $n; $u++) {
                for ($v = 0; $v < $n; $v++) {
                    if ($u !== $v && mt_rand(1, 100) <= 20) {           // ~20 % de las aristas posibles
                        $vecinos[$u][] = [$v, (float) mt_rand(0, 20)];  // pesos chicos: hay empates y ceros
                    }
                }
            }

            $esperado = $this->bellmanFord($vecinos, $n, 0);
            for ($destino = 0; $destino < $n; $destino++) {
                $r = Dijkstra::caminoMasCorto($vecinos, 0, $destino);

                if (is_infinite($esperado[$destino])) {
                    $this->assertNull($r, "semilla {$semilla}: {$destino} es inalcanzable");

                    continue;
                }

                $this->assertSame($esperado[$destino], $r['costo'], "semilla {$semilla}, destino {$destino}");

                // El camino existe arista por arista y su suma es el costo.
                $suma = 0.0;
                for ($k = 0; $k < count($r['camino']) - 1; $k++) {
                    $pesos = array_column(array_filter($vecinos[$r['camino'][$k]] ?? [], static fn ($a) => $a[0] === $r['camino'][$k + 1]), 1);
                    $this->assertNotEmpty($pesos, 'el camino usa una arista inexistente');
                    $suma += min($pesos);
                }
                $this->assertSame($r['costo'], $suma);
            }
        }
    }

    // ── Grafo de calles (OpenStreetMap / Overpass) ─────────

    private const VELOCIDADES = ['primary' => 60, 'residential' => 25];

    /**
     * Calles de prueba:
     *   1 ─ 2 ─ 3   calle residencial de doble sentido
     *   3 → 4       un solo sentido (oneway=yes)
     *   1 ═ 5 ═ 4   avenida (primary) más larga pero más rápida
     */
    private function overpass(): array
    {
        $nodo = static fn (int $id, float $lat, float $lng): array => ['type' => 'node', 'id' => $id, 'lat' => $lat, 'lon' => $lng];

        return ['elements' => [
            $nodo(1, 19.0000, -98.2000),
            $nodo(2, 19.0000, -98.1950),
            $nodo(3, 19.0000, -98.1900),
            $nodo(4, 19.0050, -98.1900),
            $nodo(5, 19.0080, -98.2000),
            ['type' => 'way', 'id' => 10, 'nodes' => [1, 2, 3], 'tags' => ['highway' => 'residential']],
            ['type' => 'way', 'id' => 11, 'nodes' => [3, 4], 'tags' => ['highway' => 'residential', 'oneway' => 'yes']],
            ['type' => 'way', 'id' => 12, 'nodes' => [1, 5, 4], 'tags' => ['highway' => 'primary']],
            ['type' => 'way', 'id' => 13, 'nodes' => [2, 99], 'tags' => ['highway' => 'footway']], // no es para autos: se ignora
        ]];
    }

    public function testArmaElGrafoConSentidos(): void
    {
        $g = GrafoCalles::desdeOverpass($this->overpass(), self::VELOCIDADES);

        $this->assertSame(5, $g->numNodos());
        $this->assertSame(4 + 1 + 4, $g->numAristas()); // 2 tramos × 2 sentidos + 1 + 2 tramos × 2

        $ady = $g->adyacencia('distancia');
        $this->assertContains(4, array_column($ady[3], 0), 'de 3 se puede ir a 4');
        $this->assertNotContains(3, array_column($ady[4] ?? [], 0), 'de 4 no se puede volver a 3 (un solo sentido)');
    }

    public function testElCriterioCambiaElCamino(): void
    {
        $g = GrafoCalles::desdeOverpass($this->overpass(), self::VELOCIDADES);

        // Por distancia conviene la calle residencial (1→2→3→4); por tiempo, la avenida (1→5→4).
        $porDistancia = Dijkstra::caminoMasCorto($g->adyacencia('distancia'), 1, 4);
        $porTiempo    = Dijkstra::caminoMasCorto($g->adyacencia('tiempo'), 1, 4);

        $this->assertSame([1, 2, 3, 4], $porDistancia['camino']);
        $this->assertSame([1, 5, 4], $porTiempo['camino']);

        $m = $g->medir($porTiempo['camino'], 'tiempo');
        $this->assertEqualsWithDelta($porTiempo['costo'], $m['segundos'], 1e-6);
        $this->assertGreaterThan($g->medir($porDistancia['camino'], 'distancia')['metros'], $m['metros']);

        // De 4 a 1 solo se puede por la avenida (la calle 3→4 es de un sentido).
        $this->assertSame([4, 5, 1], Dijkstra::caminoMasCorto($g->adyacencia('distancia'), 4, 1)['camino']);
    }

    public function testSentidosDeOpenStreetMap(): void
    {
        $this->assertSame(1, GrafoCalles::sentido(['oneway' => 'yes']));
        $this->assertSame(-1, GrafoCalles::sentido(['oneway' => '-1']));
        $this->assertSame(0, GrafoCalles::sentido(['oneway' => 'no', 'junction' => 'roundabout']));
        $this->assertSame(1, GrafoCalles::sentido(['junction' => 'roundabout']));
        $this->assertSame(1, GrafoCalles::sentido(['highway' => 'motorway']));
        $this->assertSame(0, GrafoCalles::sentido(['highway' => 'residential']));
    }

    public function testPegaElPuntoAlNodoMasCercano(): void
    {
        $g = GrafoCalles::desdeOverpass($this->overpass(), self::VELOCIDADES);
        [$nodo, $metros] = $g->nodoMasCercano(new Punto(19.0001, -98.1951));

        $this->assertSame(2, $nodo);
        $this->assertLessThan(20, $metros);
    }

    public function testLaConsultaNoMandaLaUbicacionExacta(): void
    {
        $vel = ['primary' => 60, 'residential' => 25, 'track' => 15];

        // Puntos cercanos (3.8 km): todas las calles del rectángulo, incluidos caminos rurales.
        $q = GrafoCalles::consulta(new Punto(19.2453, -97.7846), new Punto(19.2586, -97.7514), $vel);
        $this->assertStringNotContainsString('19.2453', $q);
        $this->assertStringNotContainsString('97.7846', $q);
        $this->assertStringContainsString('"highway"~"^(primary|residential|track)$"', $q);
        $this->assertStringContainsString('(19.21,-97.82,19.29,-97.72)', $q); // margen mínimo de 0.03°
        $this->assertStringNotContainsString('around', $q);

        // Puntos lejanos (≈ 21 km): vías principales en el rectángulo y calles locales alrededor de cada punto.
        $q = GrafoCalles::consulta(new Punto(19.2453, -97.7846), new Punto(19.3670, -97.6103), $vel);
        $this->assertStringContainsString('"highway"~"^(primary)$"', $q);
        $this->assertStringContainsString('"highway"~"^(residential|track)$"', $q);
        $this->assertStringContainsString('around:2800,19.25,-97.78', $q);
        $this->assertStringContainsString('around:2800,19.37,-97.61', $q);
        $this->assertStringNotContainsString('19.3670', $q);
    }

    public function testReintentaUnaVezSiOverpassEstaSaturado(): void
    {
        $a    = new Punto(19.0, -98.2);
        $b    = new Punto(19.005, -98.19);
        $http = new TransporteFalso([['status' => 502, 'cuerpo' => ''], ['status' => 200, 'cuerpo' => json_encode($this->overpass())]]);

        $g = GrafoCalles::descargar($a, $b, $http, 'https://overpass.test/api', self::VELOCIDADES, esperaReintentoSeg: 0);

        $this->assertCount(2, $http->enviados);
        $this->assertSame(5, $g->numNodos());

        // Un error que no es de saturación (400) no se reintenta.
        $http = new TransporteFalso([['status' => 400, 'cuerpo' => '']]);
        try {
            GrafoCalles::descargar($a, $b, $http, 'https://overpass.test/api', self::VELOCIDADES, esperaReintentoSeg: 0);
            $this->fail('Debía lanzar ErrorProveedorRutas');
        } catch (ErrorProveedorRutas) {
            $this->assertCount(1, $http->enviados);
        }
    }

    public function testDescargaUsaCacheYTraduceErrores(): void
    {
        $http = new TransporteFalso([['status' => 200, 'cuerpo' => json_encode($this->overpass())]]);
        $guardadas = [];
        $a = new Punto(19.0, -98.2);
        $b = new Punto(19.005, -98.19);

        $g = GrafoCalles::descargar($a, $b, $http, 'https://overpass.test/api', self::VELOCIDADES, 2000, 30,
            static fn (string $q) => $guardadas[$q] ?? null,
            static function (string $q, array $json) use (&$guardadas): void { $guardadas[$q] = $json; });
        $this->assertSame(5, $g->numNodos());
        $this->assertSame('Fruver/2.0 (planeador de rutas)', $http->enviados[0]['encabezados']['User-Agent']);

        // Segunda vez: sale de la caché, sin volver a llamar al servicio.
        GrafoCalles::descargar($a, $b, $http, 'https://overpass.test/api', self::VELOCIDADES, 2000, 30,
            static fn (string $q) => $guardadas[$q] ?? null);
        $this->assertCount(1, $http->enviados);

        $this->expectException(ErrorProveedorRutas::class);
        GrafoCalles::descargar($a, $b, new TransporteFalso([['status' => 429, 'cuerpo' => ''], ['status' => 429, 'cuerpo' => '']]),
            'https://overpass.test/api', self::VELOCIDADES, esperaReintentoSeg: 0);
    }
}
