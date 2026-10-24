<?php

namespace App\Models;

class Modelo_entrada extends PdoModelo
{
    protected string $tabla         = 'entrada';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = [
        'fecha', 'fecha_cad', 'cantidad', 'u_compra', 'u_venta', 'precio_compra_u',
        'conv_pc', 'precio_venta_u', 'id_producto', 'equivalente', 'conversion',
    ];

    /**
     * Lista paginada de entradas con el nombre de su producto y búsqueda opcional.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(?string $buscar = '', int $porPagina = 20): array
    {
        $buscar = trim((string) $buscar);
        $where  = '';
        $params = [];

        if ($buscar !== '') {
            $like  = $this->patronLike($buscar);
            $parte = [];

            if (ctype_digit($buscar)) {
                $parte[]  = 'entrada.id = ?';
                $parte[]  = 'entrada.id_producto = ?';
                $params[] = (int) $buscar;
                $params[] = (int) $buscar;
            }

            foreach ([
                'entrada.fecha', 'entrada.fecha_cad', 'entrada.cantidad', 'entrada.u_compra',
                'entrada.u_venta', 'entrada.precio_compra_u', 'entrada.precio_venta_u',
                'entrada.equivalente', 'entrada.conversion', 'producto.nombre',
            ] as $columna) {
                $parte[]  = "{$columna} LIKE ?";
                $params[] = $like;
            }

            $where = ' WHERE ' . implode(' OR ', $parte);
        }

        return $this->paginarSql(
            'SELECT entrada.*, producto.nombre AS nombre_producto
               FROM entrada
               JOIN producto ON producto.id = entrada.id_producto' . $where . '
              ORDER BY entrada.id DESC',
            $params,
            $porPagina
        );
    }

    /** @return list<array<string,mixed>> */
    public function getEntradasConProducto(): array
    {
        return $this->filas(
            'SELECT entrada.id, entrada.id_producto, entrada.fecha, entrada.u_venta, entrada.cantidad,
                    entrada.u_compra, producto.nombre AS nombre_producto
               FROM entrada
               JOIN producto ON producto.id = entrada.id_producto'
        );
    }

    /** @return array<int|string,string> mapa id_producto => precio máximo */
    public function precioMaximoPorProducto(): array
    {
        $filas = $this->filas(
            'SELECT id_producto, MAX(precio_venta_u) AS precio_maximo FROM entrada GROUP BY id_producto'
        );

        return array_column($filas, 'precio_maximo', 'id_producto');
    }

    /**
     * u_venta y precio_venta_u de la entrada más reciente de cada producto.
     * Mapa: id_producto => ['u_venta' => ..., 'precio_venta_u' => ...]
     *
     * @return array<int|string,array{u_venta:string,precio_venta_u:string}>
     */
    public function ultimaEntradaPorProducto(): array
    {
        $filas = $this->filas(
            'SELECT e.id_producto, e.u_venta, e.precio_venta_u
               FROM entrada e
               JOIN (SELECT MAX(id) AS max_id FROM entrada GROUP BY id_producto) ult
                 ON ult.max_id = e.id'
        );

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[$fila['id_producto']] = [
                'u_venta'        => $fila['u_venta'],
                'precio_venta_u' => $fila['precio_venta_u'],
            ];
        }

        return $mapa;
    }

    // ─────────────────────────────────────────────────────────────
    // ENTRADA RÁPIDA (productos de alta rotación)
    // ─────────────────────────────────────────────────────────────

    /** Días hacia atrás que se miran para saber qué productos rotan más. */
    public const DIAS_ROTACION = 30;

    /** Máximo de productos que se muestran en la pantalla de entrada rápida. */
    public const MAX_RAPIDA = 30;

    /**
     * Productos con alguna entrada previa, ordenados por rotación: primero los que
     * aparecen en más pedidos de los últimos $dias días (sin contar pedidos cancelados)
     * y, a igualdad, los de mayor cantidad vendida. Cada fila trae los datos de su última
     * entrada, que son los que la entrada rápida copia.
     *
     * @param bool $todos false = solo los que se vendieron en el periodo; true = todos
     *
     * @return list<array<string,mixed>>
     */
    public function altaRotacion(bool $todos = false, int $dias = self::DIAS_ROTACION, int $limite = self::MAX_RAPIDA): array
    {
        $desde  = date('Y-m-d', strtotime('-' . max(1, $dias) . ' days'));
        $limite = max(1, min(500, $limite)); // entero ya acotado: se escribe directo en el LIMIT

        $sql = 'SELECT pr.id AS id_producto, pr.nombre, pr.categoria,
                       e.fecha AS ultima_fecha, e.u_compra, e.u_venta, e.equivalente,
                       e.precio_compra_u, e.precio_venta_u,
                       COALESCE(v.pedidos, 0) AS pedidos,
                       COALESCE(v.vendido, 0) AS vendido
                  FROM producto pr
                  JOIN entrada e
                    ON e.id = (SELECT MAX(e2.id) FROM entrada e2 WHERE e2.id_producto = pr.id)
             LEFT JOIN (SELECT pp.id_producto,
                               COUNT(DISTINCT pp.id_pedido) AS pedidos,
                               SUM(pp.cant)                 AS vendido
                          FROM producto_pedido pp
                          JOIN pedido p ON p.id = pp.id_pedido
                         WHERE p.fecha >= ?
                           AND COALESCE((SELECT es.estado FROM estatus es
                                          WHERE es.id_pedido = p.id
                                          ORDER BY es.id DESC LIMIT 1), \'\') <> \'pedido_cancelado\'
                      GROUP BY pp.id_producto) v
                    ON v.id_producto = pr.id'
            . ($todos ? '' : ' WHERE COALESCE(v.pedidos, 0) > 0')
            . ' ORDER BY pedidos DESC, vendido DESC, pr.nombre ASC
                 LIMIT ' . $limite;

        return $this->filas($sql, [$desde]);
    }

    /** Cuántos productos no tienen ninguna entrada (no se pueden registrar con la entrada rápida). */
    public function productosSinEntrada(): int
    {
        return (int) $this->valor(
            'SELECT COUNT(*) FROM producto pr
              WHERE NOT EXISTS (SELECT 1 FROM entrada e WHERE e.id_producto = pr.id)'
        );
    }

    /** Entrada más reciente de un producto (o null si nunca ha tenido). */
    public function ultimaDeProducto(int $idProducto): ?array
    {
        return $this->fila(
            'SELECT * FROM entrada WHERE id_producto = ? ORDER BY id DESC LIMIT 1',
            [$idProducto]
        );
    }

    /**
     * Registra varias entradas de una vez copiando, de la última entrada de cada producto,
     * la unidad de compra y de venta, la equivalencia y los precios (salvo los que se envíen).
     * Todo o nada. La caducidad (fecha + 5 días), la conversión y la existencia las calculan
     * los triggers de MySQL, igual que en la entrada normal.
     *
     * @param list<array{id_producto:int,cantidad:float,precio_compra:?float,precio_venta:?float}> $lineas
     *
     * @return int cuántas entradas se registraron
     *
     * @throws \DomainException si un producto no tiene entrada previa de la cual copiar
     */
    public function crearRapidas(string $fecha, array $lineas): int
    {
        return (int) $this->transaccion(function () use ($fecha, $lineas) {
            $n = 0;

            foreach ($lineas as $linea) {
                $ultima = $this->ultimaDeProducto($linea['id_producto']);

                if ($ultima === null) {
                    throw new \DomainException(
                        'El producto ' . $linea['id_producto'] . ' no tiene una entrada previa; usa "Nueva Entrada".'
                    );
                }

                $this->insert([
                    'fecha'           => $fecha,
                    'fecha_cad'       => null, // el trigger la calcula (fecha + 5 días)
                    'cantidad'        => $linea['cantidad'],
                    'u_compra'        => $ultima['u_compra'],
                    'u_venta'         => $ultima['u_venta'],
                    'equivalente'     => $ultima['equivalente'],
                    'precio_compra_u' => $linea['precio_compra'] ?? $ultima['precio_compra_u'],
                    'precio_venta_u'  => $linea['precio_venta'] ?? $ultima['precio_venta_u'],
                    'id_producto'     => $linea['id_producto'],
                ]);
                $n++;
            }

            return $n;
        });
    }
}
