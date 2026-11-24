<?php

namespace App\Libraries\Rutas;

use InvalidArgumentException;

/**
 * Árbol de expansión mínima (AEM) de los puntos de reparto.
 *
 * Grafo: el almacén (nodo 0) y cada parada (nodos 1..n), unidos todos con todos. El peso
 * de cada arista es la distancia estimada entre los dos puntos (línea recta × factor de
 * calles, lo mismo que usa el optimizador local). El AEM conecta todos los puntos con la
 * menor suma de pesos posible y sin ciclos.
 *
 *  - prim()       : versión básica de Prim. Empieza en el almacén y en cada vuelta agrega
 *                   la arista más barata que une un nodo del árbol con uno que aún no está.
 *  - kruskal()    : versión básica de Kruskal (aristas de menor a mayor, descartando las
 *                   que cerrarían un ciclo). Sirve para comprobar a Prim: los dos deben dar
 *                   el mismo costo total.
 *  - recorrido()  : orden de visita = recorrido en preorden del árbol desde el almacén (se
 *                   visita un punto y después sus ramas, de la más corta a la más larga).
 *                   Es la forma clásica de convertir un AEM en una ruta; con las distancias
 *                   estimadas su largo nunca pasa del doble del costo del árbol.
 *
 * Una arista es [nodoA, nodoB, peso]. En empates se queda la primera encontrada, así que el
 * resultado siempre es el mismo para los mismos datos.
 */
final class ArbolExpansionMinima
{
    /** Clave del almacén en las aristas que se devuelven con claves de parada. */
    public const ALMACEN = 'almacen';

    /**
     * Prim básico sobre una matriz de pesos simétrica (grafo completo no dirigido).
     *
     * @param list<list<float>> $pesos matriz n×n
     * @return array{aristas: list<array{0:int,1:int,2:float}>, costo: float}
     */
    public static function prim(array $pesos, int $raiz = 0): array
    {
        $n = self::validar($pesos);
        if ($n === 0) {
            return ['aristas' => [], 'costo' => 0.0];
        }
        if ($raiz < 0 || $raiz >= $n) {
            throw new InvalidArgumentException('El nodo inicial no está en el grafo.');
        }

        $enArbol = [$raiz => true];
        $aristas = [];
        $costo   = 0.0;

        // Mientras falten nodos: la arista más barata entre el árbol y el resto.
        while (count($enArbol) < $n) {
            $mejor = null;
            foreach (array_keys($enArbol) as $i) {
                for ($j = 0; $j < $n; $j++) {
                    if (isset($enArbol[$j])) {
                        continue;
                    }
                    if ($mejor === null || $pesos[$i][$j] < $mejor[2]) {
                        $mejor = [$i, $j, (float) $pesos[$i][$j]];
                    }
                }
            }

            $enArbol[$mejor[1]] = true;
            $aristas[]          = $mejor;
            $costo             += $mejor[2];
        }

        return ['aristas' => $aristas, 'costo' => $costo];
    }

    /**
     * Kruskal básico (para verificar a Prim).
     *
     * @param list<list<float>> $pesos matriz n×n
     * @return array{aristas: list<array{0:int,1:int,2:float}>, costo: float}
     */
    public static function kruskal(array $pesos): array
    {
        $n = self::validar($pesos);

        $todas = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $todas[] = [$i, $j, (float) $pesos[$i][$j]];
            }
        }
        usort($todas, static fn (array $a, array $b): int => $a[2] <=> $b[2]);

        // Cada nodo empieza en su propio grupo; dos nodos del mismo grupo ya están conectados.
        $grupo   = $n > 0 ? range(0, $n - 1) : [];
        $aristas = [];
        $costo   = 0.0;

        foreach ($todas as [$i, $j, $peso]) {
            if ($grupo[$i] === $grupo[$j]) {
                continue; // cerraría un ciclo
            }

            $viejo = $grupo[$j];
            foreach ($grupo as $k => $g) {
                if ($g === $viejo) {
                    $grupo[$k] = $grupo[$i];
                }
            }

            $aristas[] = [$i, $j, $peso];
            $costo    += $peso;

            if (count($aristas) === $n - 1) {
                break;
            }
        }

        return ['aristas' => $aristas, 'costo' => $costo];
    }

    /**
     * ¿Las aristas forman un árbol de expansión de los n nodos? (n − 1 aristas y todo conectado)
     *
     * @param list<array{0:int,1:int,2?:float}> $aristas
     */
    public static function esArbolDeExpansion(array $aristas, int $n): bool
    {
        if ($n === 0) {
            return $aristas === [];
        }
        if (count($aristas) !== $n - 1) {
            return false;
        }

        $vecinos = array_fill(0, $n, []);
        foreach ($aristas as $a) {
            [$i, $j] = $a;
            if ($i < 0 || $j < 0 || $i >= $n || $j >= $n || $i === $j) {
                return false;
            }
            $vecinos[$i][] = $j;
            $vecinos[$j][] = $i;
        }

        $visto = [0 => true];
        $pila  = [0];
        while ($pila !== []) {
            foreach ($vecinos[array_pop($pila)] as $v) {
                if (! isset($visto[$v])) {
                    $visto[$v] = true;
                    $pila[]    = $v;
                }
            }
        }

        // n − 1 aristas que alcanzan los n nodos no pueden tener ciclos.
        return count($visto) === $n;
    }

    /**
     * Orden de visita: preorden del árbol desde $raiz, ramas más cortas primero.
     *
     * @param list<array{0:int,1:int,2:float}> $aristas
     * @return list<int> nodos en orden de visita (sin la raíz)
     */
    public static function recorrido(array $aristas, int $raiz = 0): array
    {
        $vecinos = [];
        foreach ($aristas as [$i, $j, $peso]) {
            $vecinos[$i][] = [$j, $peso];
            $vecinos[$j][] = [$i, $peso];
        }

        $orden = [];
        $visto = [$raiz => true];

        $visitar = static function (int $nodo) use (&$visitar, &$vecinos, &$orden, &$visto): void {
            $hijos = $vecinos[$nodo] ?? [];
            usort($hijos, static fn (array $a, array $b): int => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

            foreach ($hijos as [$hijo]) {
                if (isset($visto[$hijo])) {
                    continue;
                }
                $visto[$hijo] = true;
                $orden[]      = $hijo;
                $visitar($hijo);
            }
        };
        $visitar($raiz);

        return $orden;
    }

    /**
     * Matriz de pesos (metros estimados) entre los puntos dados.
     *
     * @param list<Punto> $puntos
     * @return list<list<float>>
     */
    public static function matrizDePesos(array $puntos): array
    {
        $pesos = [];
        foreach (array_values($puntos) as $i => $a) {
            foreach (array_values($puntos) as $j => $b) {
                $pesos[$i][$j] = $i === $j ? 0.0 : $a->distanciaA($b) * OptimizadorLocal::FACTOR_CALLES;
            }
        }

        return $pesos;
    }

    /**
     * Aplica Prim a un almacén y sus paradas.
     *
     * @param list<Parada> $paradas
     * @return array{paradas: list<Parada>, aristas: list<array{0:string,1:string,2:int}>, costo_m: int}
     *         paradas en el orden del recorrido del árbol; aristas con claves de parada
     *         (el almacén es self::ALMACEN) y metros estimados
     */
    public static function deParadas(Punto $origen, array $paradas): array
    {
        $paradas = array_values($paradas);
        $pesos   = self::matrizDePesos([$origen, ...array_map(static fn (Parada $p) => $p->punto, $paradas)]);
        $arbol   = self::prim($pesos, 0);

        $clave = static fn (int $nodo): string => $nodo === 0 ? self::ALMACEN : $paradas[$nodo - 1]->clave;

        return [
            'paradas' => array_map(static fn (int $nodo) => $paradas[$nodo - 1], self::recorrido($arbol['aristas'], 0)),
            'aristas' => array_map(static fn (array $a) => [$clave($a[0]), $clave($a[1]), (int) round($a[2])], $arbol['aristas']),
            'costo_m' => (int) round($arbol['costo']),
        ];
    }

    /** @return int número de nodos; exige una matriz cuadrada */
    private static function validar(array $pesos): int
    {
        $n = count($pesos);
        foreach (array_values($pesos) as $fila) {
            if (! is_array($fila) || count($fila) !== $n) {
                throw new InvalidArgumentException('La matriz de pesos debe ser cuadrada.');
            }
        }

        return $n;
    }
}
