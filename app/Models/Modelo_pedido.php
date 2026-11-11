<?php

namespace App\Models;

use DomainException;

class Modelo_pedido extends PdoModelo
{
    protected string $tabla         = 'pedido';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = ['fecha', 'id_cliente', 'id_repartidor'];

    /** Columnas de la migración de rutas (2026-11-05-100000_AddRutasReparto). */
    public const CAMPOS_RUTAS = ['id_direccion', 'ventana_inicio', 'ventana_fin'];

    private static ?bool $conRutas = null;

    public function __construct(?\PDO $pdo = null)
    {
        parent::__construct($pdo);

        if ($this->tieneColumnasRutas()) {
            $this->camposPermitidos = [...$this->camposPermitidos, ...self::CAMPOS_RUTAS];
        }
    }

    /** ¿Ya existe pedido.id_direccion? Mientras no, todo funciona como antes. */
    public function tieneColumnasRutas(): bool
    {
        if (self::$conRutas === null) {
            try {
                $this->filas('SELECT id_direccion FROM pedido LIMIT 0');
                self::$conRutas = true;
            } catch (\Throwable) {
                self::$conRutas = false;
            }
        }

        return self::$conRutas;
    }

    /**
     * Completa la cabecera con la dirección de entrega: la solicitada (si es del cliente)
     * o la principal del cliente. Sin la migración de rutas, quita esos campos.
     */
    private function conDireccion(array $cab, bool $modificando = false): array
    {
        if (! $this->tieneColumnasRutas()) {
            return array_diff_key($cab, array_flip(self::CAMPOS_RUTAS));
        }

        // Al modificar sin que el formulario mande dirección se conserva la que tenía.
        if (array_key_exists('id_cliente', $cab) && (array_key_exists('id_direccion', $cab) || ! $modificando)) {
            $solicitada = isset($cab['id_direccion']) && (int) $cab['id_direccion'] > 0 ? (int) $cab['id_direccion'] : null;
            $cab['id_direccion'] = (new Modelo_direccion($this->pdo))->resolverParaPedido((int) $cab['id_cliente'], $solicitada);
        }

        foreach (['ventana_inicio', 'ventana_fin'] as $campo) {
            if (array_key_exists($campo, $cab)) {
                $cab[$campo] = self::horaValida($cab[$campo]);
            }
        }

        return $cab;
    }

    /** "14:30" / "14:30:00" → "14:30:00"; cualquier otra cosa → null. */
    public static function horaValida(mixed $hora): ?string
    {
        $hora = trim((string) $hora);

        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $hora) !== 1) {
            return null;
        }

        return strlen($hora) === 5 ? $hora . ':00' : $hora;
    }

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
    // HISTORIAL POR CLIENTE (finalidades voluntarias V2 y V3: solo con consentimiento)
    // ─────────────────────────────────────────────────────────────

    /**
     * Productos que más pide cada cliente (por número de pedidos en que aparecen).
     *
     * @param list<int> $idsCliente
     *
     * @return array<int, list<string>> id_cliente => nombres (máximo $porCliente)
     */
    public function productosFrecuentes(array $idsCliente, int $porCliente = 3): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsCliente), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $salida = [];

        foreach ($this->filas(
            "SELECT p.id_cliente, pr.nombre, COUNT(DISTINCT p.id) AS veces, MAX(p.fecha) AS ultima
               FROM pedido p
               JOIN producto_pedido pp ON pp.id_pedido = p.id
               JOIN producto pr        ON pr.id = pp.id_producto
              WHERE p.id_cliente IN ({$marcas})
           GROUP BY p.id_cliente, pp.id_producto, pr.nombre
           ORDER BY p.id_cliente, veces DESC, ultima DESC",
            $ids
        ) as $fila) {
            $id = (int) $fila['id_cliente'];
            if (count($salida[$id] ?? []) < $porCliente) {
                $salida[$id][] = (string) $fila['nombre'];
            }
        }

        return $salida;
    }

    /**
     * Último pedido de cada cliente (el que se evalúa en una encuesta).
     *
     * @param list<int> $idsCliente
     *
     * @return array<int, array{id:int, fecha:string}>
     */
    public function ultimosDeClientes(array $idsCliente): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsCliente), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $salida = [];

        foreach ($this->filas(
            "SELECT p.id_cliente, p.id, p.fecha
               FROM pedido p
               JOIN (SELECT id_cliente, MAX(id) AS ultimo FROM pedido WHERE id_cliente IN ({$marcas}) GROUP BY id_cliente) u
                 ON u.ultimo = p.id",
            $ids
        ) as $fila) {
            $salida[(int) $fila['id_cliente']] = ['id' => (int) $fila['id'], 'fecha' => (string) $fila['fecha']];
        }

        return $salida;
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

            $idPedido = $this->insert($this->conDireccion($cab));

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
            $this->update($idPedido, $this->conDireccion($cab, true));

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
            $actual  = $estatus->ultimo($idPedido)['estado'] ?? null;

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
        return (new Modelo_estatus($this->pdo))->ultimo($idPedido)['estado'] ?? 'pedido_pendiente';
    }

    /**
     * Datos para el ticket (resumen) de un pedido: cliente, estado actual y líneas.
     * Devuelve null si el pedido no existe. Se usa para el mensaje de WhatsApp.
     *
     * @return null|array{id:int,fecha:?string,cliente:string,telefono:?string,estado:string,
     *                    total:float,items:list<array<string,mixed>>}
     */
    public function ticket(int|string $idPedido): ?array
    {
        $cab = $this->fila(
            'SELECT p.id, p.fecha, c.nombre, c.ape_pat, c.ape_mat, c.telefono
               FROM pedido p
               JOIN cliente c ON c.id = p.id_cliente
              WHERE p.id = ?
              LIMIT 1',
            [$idPedido]
        );

        if ($cab === null) {
            return null;
        }

        $items = $this->filas(
            'SELECT pp.cant, pp.unidad_venta, pp.precio_venta, pp.total, pr.nombre AS nombre_producto
               FROM producto_pedido pp
               JOIN producto pr ON pr.id = pp.id_producto
              WHERE pp.id_pedido = ?
           ORDER BY pp.id ASC',
            [$idPedido]
        );

        $total = 0.0;
        foreach ($items as $item) {
            // `total` puede venir nulo (lo calcula un trigger al insertar): se recalcula.
            $total += $item['total'] !== null
                ? (float) $item['total']
                : round((float) $item['cant'] * (float) $item['precio_venta'], 2);
        }

        return [
            'id'       => (int) $cab['id'],
            'fecha'    => $cab['fecha'],
            'cliente'  => trim(implode(' ', array_filter([$cab['nombre'], $cab['ape_pat'], $cab['ape_mat']]))),
            'telefono' => $cab['telefono'],
            'estado'   => $this->estadoActual($idPedido),
            'total'    => round($total, 2),
            'items'    => $items,
        ];
    }
        // ─────────────────────────────────────────────────────────────
    // RECARGAR PEDIDO (volver a pedir lo mismo)
    // ─────────────────────────────────────────────────────────────

    /**
     * Prepara las líneas de un pedido anterior para cargarlas en un pedido nuevo.
     * Precio, unidad y stock se leen del catálogo vigente, no del pedido viejo.
     *
     * @return array{pedido:array<string,mixed>,items:list<array<string,mixed>>,avisos:list<string>}
     *
     * @param int|null $idCliente  Si se indica (lado tienda), el pedido debe pertenecer a ese cliente.
     *
     * @throws DomainException si el pedido no existe o ninguna línea se puede recargar
     */
    public function prepararRecarga(int|string $idPedido, ?int $idCliente = null): array
    {
        $pedido = $this->find($idPedido);

        // Mismo mensaje si no existe o es de otro cliente: no se revela que existe
        if (! $pedido || ($idCliente !== null && (int) $pedido['id_cliente'] !== $idCliente)) {
            throw new DomainException('El pedido no existe.');
        }

        $lineas = (new Modelo_productopedidos($this->pdo))->itemsDePedido($idPedido);
        $avisos = [];

        // Sumar cantidades por producto (todas las líneas usarán el mismo precio vigente)
        $porProducto = [];

        foreach ($lineas as $linea) {
            $cant = (float) $linea['cant'];

            if ($cant <= 0) {
                $avisos[] = 'Se omitió una línea del pedido original con cantidad no válida.';
                continue;
            }

            $id = (int) $linea['id_producto'];
            $porProducto[$id] = ($porProducto[$id] ?? 0) + $cant;
        }

        $productos = new Modelo_producto($this->pdo);
        $catalogo  = $productos->catalogoPorIds(array_keys($porProducto));
        $items     = [];

        foreach ($porProducto as $idProducto => $cant) {
            $prod = $catalogo[$idProducto] ?? null;

            if ($prod === null) {
                $nombre   = $productos->find($idProducto)['nombre'] ?? 'Producto #' . $idProducto;
                $avisos[] = '"' . $nombre . '" ya no está disponible (sin precio de venta) y no se incluyó.';
                continue;
            }

            $stock = (float) $prod['stock'];

            if ($stock <= 0) {
                $avisos[] = '"' . $prod['nombre'] . '" no tiene stock y no se incluyó.';
                continue;
            }

            if ($cant > $stock) {
                $avisos[] = '"' . $prod['nombre'] . '": la cantidad se ajustó de ' . $cant . ' a ' . $stock . ' (stock disponible).';
                $cant     = $stock;
            }

            try {
                $unidad = Modelo_productopedidos::normalizarUnidad((string) $prod['unidad']);
            } catch (DomainException) {
                $avisos[] = '"' . $prod['nombre'] . '" tiene una unidad de venta no válida y no se incluyó.';
                continue;
            }

            $precio  = (float) $prod['precio'];
            $items[] = [
                'id'          => null,
                'id_producto' => $idProducto,
                'u_venta'     => $unidad,
                'cant'        => round($cant, 2),
                'p_venta'     => round($precio, 2),
                'total'       => number_format($cant * $precio, 2, '.', ''),
            ];
        }

        if ($items === []) {
            throw new DomainException('No se pudo recargar: ninguna línea tiene stock o precio de venta vigente.');
        }

        return ['pedido' => $pedido, 'items' => $items, 'avisos' => $avisos];
    }
        /**
     * Últimos pedidos de un cliente, con total y lista de productos (para "Volver a pedir").
     *
     * @return list<array<string,mixed>>
     */
    public function recientesDeCliente(int $idCliente, int $limite = 5): array
    {
        return $this->filas(
            'SELECT p.id, p.fecha,
                    (SELECT COALESCE(SUM(pp.total), 0)
                       FROM producto_pedido pp WHERE pp.id_pedido = p.id) AS total,
                    (SELECT GROUP_CONCAT(pr.nombre ORDER BY pp.id SEPARATOR ", ")
                       FROM producto_pedido pp
                       JOIN producto pr ON pr.id = pp.id_producto
                      WHERE pp.id_pedido = p.id) AS productos
               FROM pedido p
              WHERE p.id_cliente = ?
           ORDER BY p.id DESC
              LIMIT ' . max(1, $limite),
            [$idCliente]
        );
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
     * @param int|null $idDireccion  dirección del cliente (null = su principal)
     * @return array{id:int,total:float}
     *
     * @throws DomainException por producto no disponible o stock insuficiente
     */
    public function crearPedidoDeCliente(int $idCliente, array $lineas, ?int $idDireccion = null): array
    {
        if ($lineas === []) {
            throw new DomainException('Tu carrito está vacío.');
        }

        return $this->transaccion(function () use ($idCliente, $lineas, $idDireccion) {
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

            $idPedido = $this->insert($this->conDireccion([
                'fecha'         => date('Y-m-d'),
                'id_cliente'    => $idCliente,
                'id_repartidor' => null, // lo asigna el personal al confirmar el pedido
                'id_direccion'  => $idDireccion,
            ]));

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
