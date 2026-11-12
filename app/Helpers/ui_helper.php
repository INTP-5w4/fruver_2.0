<?php

/**
 * Piezas de interfaz compartidas por las vistas del panel (estilos en public/estilos/base.css).
 */

if (! function_exists('estado_pedido_texto')) {
    /** Nombre legible de un estado de la tabla `estatus` (pedido_en_transito → "En tránsito"). */
    function estado_pedido_texto(?string $estado): string
    {
        return match ((string) $estado) {
            'pedido_pendiente'   => 'Pendiente',
            'pedido_realizado'   => 'Realizado',
            'pedido_confirmado'  => 'Confirmado',
            'pedido_en_transito' => 'En tránsito',
            'pedido_entregado'   => 'Entregado',
            'pedido_a_credito'   => 'A crédito',
            'pedido_pagado'      => 'Pagado',
            'pedido_cancelado'   => 'Cancelado',
            default              => ucfirst(str_replace(['pedido_', '_'], ['', ' '], (string) $estado)) ?: 'Sin estado',
        };
    }
}

if (! function_exists('estado_pedido')) {
    /** Insignia de color del estado de un pedido. */
    function estado_pedido(?string $estado): string
    {
        $clave = preg_replace('/[^a-z_]/', '', str_replace('pedido_', '', (string) $estado)) ?: 'pendiente';

        return '<span class="est est-' . $clave . '">' . esc(estado_pedido_texto($estado)) . '</span>';
    }
}

if (! function_exists('estado_entrega')) {
    /** Insignia de color del estado de una entrega (asignada, en_ruta, entregada, fallida, cancelada). */
    function estado_entrega(?string $estado): string
    {
        $textos = [
            'asignada'  => 'Asignada',
            'en_ruta'   => 'En ruta',
            'entregada' => 'Entregada',
            'fallida'   => 'No entregada',
            'cancelada' => 'Cancelada',
        ];
        $clave = preg_replace('/[^a-z_]/', '', (string) $estado) ?: 'cancelada';

        return '<span class="est est-' . $clave . '">' . esc($textos[$clave] ?? ucfirst(str_replace('_', ' ', $clave))) . '</span>';
    }
}

if (! function_exists('es_tecnico')) {
    /**
     * ¿La persona en sesión es superadmin o developer? Solo ellos ven los detalles técnicos
     * (archivos, comandos, llaves, rutas del servidor). El resto del personal ve un mensaje
     * sencillo que le pide avisar al administrador del sistema.
     */
    function es_tecnico(): bool
    {
        try {
            $usuario = auth()->user();
        } catch (Throwable) {
            return false;
        }

        return $usuario !== null && $usuario->inGroup('superadmin', 'developer');
    }
}

if (! function_exists('mensaje_tecnico')) {
    /** El texto técnico para superadmin/developer; el sencillo para todos los demás. */
    function mensaje_tecnico(string $tecnico, string $sencillo): string
    {
        return es_tecnico() ? $tecnico : $sencillo;
    }
}
