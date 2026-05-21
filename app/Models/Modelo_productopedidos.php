<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_productopedidos extends Model {
    protected $table      = 'producto_pedido';
    protected $primaryKey = 'id';
    protected $allowedFields = ['id','cant','precio_venta','unidad_venta','total','id_pedido','id_producto'];

    /**
     * Devuelve una fila por cada producto_pedido (uso interno / legacy)
     */
    public function obtenerInformacionCompleta(): array {
        return $this->db->table('producto_pedido pp')
            ->select('
                pp.*,
                pr.nombre AS nombre_producto,
                rep.nombre AS nombre_repartidor,
                rep.ape_pat AS ape_pat_repartidor,
                rep.ape_mat AS ape_mat_repartidor,
                ped.fecha AS fecha_pedido,
                cli.nombre AS nombre_cliente,
                cli.ape_pat AS ape_pat_cliente,
                cli.ape_mat AS ape_mat_cliente,
                (SELECT estado FROM estatus WHERE id_pedido = pp.id_pedido ORDER BY id DESC LIMIT 1) AS estado_actual
            ')
            ->join('producto pr',    'pr.id  = pp.id_producto')
            ->join('pedido ped',     'ped.id = pp.id_pedido')
            ->join('repartidor rep', 'rep.id = ped.id_repartidor')
            ->join('cliente cli',    'cli.id = ped.id_cliente')
            ->get()->getResultArray();
    }

    /**
     * Devuelve una entrada por pedido con sus ítems anidados.
     * Estructura de cada elemento:
     * [
     *   'id_pedido'            => int,
     *   'fecha_pedido'         => string,
     *   'nombre_cliente'       => string,
     *   'ape_pat_cliente'      => string,
     *   'ape_mat_cliente'      => string,
     *   'nombre_repartidor'    => string,
     *   'ape_pat_repartidor'   => string,
     *   'ape_mat_repartidor'   => string,
     *   'estado_actual'        => string,
     *   'total_pedido'         => float,
     *   'items'                => [ [...], [...] ]
     * ]
     */
    public function obtenerPedidosAgrupados(): array {
        $filas = $this->db->table('producto_pedido pp')
            ->select('
                pp.id,
                pp.cant,
                pp.precio_venta,
                pp.unidad_venta,
                pp.total,
                pp.id_pedido,
                pp.id_producto,
                pr.nombre AS nombre_producto,
                rep.nombre AS nombre_repartidor,
                rep.ape_pat AS ape_pat_repartidor,
                rep.ape_mat AS ape_mat_repartidor,
                ped.fecha AS fecha_pedido,
                cli.nombre AS nombre_cliente,
                cli.ape_pat AS ape_pat_cliente,
                cli.ape_mat AS ape_mat_cliente,
                (SELECT estado FROM estatus WHERE id_pedido = pp.id_pedido ORDER BY id DESC LIMIT 1) AS estado_actual
            ')
            ->join('producto pr',    'pr.id  = pp.id_producto')
            ->join('pedido ped',     'ped.id = pp.id_pedido')
            ->join('repartidor rep', 'rep.id = ped.id_repartidor')
            ->join('cliente cli',    'cli.id = ped.id_cliente')
            ->orderBy('pp.id_pedido', 'DESC')
            ->get()->getResultArray();

        // Agrupar por id_pedido en PHP
        $agrupados = [];

        foreach ($filas as $fila) {
            $idPedido = $fila['id_pedido'];

            if (!isset($agrupados[$idPedido])) {
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
                'id'            => $fila['id'],
                'id_producto'   => $fila['id_producto'],
                'nombre_producto' => $fila['nombre_producto'],
                'cant'          => $fila['cant'],
                'precio_venta'  => $fila['precio_venta'],
                'unidad_venta'  => $fila['unidad_venta'],
                'total'         => $fila['total'],
            ];

            $agrupados[$idPedido]['total_pedido'] += (float) $fila['total'];
        }

        // Reindexar para que sea un array numérico simple
        return array_values($agrupados);
    }
}