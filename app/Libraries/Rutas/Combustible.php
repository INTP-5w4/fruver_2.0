<?php

namespace App\Libraries\Rutas;

/**
 * Consumo y costo de combustible de una ruta.
 *
 *  litros = km de la ruta / rendimiento del vehículo (km por litro)
 *  costo  = litros × precio por litro del combustible que usa
 *
 * El rendimiento se captura en la ficha del repartidor (es como se conoce: "mi moto rinde
 * 35 km por litro"). Si no lo tiene, se usa uno típico de su tipo de vehículo y la pantalla
 * lo marca como estimado. La bicicleta no gasta combustible.
 *
 * Los precios son aproximados y los actualiza el personal en Rutas → Combustible (se
 * guardan con el paquete Settings, tabla `settings`); mientras no lo haga se usan los de
 * Config\Rutas::$preciosCombustible.
 */
final class Combustible
{
    /** Tipos de combustible (columna repartidor.combustible). */
    public const TIPOS = [
        'magna'   => 'Gasolina verde (Magna / regular)',
        'premium' => 'Gasolina roja (Premium)',
        'diesel'  => 'Diésel',
    ];

    public const CORTOS = ['magna' => 'verde', 'premium' => 'roja', 'diesel' => 'diésel'];

    /** Rendimiento típico (km/L) cuando la ficha no lo tiene. */
    public const RENDIMIENTO_TIPICO = ['moto' => 30.0, 'auto' => 12.0, 'camioneta' => 8.0, 'bicicleta' => 0.0];

    /** Combustible típico por vehículo cuando la ficha no lo tiene. */
    public const COMBUSTIBLE_TIPICO = ['moto' => 'magna', 'auto' => 'magna', 'camioneta' => 'magna', 'bicicleta' => null];

    /**
     * Precio por litro vigente de cada combustible.
     *
     * @return array<string,float>
     */
    public static function precios(): array
    {
        $base = config('Rutas')->preciosCombustible;

        try {
            $guardados = setting('Rutas.preciosCombustible');
        } catch (\Throwable) {
            $guardados = null; // sin tabla settings: se usan los de la configuración
        }

        $precios = [];
        foreach (array_keys(self::TIPOS) as $tipo) {
            $valor          = is_array($guardados) && isset($guardados[$tipo]) ? $guardados[$tipo] : ($base[$tipo] ?? 0);
            $precios[$tipo] = max(0.0, round((float) $valor, 2));
        }

        return $precios;
    }

    /** Fecha (Y-m-d) en que el personal actualizó los precios; null = valores de fábrica. */
    public static function actualizados(): ?string
    {
        try {
            $f = setting('Rutas.preciosActualizados');
        } catch (\Throwable) {
            return null;
        }

        return is_string($f) && $f !== '' ? $f : null;
    }

    /** @param array<string,float> $precios */
    public static function guardarPrecios(array $precios): void
    {
        $limpios = [];
        foreach (array_keys(self::TIPOS) as $tipo) {
            $limpios[$tipo] = round(max(0.0, (float) ($precios[$tipo] ?? 0)), 2);
        }

        setting('Rutas.preciosCombustible', $limpios);
        setting('Rutas.preciosActualizados', date('Y-m-d'));
    }

    /**
     * Litros y costo de recorrer $distanciaM con el vehículo de un repartidor.
     *
     * @param array<string,mixed> $repartidor fila de `repartidor` (vehiculo, rendimiento_km_l, combustible)
     *
     * @return array{litros:float,costo:float,precio:float,combustible:string,rendimiento:float,estimado:bool}|null
     *         null si el vehículo no usa combustible (bicicleta)
     */
    public static function calcular(int $distanciaM, array $repartidor): ?array
    {
        $vehiculo    = (string) ($repartidor['vehiculo'] ?? 'moto');
        $rendimiento = (float) ($repartidor['rendimiento_km_l'] ?? 0);
        $estimado    = $rendimiento <= 0;

        if ($estimado) {
            $rendimiento = self::RENDIMIENTO_TIPICO[$vehiculo] ?? 0.0;
        }

        $combustible = (string) ($repartidor['combustible'] ?? '');
        if (! isset(self::TIPOS[$combustible])) {
            $combustible = (string) (self::COMBUSTIBLE_TIPICO[$vehiculo] ?? '');
        }

        if ($rendimiento <= 0 || $combustible === '') {
            return null;
        }

        $precio = self::precios()[$combustible] ?? 0.0;
        $litros = max(0, $distanciaM) / 1000 / $rendimiento;

        return [
            'litros'      => round($litros, 2),
            'costo'       => round($litros * $precio, 2),
            'precio'      => $precio,
            'combustible' => $combustible,
            'rendimiento' => $rendimiento,
            'estimado'    => $estimado,
        ];
    }

    /** "2.35 L de verde ≈ $55.70" */
    public static function texto(?float $litros, ?float $costo, ?string $combustible = null): string
    {
        if ($litros === null || $costo === null) {
            return '—';
        }

        $tipo = $combustible !== null && isset(self::CORTOS[$combustible]) ? ' de ' . self::CORTOS[$combustible] : '';

        return number_format($litros, 2) . ' L' . $tipo . ' ≈ $' . number_format($costo, 2);
    }
}
