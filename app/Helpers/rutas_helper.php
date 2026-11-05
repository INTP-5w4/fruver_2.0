<?php

/**
 * Ayudantes de vista para el módulo de rutas de reparto.
 */

use App\Models\Modelo_ruta;

if (! function_exists('rutas_listas')) {
    /** ¿Ya se corrió la migración de rutas? (las vistas muestran los campos nuevos solo entonces) */
    function rutas_listas(): bool
    {
        static $listo = null;

        if ($listo === null) {
            try {
                $listo = (new Modelo_ruta())->esquemaListo();
            } catch (Throwable) {
                $listo = false;
            }
        }

        return $listo;
    }
}

if (! function_exists('rutas_duracion')) {
    /** 3720 → "1 h 2 min" */
    function rutas_duracion(int|string|null $segundos): string
    {
        $m = (int) round(((int) $segundos) / 60);

        return $m >= 60 ? intdiv($m, 60) . ' h ' . ($m % 60) . ' min' : $m . ' min';
    }
}

if (! function_exists('rutas_km')) {
    /** 12345 → "12.3 km" */
    function rutas_km(int|string|null $metros): string
    {
        return number_format(((int) $metros) / 1000, 1) . ' km';
    }
}

if (! function_exists('rutas_hora')) {
    /** "2026-11-06 11:40:00" → "11:40" */
    function rutas_hora(?string $fechaHora): string
    {
        return $fechaHora ? date('H:i', strtotime($fechaHora)) : '—';
    }
}
