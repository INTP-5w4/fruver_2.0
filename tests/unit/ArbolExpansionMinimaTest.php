<?php

use App\Libraries\Rutas\ArbolExpansionMinima as Arbol;
use App\Libraries\Rutas\OpcionesRuta;
use App\Libraries\Rutas\Parada;
use App\Libraries\Rutas\PlaneadorRutas;
use App\Libraries\Rutas\Punto;
use App\Libraries\Rutas\ResultadoRuta;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Árbol de expansión mínima (Prim) y su verificación con Kruskal y con fuerza bruta.
 *
 * @internal
 */
final class ArbolExpansionMinimaTest extends CIUnitTestCase
{
    /** Grafo de 5 nodos con su AEM conocido (cálculo a mano): 0-1 (2), 1-2 (3), 1-4 (5), 0-3 (6) = 16. */
    private const GRAFO = [
        [0, 2, 9, 6, 9],
        [2, 0, 3, 8, 5],
        [9, 3, 0, 9, 7],
        [6, 8, 9, 0, 9],
        [9, 5, 7, 9, 0],
    ];

    /** Matriz simétrica con pesos al azar (semilla fija para que la prueba sea repetible). */
    private function matrizAlAzar(int $n, int $semilla): array
    {
        mt_srand($semilla);
        $m = array_fill(0, $n, array_fill(0, $n, 0.0));
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $m[$i][$j] = $m[$j][$i] = (float) mt_rand(1, 50); // pesos chicos: hay empates a propósito
            }
        }

        return $m;
    }

    /** Fuerza bruta: el menor costo entre TODOS los árboles de expansión (solo grafos chicos). */
    private function minimoPorFuerzaBruta(array $pesos): float
    {
        $n     = count($pesos);
        $todas = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $todas[] = [$i, $j, $pesos[$i][$j]];
            }
        }

        $mejor = INF;
        $m     = count($todas);
        for ($mascara = 0; $mascara < (1 << $m); $mascara++) {
            $elegidas = [];
            for ($k = 0; $k < $m; $k++) {
                if ($mascara & (1 << $k)) {
                    $elegidas[] = $todas[$k];
                }
            }
            if (count($elegidas) === $n - 1 && Arbol::esArbolDeExpansion($elegidas, $n)) {
                $mejor = min($mejor, array_sum(array_column($elegidas, 2)));
            }
        }

        return $mejor;
    }

    // ── Prim ───────────────────────────────────────────────

    public function testPrimEnElGrafoDeEjemplo(): void
    {
        $r = Arbol::prim(self::GRAFO);

        $this->assertSame(16.0, $r['costo']);
        $this->assertSame([[0, 1, 2.0], [1, 2, 3.0], [1, 4, 5.0], [0, 3, 6.0]], $r['aristas']);
        $this->assertTrue(Arbol::esArbolDeExpansion($r['aristas'], 5));
    }

    public function testPrimDaElMismoCostoDesdeCualquierNodo(): void
    {
        for ($raiz = 0; $raiz < 5; $raiz++) {
            $this->assertSame(16.0, Arbol::prim(self::GRAFO, $raiz)['costo'], "Desde el nodo {$raiz}");
        }
    }

    public function testCasosLimite(): void
    {
        $this->assertSame(['aristas' => [], 'costo' => 0.0], Arbol::prim([]));
        $this->assertSame(['aristas' => [], 'costo' => 0.0], Arbol::prim([[0]]));
        $this->assertSame([[0, 1, 4.0]], Arbol::prim([[0, 4], [4, 0]])['aristas']);

        $this->expectException(InvalidArgumentException::class);
        Arbol::prim([[0, 1], [1]]);
    }

    // ── Verificación: Prim contra Kruskal y contra fuerza bruta ─────

    public function testKruskalCoincideConElGrafoDeEjemplo(): void
    {
        $r = Arbol::kruskal(self::GRAFO);

        $this->assertSame(16.0, $r['costo']);
        $this->assertTrue(Arbol::esArbolDeExpansion($r['aristas'], 5));
    }

    public function testPrimYKruskalDanElMismoCostoEnGrafosAlAzar(): void
    {
        foreach (range(2, 15) as $n) {
            foreach (range(1, 20) as $semilla) {
                $m     = $this->matrizAlAzar($n, $n * 100 + $semilla);
                $prim  = Arbol::prim($m);
                $krusk = Arbol::kruskal($m);

                $this->assertTrue(Arbol::esArbolDeExpansion($prim['aristas'], $n), "Prim n={$n}");
                $this->assertTrue(Arbol::esArbolDeExpansion($krusk['aristas'], $n), "Kruskal n={$n}");
                $this->assertEqualsWithDelta($krusk['costo'], $prim['costo'], 1e-9, "n={$n}, semilla={$semilla}");
            }
        }
    }

    public function testPrimEsElMinimoPorFuerzaBruta(): void
    {
        // 5 nodos = 10 aristas posibles: se revisan los 1,024 subconjuntos.
        foreach (range(1, 15) as $semilla) {
            $m = $this->matrizAlAzar(5, $semilla);
            $this->assertEqualsWithDelta($this->minimoPorFuerzaBruta($m), Arbol::prim($m)['costo'], 1e-9, "semilla={$semilla}");
        }
    }

    public function testDetectaLoQueNoEsArbol(): void
    {
        $this->assertFalse(Arbol::esArbolDeExpansion([[0, 1], [1, 2]], 4), 'faltan aristas');
        $this->assertFalse(Arbol::esArbolDeExpansion([[0, 1], [1, 2], [2, 0]], 4), 'ciclo y un nodo suelto');
        $this->assertFalse(Arbol::esArbolDeExpansion([[0, 1], [0, 1], [2, 3]], 4), 'repetida y desconectado');
        $this->assertTrue(Arbol::esArbolDeExpansion([[0, 1], [1, 2], [1, 3]], 4));
    }

    // ── Recorrido del árbol ─────────────────────────────────

    public function testRecorridoEnPreordenConRamasCortasPrimero(): void
    {
        // Árbol del grafo de ejemplo: 0 → {1 (2), 3 (6)}, 1 → {2 (3), 4 (5)}
        $orden = Arbol::recorrido(Arbol::prim(self::GRAFO)['aristas'], 0);

        $this->assertSame([1, 2, 4, 3], $orden);
    }

    public function testPuntosEnLineaSeVisitanEnOrden(): void
    {
        $almacen = new Punto(19.0433, -98.1981);
        $paradas = [
            new Parada('p3', new Punto(19.0733, -98.1981)),
            new Parada('p1', new Punto(19.0533, -98.1981)),
            new Parada('p4', new Punto(19.0833, -98.1981)),
            new Parada('p2', new Punto(19.0633, -98.1981)),
        ];

        $r = Arbol::deParadas($almacen, $paradas);

        $this->assertSame(['p1', 'p2', 'p3', 'p4'], array_map(static fn (Parada $p) => $p->clave, $r['paradas']));
        $this->assertSame(['almacen', 'p1'], array_slice($r['aristas'][0], 0, 2));
        $this->assertCount(4, $r['aristas']);
    }

    public function testEjemploDeSeisMunicipios(): void
    {
        // Almacén en Rafael Lara Grajales y seis entregas (mismos puntos que el seeder RutasTest).
        $almacen = new Punto(19.2453, -97.7846);
        $paradas = [
            new Parada('SanJoseChiapa', new Punto(19.2087, -97.7612)),
            new Parada('Oriental', new Punto(19.3670, -97.6103)),
            new Parada('Nopalucan', new Punto(19.2063, -97.8049)),
            new Parada('SanSalvadorElSeco', new Punto(19.1343, -97.6406)),
            new Parada('Mazapiltepec', new Punto(19.2203, -97.7210)),
            new Parada('Soltepec', new Punto(19.2586, -97.7514)),
        ];

        $r = Arbol::deParadas($almacen, $paradas);

        $this->assertSame(66690, $r['costo_m']);
        $this->assertSame(
            ['Soltepec', 'Oriental', 'SanJoseChiapa', 'Mazapiltepec', 'SanSalvadorElSeco', 'Nopalucan'],
            array_map(static fn (Parada $p) => $p->clave, $r['paradas'])
        );

        // Kruskal llega al mismo costo con la misma matriz.
        $pesos = Arbol::matrizDePesos([$almacen, ...array_map(static fn (Parada $p) => $p->punto, $paradas)]);
        $this->assertSame(66690, (int) round(Arbol::kruskal($pesos)['costo']));
    }

    // ── Integración con el planeador ────────────────────────

    public function testPlaneadorSinServicioOfreceLaOpcionArbol(): void
    {
        $cfg           = new \Config\Rutas();
        $cfg->proveedor = 'local';
        $almacen       = new Punto(19.0433, -98.1981);
        $paradas       = [
            new Parada('p1', new Punto(19.0533, -98.1981)),
            new Parada('p2', new Punto(19.0633, -98.1881)),
            new Parada('p3', new Punto(19.0333, -98.2081)),
        ];

        $op = PlaneadorRutas::crear($cfg)->opciones($almacen, $paradas, new OpcionesRuta('rapida', 'moto', new DateTimeImmutable('+1 day 09:00')));

        $this->assertSame(['aproximada', 'arbol'], array_keys($op));
        $arbol = $op['arbol'];
        $this->assertSame('arbol', $arbol->estrategia);
        $this->assertCount(3, $arbol->arbol['aristas']);
        $this->assertGreaterThan(0, $arbol->arbol['costo_m']);
        $this->assertCount(4, $arbol->tramos, '3 paradas + regreso');

        // El árbol sobrevive al guardarse en sesión.
        $this->assertSame($arbol->arbol, ResultadoRuta::desdeArreglo($arbol->aArreglo())->arbol);
    }

    public function testUnaSolaParadaNoOfreceArbol(): void
    {
        $cfg            = new \Config\Rutas();
        $cfg->proveedor = 'local';

        $op = PlaneadorRutas::crear($cfg)->opciones(
            new Punto(19.0433, -98.1981),
            [new Parada('p1', new Punto(19.0533, -98.1981))],
            new OpcionesRuta('rapida', 'moto', new DateTimeImmutable('+1 day 09:00'))
        );

        $this->assertSame(['aproximada'], array_keys($op));
    }
}
