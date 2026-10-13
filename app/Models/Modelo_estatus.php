<?php

namespace App\Models;

class Modelo_estatus extends PdoModelo
{
    protected string $tabla         = 'estatus';
    protected string $llavePrimaria = 'id';

    // `fecha` es opcional: si no se manda, MySQL usa current_timestamp().
    protected array $camposPermitidos = ['estado', 'fecha', 'id_pedido'];

    /** Último estatus de un pedido, ordenando por id (como lo hacía Estatus::guarda_estatus). */
    public function ultimoPorId(int|string $idPedido): ?array
    {
        return $this->fila(
            'SELECT * FROM estatus WHERE id_pedido = ? ORDER BY id DESC LIMIT 1',
            [$idPedido]
        );
    }

    /** Último estatus de un pedido, ordenando por fecha (como lo hacía P_pedidos). */
    public function ultimoPorFecha(int|string $idPedido): ?array
    {
        return $this->fila(
            'SELECT * FROM estatus WHERE id_pedido = ? ORDER BY fecha DESC, id DESC LIMIT 1',
            [$idPedido]
        );
    }

    /** Registro inmediato anterior (id menor) del mismo pedido. */
    public function anterior(int|string $idPedido, int|string $idActual): ?array
    {
        return $this->fila(
            'SELECT * FROM estatus WHERE id_pedido = ? AND id < ? ORDER BY id DESC LIMIT 1',
            [$idPedido, $idActual]
        );
    }

    /** Registro inmediato posterior (id mayor) del mismo pedido. */
    public function posterior(int|string $idPedido, int|string $idActual): ?array
    {
        return $this->fila(
            'SELECT * FROM estatus WHERE id_pedido = ? AND id > ? ORDER BY id ASC LIMIT 1',
            [$idPedido, $idActual]
        );
    }

    public function eliminarPorPedido(int|string $idPedido): int
    {
        return $this->eliminarPor('id_pedido', $idPedido);
    }
}
