<?php

namespace App\Models;

use DomainException;

class Modelo_productopedidos extends PdoModelo
{
    protected string $tabla         = 'producto_pedido';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = [
        'cant', 'precio_venta', 'unidad_venta', 'total', 'id_pedido', 'id_producto',
    ];

    /**
     * Unidades de venta aceptadas. Las claves son lo que puede llegar (entrada.u_venta)
     * y el valor es lo que se guarda en producto_pedido.unidad_venta.
     */
    public const UNIDADES = [
        'Kilogramo' => 'Kilogramo',
        'Litro'     => 'Litro',
        'Caja'      => 'Caja',
        'Pieza'     => 'Pieza',
        'Domo'      => 'Domo',
        'Ramo'      => 'Ramos',
        'Ramos'     => 'Ramos',
    ];

    /** Convierte una unidad a su valor de almacenamiento; lanza DomainException si no es válida. */
    public static function normalizarUnidad(string $unidad): string
    {
        $unidad = trim($unidad);

        if (! isset(self::UNIDADES[$unidad])) {
            throw new DomainException('Unidad de venta no válida.');
        }

        return self::UNIDADES[$unidad];
    }

    /**
     * Valida y normaliza las líneas de un pedido que llegan del navegador (JSON).
     * Devuelve líneas limpias con: id (opcional), id_producto, cant, precio_venta,
     * unidad_venta y total (recalculado aquí: no se confía en el total del cliente).
     *
     * @return list<array<string,mixed>>
     */
    public static function limpiarItems(mixed $crudos): array
    {
        if (! is_array($crudos) || $crudos === []) {
            throw new DomainException('El carrito está vacío.');
        }

        if (count($crudos) > 100) {
            throw new DomainException('Demasiados productos en un mismo pedido.');
        }

        $limpios = [];

        foreach ($crudos as $item) {
            if (! is_array($item)) {
                throw new DomainException('Datos del carrito no válidos.');
            }

            $idProducto = $item['id_producto'] ?? null;
            $cant       = $item['cant'] ?? null;
            $precio     = $item['p_venta'] ?? ($item['precio_venta'] ?? null);

            if (! is_numeric($idProducto) || (int) $idProducto < 1
                || ! is_numeric($cant)    || (float) $cant <= 0 || (float) $cant > 99999999
                || ! is_numeric($precio)  || (float) $precio < 0 || (float) $precio > 99999999) {
                throw new DomainException('Hay datos no válidos en el carrito (producto, cantidad o precio).');
            }

            $limpios[] = [
                'id'           => isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null,
                'id_producto'  => (int) $idProducto,
                'cant'         => round((float) $cant, 2),
                'precio_venta' => round((float) $precio, 2),
                'unidad_venta' => self::normalizarUnidad((string) ($item['u_venta'] ?? $item['unidad_venta'] ?? '')),
                'total'        => round((float) $cant * (float) $precio, 2),
            ];
        }

        return $limpios;
    }

    /** @return list<array<string,mixed>> */
    public function itemsDePedido(int|string $idPedido): array
    {
        return $this->buscarPor('id_pedido', $idPedido, 'id ASC');
    }

    public function eliminarPorPedido(int|string $idPedido): int
    {
        return $this->eliminarPor('id_pedido', $idPedido);
    }

    /** Actualiza una línea SOLO si pertenece a ese pedido (evita tocar líneas de otros pedidos). */
    public function actualizarDePedido(int|string $id, int|string $idPedido, array $datos): bool
    {
        $datos = array_intersect_key($datos, array_flip($this->camposPermitidos));
        unset($datos['id_pedido']);

        $set = implode(', ', array_map(fn ($c) => "`{$c}` = ?", array_keys($datos)));

        return $this->afectar(
            "UPDATE producto_pedido SET {$set} WHERE id = ? AND id_pedido = ?",
            [...array_values($datos), $id, $idPedido]
        ) >= 0;
    }

    /** Elimina una línea SOLO si pertenece a ese pedido. */
    public function eliminarDePedido(int|string $id, int|string $idPedido): int
    {
        return $this->afectar('DELETE FROM producto_pedido WHERE id = ? AND id_pedido = ?', [$id, $idPedido]);
    }

    /**
     * Pedidos con sus líneas agrupadas (vista lista_p_pedido).
     * LEFT JOIN con repartidor: un pedido hecho desde la tienda aún no tiene repartidor.
     *
     * @return list<array<string,mixed>>
     */
    public function obtenerPedidosAgrupados(string $buscar = ''): array
    {
        $sql = 'SELECT pp.id, pp.cant, pp.precio_venta, pp.unidad_venta, pp.total,
                       pp.id_pedido, pp.id_producto,
                       pr.nombre  AS nombre_producto,
                       rep.nombre AS nombre_repartidor,
                       rep.ape_pat AS ape_pat_repartidor,
                       rep.ape_mat AS ape_mat_repartidor,
                       ped.fecha  AS fecha_pedido,
                       cli.nombre AS nombre_cliente,
                       cli.ape_pat AS ape_pat_cliente,
                       cli.ape_mat AS ape_mat_cliente,
                       (SELECT estado FROM estatus
                         WHERE id_pedido = pp.id_pedido
                         ORDER BY id DESC LIMIT 1) AS estado_actual
                  FROM producto_pedido pp
                  JOIN producto pr          ON pr.id  = pp.id_producto
                  JOIN pedido ped           ON ped.id = pp.id_pedido
             LEFT JOIN repartidor rep       ON rep.id = ped.id_repartidor
                  JOIN cliente cli          ON cli.id = ped.id_cliente';

        $params = [];
        $buscar = trim($buscar);

        if ($buscar !== '') {
            $like    = $this->patronLike($buscar);
            $sql    .= ' WHERE (pp.id_pedido LIKE ? OR pr.nombre LIKE ? OR cli.nombre LIKE ?
                            OR cli.ape_pat LIKE ? OR ped.fecha LIKE ?)';
            $params  = array_fill(0, 5, $like);
        }

        $filas = $this->filas($sql . ' ORDER BY pp.id_pedido DESC, pp.id ASC', $params);

        // Agrupar en PHP
        $agrupados = [];

        foreach ($filas as $fila) {
            $idPedido = $fila['id_pedido'];

            if (! isset($agrupados[$idPedido])) {
                $agrupados[$idPedido] = [
                    'id_pedido'          => $idPedido,
                    'fecha_pedido'       => $fila['fecha_pedido'],
                    'nombre_cliente'     => $fila['nombre_cliente'],
                    'ape_pat_cliente'    => $fila['ape_pat_cliente'],
                    'ape_mat_cliente'    => $fila['ape_mat_cliente'],
                    'nombre_repartidor'  => $fila['nombre_repartidor'],
                    'ape_pat_repartidor' => $fila['ape_pat_repartidor'],
                    'ape_mat_repartidor' => $fila['ape_mat_repartidor'],
                    'estado_actual'      => $fila['estado_actual'],
                    'total_pedido'       => 0,
                    'items'              => [],
                ];
            }

            $agrupados[$idPedido]['items'][] = [
                'id'              => $fila['id'],
                'id_producto'     => $fila['id_producto'],
                'nombre_producto' => $fila['nombre_producto'],
                'cant'            => $fila['cant'],
                'precio_venta'    => $fila['precio_venta'],
                'unidad_venta'    => $fila['unidad_venta'],
                'total'           => $fila['total'],
            ];

            $agrupados[$idPedido]['total_pedido'] += (float) $fila['total'];
        }

        return array_values($agrupados);
    }
}
