<?php

namespace App\Models;

class Modelo_estatus extends PdoModelo
{
    protected string $tabla         = 'estatus';
    protected string $llavePrimaria = 'id';

    // `fecha` es opcional: si no se manda, MySQL usa current_timestamp().
    protected array $camposPermitidos = ['estado', 'fecha', 'id_pedido'];

    /**
     * Último estatus registrado de un pedido (orden de captura = id).
     *
     * Es la ÚNICA forma de leer el estado actual: antes unas partes ordenaban por `fecha`
     * (editable a mano) y otras por `id`, y podían no coincidir. Panel, estatus, entregas
     * y rutas usan ahora este método.
     */
    public function ultimo(int|string $idPedido): ?array
    {
        return $this->fila(
            'SELECT * FROM estatus WHERE id_pedido = ? ORDER BY id DESC LIMIT 1',
            [$idPedido]
        );
    }

    /** @deprecated Usa ultimo(). Se conserva por compatibilidad. */
    public function ultimoPorId(int|string $idPedido): ?array
    {
        return $this->ultimo($idPedido);
    }

    /** @deprecated Usa ultimo(). Antes ordenaba por fecha; ahora devuelve lo mismo que ultimo(). */
    public function ultimoPorFecha(int|string $idPedido): ?array
    {
        return $this->ultimo($idPedido);
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
