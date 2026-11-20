<?php

namespace App\Libraries\Rutas;

use InvalidArgumentException;

/**
 * Algoritmo de Dijkstra en su versión básica: camino de menor costo entre dos nodos de un
 * grafo dirigido con pesos no negativos.
 *
 *  1. El origen empieza con costo 0; los demás, desconocidos (infinito).
 *  2. Se toma el nodo pendiente con MENOR costo acumulado y su costo queda definitivo.
 *  3. Se revisan sus vecinos: si llegar a uno pasando por él es más barato, se anota el
 *     nuevo costo y desde dónde se llegó (anterior).
 *  4. Se repite hasta que el destino queda definitivo (o ya no hay pendientes: no hay camino).
 *  5. El camino se reconstruye siguiendo «anterior» desde el destino hasta el origen.
 *
 * Para elegir el siguiente nodo se recorre la lista de pendientes (sin montículo ni cola de
 * prioridad): es la forma más sencilla. En empates se queda el primero encontrado, por eso
 * el resultado siempre es el mismo para los mismos datos.
 */
final class Dijkstra
{
    /**
     * @param array<int|string, list<array{0:int|string, 1:float|int}>> $vecinos lista de adyacencia:
     *        nodo => [[vecino, peso], ...]  (una arista por cada sentido permitido)
     *
     * @return array{costo: float, camino: list<int|string>, visitados: int}|null null si no hay camino
     */
    public static function caminoMasCorto(array $vecinos, int|string $origen, int|string $destino): ?array
    {
        $costo      = [$origen => 0.0];    // mejor costo conocido hasta cada nodo
        $anterior   = [];                  // nodo desde el que se llegó con ese costo
        $definitivo = [];                  // nodos cuyo costo ya no puede bajar
        $pendientes = [$origen => true];   // nodos con costo conocido pero aún no definitivo

        while ($pendientes !== []) {
            // Paso 2: el pendiente con menor costo acumulado.
            $actual = null;
            foreach ($pendientes as $nodo => $_) {
                if ($actual === null || $costo[$nodo] < $costo[$actual]) {
                    $actual = $nodo;
                }
            }

            unset($pendientes[$actual]);
            $definitivo[$actual] = true;

            if ((string) $actual === (string) $destino) {
                break;
            }

            // Paso 3: revisar los vecinos.
            foreach ($vecinos[$actual] ?? [] as [$vecino, $peso]) {
                if ($peso < 0) {
                    throw new InvalidArgumentException('Dijkstra no admite pesos negativos.');
                }
                if (isset($definitivo[$vecino])) {
                    continue;
                }

                $nuevo = $costo[$actual] + $peso;
                if (! isset($costo[$vecino]) || $nuevo < $costo[$vecino]) {
                    $costo[$vecino]      = $nuevo;
                    $anterior[$vecino]   = $actual;
                    $pendientes[$vecino] = true;
                }
            }
        }

        if (! isset($definitivo[$destino])) {
            return null;
        }

        // Paso 5: reconstruir el camino hacia atrás.
        $camino = [$destino];
        $nodo   = $destino;
        while (isset($anterior[$nodo])) {
            $nodo     = $anterior[$nodo];
            $camino[] = $nodo;
        }

        return [
            'costo'     => (float) $costo[$destino],
            'camino'    => array_reverse($camino),
            'visitados' => count($definitivo),
        ];
    }
}
