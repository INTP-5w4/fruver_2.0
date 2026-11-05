<?php

namespace App\Libraries\Rutas;

/**
 * Optimizador de respaldo SIN Google (sin costo, sin red).
 *
 * Se usa cuando no hay llave configurada, cuando Google falla o se agotó la cuota.
 * Calcula distancias en línea recta (Haversine) multiplicadas por un factor de calles
 * y estima tiempos con una velocidad media por vehículo. El orden se obtiene con
 * "vecino más cercano" y se mejora con 2-opt. Es una APROXIMACIÓN: la pantalla lo avisa.
 */
final class OptimizadorLocal implements OptimizadorInterface
{
    /** Las calles no son línea recta: ~35 % más de recorrido en ciudad. */
    public const FACTOR_CALLES = 1.35;

    /** @param array<string,int|float> $velocidades km/h por vehículo */
    public function __construct(private readonly array $velocidades = ['moto' => 25, 'auto' => 22, 'camioneta' => 20, 'bicicleta' => 13])
    {
    }

    public function calcular(Punto $origen, array $paradas, OpcionesRuta $op): ResultadoRuta
    {
        $paradas = array_values($paradas);
        $n       = count($paradas);

        if ($n === 0) {
            return new ResultadoRuta($op->estrategia, 'local', [], [], 0, 0, null, $op->regresar);
        }

        // Nodo 0 = origen; nodos 1..n = paradas; nodo n+1 = punto de regreso.
        $regreso = $n + 1;
        $puntos  = [$origen, ...array_map(static fn (Parada $p) => $p->punto, $paradas), $op->regresoA ?? $origen];
        $dist   = [];
        foreach ($puntos as $i => $a) {
            foreach ($puntos as $j => $b) {
                $dist[$i][$j] = $i === $j ? 0.0 : $a->distanciaA($b) * self::FACTOR_CALLES;
            }
        }

        $secuencia = range(1, $n);
        if ($op->optimizaOrden() && $n > 1) {
            $secuencia = $this->vecinoMasCercano($dist, $n);
            $secuencia = $this->dosOpt($secuencia, $dist, $op->regresar ? $regreso : null);
        }

        $velocidad = max(1.0, (float) ($this->velocidades[$op->vehiculo] ?? 20)); // km/h
        $mps       = $velocidad * 1000 / 3600;

        $tramos = [];
        $previo = 0;
        foreach ($secuencia as $nodo) {
            $tramos[] = $this->tramo($dist[$previo][$nodo], $mps);
            $previo   = $nodo;
        }
        if ($op->regresar) {
            $tramos[] = $this->tramo($dist[$previo][$regreso], $mps);
        }

        return new ResultadoRuta(
            $op->estrategia,
            'local',
            array_map(static fn (int $nodo) => $paradas[$nodo - 1]->clave, $secuencia),
            $tramos,
            array_sum(array_column($tramos, 'distancia_m')),
            array_sum(array_column($tramos, 'duracion_s')),
            null,
            $op->regresar,
            'Ruta aproximada: calculada sin Google Maps (distancias en línea recta, sin tráfico).',
        );
    }

    /** @return array{distancia_m:int,duracion_s:int} */
    private function tramo(float $metros, float $mps): array
    {
        return ['distancia_m' => (int) round($metros), 'duracion_s' => (int) round($metros / $mps)];
    }

    /** @return list<int> */
    private function vecinoMasCercano(array $dist, int $n): array
    {
        $pendientes = array_fill_keys(range(1, $n), true);
        $actual     = 0;
        $secuencia  = [];

        while ($pendientes !== []) {
            $mejor = null;
            foreach (array_keys($pendientes) as $j) {
                if ($mejor === null || $dist[$actual][$j] < $dist[$actual][$mejor]) {
                    $mejor = $j;
                }
            }
            $secuencia[] = $mejor;
            unset($pendientes[$mejor]);
            $actual = $mejor;
        }

        return $secuencia;
    }

    /**
     * Mejora 2-opt: invierte tramos mientras se reduzca la distancia total.
     *
     * @param list<int> $s
     * @return list<int>
     */
    private function dosOpt(array $s, array $dist, ?int $regreso): array
    {
        $mejora = true;
        $vueltas = 0;

        while ($mejora && $vueltas++ < 100) {
            $mejora = false;
            $total  = $this->longitud($s, $dist, $regreso);

            for ($i = 0; $i < count($s) - 1; $i++) {
                for ($k = $i + 1; $k < count($s); $k++) {
                    $candidato = [...array_slice($s, 0, $i), ...array_reverse(array_slice($s, $i, $k - $i + 1)), ...array_slice($s, $k + 1)];
                    $nueva     = $this->longitud($candidato, $dist, $regreso);

                    if ($nueva + 0.01 < $total) {
                        $s      = $candidato;
                        $total  = $nueva;
                        $mejora = true;
                    }
                }
            }
        }

        return $s;
    }

    /** Longitud del recorrido; si $regreso no es null, incluye el tramo final a ese nodo. */
    private function longitud(array $s, array $dist, ?int $regreso): float
    {
        $total  = 0.0;
        $previo = 0;
        foreach ($s as $nodo) {
            $total += $dist[$previo][$nodo];
            $previo = $nodo;
        }

        return $regreso !== null ? $total + $dist[$previo][$regreso] : $total;
    }
}
