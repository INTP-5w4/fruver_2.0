<?php

namespace App\Models;

class Modelo_existencia extends PdoModelo
{
    protected string $tabla         = 'existencia';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = ['e_total', 'e_bloqueado', 'e_venta', 'fecha', 'id_producto'];

    /**
     * Existencias con nombre de producto y unidad de venta (de su entrada más reciente).
     * Búsqueda opcional por id, fecha, producto, nombre o unidad.
     *
     * @return list<array<string,mixed>>
     */
    public function listarFiltrado(?string $buscar = null): array
    {
        $sql = 'SELECT * FROM (
                    SELECT existencia.*,
                           producto.nombre AS nombre_producto,
                           (SELECT e2.u_venta FROM entrada e2
                             WHERE e2.id_producto = existencia.id_producto
                             ORDER BY e2.id DESC LIMIT 1) AS u_venta
                      FROM existencia
                 LEFT JOIN producto ON producto.id = existencia.id_producto
                ) t';

        $params = [];
        $buscar = trim((string) $buscar);

        if ($buscar !== '') {
            $like   = $this->patronLike($buscar);
            $sql   .= ' WHERE (t.id LIKE ? OR t.fecha LIKE ? OR t.id_producto LIKE ?
                           OR t.nombre_producto LIKE ? OR t.u_venta LIKE ?)';
            $params = array_fill(0, 5, $like);
        }

        return $this->filas($sql . ' ORDER BY t.id DESC', $params);
    }

    /**
     * Stock disponible para venta por producto: id_producto => e_venta.
     * Si un producto tiene varias filas de existencia, cuenta la más reciente (mayor id).
     *
     * @return array<int|string,string|null>
     */
    public function stockDisponiblePorProducto(): array
    {
        $filas = $this->filas('SELECT id_producto, e_venta FROM existencia ORDER BY id ASC');

        return array_column($filas, 'e_venta', 'id_producto');
    }
}
