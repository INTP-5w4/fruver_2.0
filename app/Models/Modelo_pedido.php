<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_pedido extends Model{
    protected $table      = 'pedido';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['fecha','id_cliente','id_repartidor'];
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

}