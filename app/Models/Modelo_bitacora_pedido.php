<?php

namespace App\Models;

use Throwable;

/**
 * Historial de cada pedido: quién lo capturó y quién lo modificó (tabla `pedido_bitacora`).
 * Solo lectura: las filas las escriben los triggers de la migración 2026-11-10-110000.
 */
class Modelo_bitacora_pedido extends PdoModelo
{
    protected string $tabla = 'pedido_bitacora';

    public const ACCIONES = [
        'captura'      => 'Capturó',
        'modificacion' => 'Modificó',
        'productos'    => 'Productos',
        'estatus'      => 'Estatus',
        'eliminacion'  => 'Eliminó',
    ];

    private static ?bool $hayTabla = null;

    public function tablaExiste(): bool
    {
        if (self::$hayTabla === null) {
            try {
                $this->filas('SELECT id FROM pedido_bitacora LIMIT 0');
                self::$hayTabla = true;
            } catch (Throwable) {
                self::$hayTabla = false;
            }
        }

        return self::$hayTabla;
    }

    /**
     * Historial de varios pedidos en una sola consulta, del más viejo al más reciente.
     *
     * @param list<int|string> $idsPedido
     *
     * @return array<int, list<array<string,mixed>>> id_pedido => movimientos
     */
    public function dePedidos(array $idsPedido): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsPedido), static fn (int $i): bool => $i > 0)));

        if ($ids === [] || ! $this->tablaExiste()) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $salida = [];

        foreach ($this->filas(
            "SELECT id_pedido, accion, detalle, usuario, fecha
               FROM pedido_bitacora
              WHERE id_pedido IN ({$marcas})
           ORDER BY id_pedido, id",
            $ids
        ) as $fila) {
            $salida[(int) $fila['id_pedido']][] = $fila;
        }

        return $salida;
    }
}
