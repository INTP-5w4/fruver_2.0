<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_pedido extends Model{
    protected $table      = 'pedido';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['fecha','id_cliente','id_repartidor','id_producto_pedido'];
    public function pedidosPorMes()
{
    return $this->db->table('pedido')
        ->select("DATE_FORMAT(fecha, '%Y-%m') AS mes, COUNT(*) AS total")
        ->where('fecha >=', date('Y-m-d', strtotime('-6 months')))
        ->groupBy('mes')
        ->orderBy('mes', 'ASC')
        ->get()->getResultArray();
}

public function totalVentasPorMes()
{
    return $this->db->table('pedido p')
        ->select("DATE_FORMAT(p.fecha, '%Y-%m') AS mes, SUM(pp.total) AS ventas")
        ->join('producto_pedido pp', 'pp.id_pedido = p.id')
        ->where('p.fecha >=', date('Y-m-d', strtotime('-6 months')))
        ->groupBy('mes')
        ->orderBy('mes', 'ASC')
        ->get()->getResultArray();
}
public function buscarPedidos(string $buscar = ''): array
{
    $builder = $this->db->table('pedido p')
    ->select('p.*, r.nombre, r.ape_pat, r.ape_mat')
    ->join('repartidor r', 'r.id = p.id_repartidor', 'left');

    if ($buscar !== '') {
        $builder->groupStart()
            ->like('r.nombre',   $buscar)
            ->orLike('r.ape_pat', $buscar)
            ->orLike('r.ape_mat', $buscar)
        ->groupEnd();
    }

    return $builder->get()->getResultArray();
}

}