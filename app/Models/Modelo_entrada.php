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
}
