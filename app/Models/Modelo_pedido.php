<?php

namespace App\Models;

use DomainException;

class Modelo_pedido extends PdoModelo
{
    protected string $tabla         = 'pedido';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = ['fecha', 'id_cliente', 'id_repartidor'];

    /** Máquina de estados del pedido (flujo usado por el panel de administración). */
    public const TRANSICIONES = [
        'pedido_pendiente'   => ['pedido_realizado'],
        'pedido_realizado'   => ['pedido_confirmado', 'pedido_cancelado'],
        'pedido_confirmado'  => ['pedido_en_transito', 'pedido_cancelado'],
        'pedido_en_transito' => ['pedido_entregado', 'pedido_a_credito'],
        'pedido_entregado'   => ['pedido_pagado', 'pedido_a_credito'],
        'pedido_a_credito'   => ['pedido_pagado'],
        'pedido_pagado'      => [],
        'pedido_cancelado'   => [],
    ];

    /** Estados en los que un pedido ya no se puede eliminar. */
    public const ESTADOS_PROTEGIDOS = [
        'pedido_en_transito', 'pedido_entregado', 'pedido_a_credito', 'pedido_pagado',
    ];

    // ─────────────────────────────────────────────────────────────
    // ESTADÍSTICAS (dashboard)
    // ─────────────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public function pedidosPorSemana(): array
    {
        return $this->filas(
            "SELECT DATE_FORMAT(fecha, '%x-W%v') AS semana, COUNT(*) AS total
               FROM pedido
              WHERE fecha >= ?
           GROUP BY semana
           ORDER BY semana ASC",
            [date('Y-m-d', strtotime('-8 weeks'))]
        );
    }

    /** @return list<array<string,mixed>> */
    public function totalVentasPorSemana(): array
    {
        return $this->filas(
            "SELECT DATE_FORMAT(p.fecha, '%x-W%v') AS semana, SUM(pp.total) AS ventas
               FROM pedido p
               JOIN producto_pedido pp ON pp.id_pedido = p.id
              WHERE p.fecha >= ?
           GROUP BY semana
           ORDER BY semana ASC",
            [date('Y-m-d', strtotime('-8 weeks'))]
        );
    }

    // Compatibilidad hacia atrás
    public function pedidosPorMes(): array
    {
        return $this->pedidosPorSemana();
    }

    public function totalVentasPorMes(): array
    {
        return $this->totalVentasPorSemana();
    }

    // ─────────────────────────────────────────────────────────────
    // LISTADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Lista paginada de pedidos con nombre de cliente y repartidor.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(string $buscar = '', int $porPagina = 20): array
    {
        $where  = '';
        $params = [];
        $buscar = trim($buscar);

        if ($buscar !== '') {
            $like   = $this->patronLike($buscar);
            $where  = ' WHERE (pedido.id LIKE ? OR pedido.fecha LIKE ? OR cliente.nombre LIKE ?
                          OR cliente.ape_pat LIKE ? OR repartidor.nombre LIKE ? OR repartidor.ape_pat LIKE ?)';
            $params = array_fill(0, 6, $like);
        }

        return $this->paginarSql(
            'SELECT pedido.*,
                    cliente.nombre   AS nombre_cliente,
                    cliente.ape_pat  AS ape_pat_cliente,
                    repartidor.nombre  AS nombre_repartidor,
                    repartidor.ape_pat AS ape_pat_repartidor
               FROM pedido
          LEFT JOIN cliente    ON cliente.id    = pedido.id_cliente
          LEFT JOIN repartidor ON repartidor.id = pedido.id_repartidor' . $where . '
           ORDER BY pedido.id DESC',
            $params,
            $porPagina
        );
    }

    // ─────────────────────────────────────────────────────────────
    // PEDIDOS COMPLETOS (cabecera + líneas + estatus) — todo en una transacción
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un pedido desde el panel de administración.
     *
     * @param array{fecha:string,id_cliente:mixed,id_repartidor:mixed} $cab
     * @param list<array<string,mixed>> $items  líneas ya limpias (Modelo_productopedidos::limpiarItems)
     *
     * @throws DomainException si no hay stock suficiente
     */
    public function crearPedidoCompleto(array $cab, array $items, string $estado, ?string $fechaEstatus): int
    {
        return (int) $this->transaccion(function () use ($cab, $items, $estado, $fechaEstatus) {
            $existencia = new Modelo_existencia($this->pdo);
            $stock      = $existencia->stockDisponiblePorProducto();

            foreach ($items as $item) {
                $disponible = (float) ($stock[$item['id_producto']] ?? 0);

                if ($item['cant'] > $disponible) {
                    $producto = (new Modelo_producto($this->pdo))->find($item['id_producto']);
                    $nombre   = $producto['nombre'] ?? 'Producto';

                    throw new DomainException(
                        'Stock insuficiente para "' . $nombre . '". Disponible: ' . $disponible
                    );
                }
            }

            $idPedido = $this->insert($cab);

            $pp = new Modelo_productopedidos($this->pdo);
            foreach ($items as $item) {
                $pp->insert([
                    'cant'         => $item['cant'],
                    'precio_venta' => $item['precio_venta'],
                    'unidad_venta' => $item['unidad_venta'],
                    'total'        => $item['total'],
                    'id_pedido'    => $idPedido,
                    'id_producto'  => $item['id_producto'],
                ]);
            }

            (new Modelo_estatus($this->pdo))->insert(array_filter([
                'estado'    => $estado,
                'fecha'     => $fechaEstatus,
                'id_pedido' => $idPedido,
            ], fn ($v) => $v !== null && $v !== ''));

            return $idPedido;
        });
    }

    /**
     * Modifica un pedido completo (cabecera, líneas y nuevo estatus).
     * Una línea con "id" solo se actualiza si pertenece a este pedido.
     *
     * @param list<array<string,mixed>> $items líneas limpias
     */
    public function modificarPedidoCompleto(int|string $idPedido, array $cab, array $items, string $nuevoEstado, ?string $fechaEstatus): void
    {
        $this->transaccion(function () use ($idPedido, $cab, $items, $nuevoEstado, $fechaEstatus) {
            $this->update($idPedido, $cab);

            $pp = new Modelo_productopedidos($this->pdo);

            $idsEntrantes = array_filter(array_column($items, 'id'));

            foreach ($pp->itemsDePedido($idPedido) as $existente) {
                if (! in_array((int) $existente['id'], array_map('intval', $idsEntrantes), true)) {
                    $pp->eliminarDePedido($existente['id'], $idPedido);
                }
            }

            foreach ($items as $item) {
                $datos = [
                    'cant'         => $item['cant'],
                    'precio_venta' => $item['precio_venta'],
                    'unidad_venta' => $item['unidad_venta'],
                    'total'        => $item['total'],
                    'id_producto'  => $item['id_producto'],
                ];

                if (! empty($item['id'])) {
                    $pp->actualizarDePedido($item['id'], $idPedido, $datos);
                } else {
                    $pp->insert($datos + ['id_pedido' => $idPedido]);
                }
            }

            (new Modelo_estatus($this->pdo))->insert(array_filter([
                'estado'    => $nuevoEstado,
                'fecha'     => $fechaEstatus,
                'id_pedido' => $idPedido,
            ], fn ($v) => $v !== null && $v !== ''));
        });
    }

    /**
     * Elimina un pedido con sus líneas y estatus.
     *
     * @throws DomainException si el pedido ya va en camino / entregado / pagado
     */
    public function eliminarPedidoCompleto(int|string $idPedido): void
    {
        $this->transaccion(function () use ($idPedido) {
            $estatus = new Modelo_estatus($this->pdo);
            $actual  = $estatus->ultimoPorFecha($idPedido)['estado'] ?? null;

            if (in_array($actual, self::ESTADOS_PROTEGIDOS, true)) {
                throw new DomainException(
                    'No se puede eliminar: el pedido ya está en "' . str_replace('_', ' ', $actual) . '".'
                );
            }

            $estatus->eliminarPorPedido($idPedido);
            (new Modelo_productopedidos($this->pdo))->eliminarPorPedido($idPedido);
            $this->delete($idPedido);
        });
    }

    /** Estado actual de un pedido según el último estatus por fecha. */
    public function estadoActual(int|string $idPedido): string
    {
        return (new Modelo_estatus($this->pdo))->ultimoPorFecha($idPedido)['estado'] ?? 'pedido_pendiente';
    }

    // ─────────────────────────────────────────────────────────────
    // PEDIDOS DE LA TIENDA (landing main_page_4)
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un pedido para un cliente a partir del carrito de la tienda.
     *
     * El precio, la unidad y el stock se vuelven a leer de la base de datos: lo que
     * mande el navegador solo aporta "qué producto" y "cuánta cantidad".
     *
     * @param array<int,int|float> $lineas  id_producto => cantidad
     * @return array{id:int,total:float}
     *
     * @throws DomainException por producto no disponible o stock insuficiente
     */
    public function crearPedidoDeCliente(int $idCliente, array $lineas): array
    {
        if ($lineas === []) {
            throw new DomainException('Tu carrito está vacío.');
        }

        return $this->transaccion(function () use ($idCliente, $lineas) {
            $catalogo = (new Modelo_producto($this->pdo))->catalogoPorIds(array_keys($lineas));

            $items = [];
            $total = 0.0;

            foreach ($lineas as $idProducto => $cantidad) {
                $idProducto = (int) $idProducto;
                $cantidad   = (float) $cantidad;
                $prod       = $catalogo[$idProducto] ?? null;

                if ($prod === null) {
                    throw new DomainException('Uno de los productos de tu carrito ya no está disponible.');
                }

                if ($cantidad < 1 || $cantidad > 999) {
                    throw new DomainException('Cantidad no válida para "' . $prod['nombre'] . '".');
                }

                $disponible = (float) $prod['stock'];
                if ($cantidad > $disponible) {
                    throw new DomainException(
                        'Stock insuficiente para "' . $prod['nombre'] . '". Disponible: ' . rtrim(rtrim(number_format($disponible, 2, '.', ''), '0'), '.')
                    );
                }

                $precio  = (float) $prod['precio'];
                $subtot  = round($cantidad * $precio, 2);
                $total  += $subtot;

                $items[] = [
                    'cant'         => $cantidad,
                    'precio_venta' => $precio,
                    'unidad_venta' => Modelo_productopedidos::normalizarUnidad((string) $prod['unidad']),
                    'total'        => $subtot,
                    'id_producto'  => $idProducto,
                ];
            }

            $idPedido = $this->insert([
                'fecha'         => date('Y-m-d'),
                'id_cliente'    => $idCliente,
                'id_repartidor' => null, // lo asigna el personal al confirmar el pedido
            ]);

            $pp = new Modelo_productopedidos($this->pdo);
            foreach ($items as $item) {
                $pp->insert($item + ['id_pedido' => $idPedido]);
            }

            (new Modelo_estatus($this->pdo))->insert([
                'estado'    => 'pedido_realizado',
                'id_pedido' => $idPedido,
            ]);

            return ['id' => $idPedido, 'total' => round($total, 2)];
        });
    }
}
