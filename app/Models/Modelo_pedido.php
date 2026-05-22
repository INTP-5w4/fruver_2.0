<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_pedido extends Model{
    protected $table      = 'pedido';
    protected $primaryKey = 'id';
    protected $allowedFields = ['fecha','id_cliente','id_repartidor','id_producto_pedido'];

    public function pedidosPorSemana()
    {
        return $this->db->table('pedido')
            ->select("DATE_FORMAT(fecha, '%x-W%v') AS semana, COUNT(*) AS total")
            ->where('fecha >=', date('Y-m-d', strtotime('-8 weeks')))
            ->groupBy('semana')
            ->orderBy('semana', 'ASC')
            ->get()->getResultArray();
    }

    public function totalVentasPorSemana()
    {
        return $this->db->table('pedido p')
            ->select("DATE_FORMAT(p.fecha, '%x-W%v') AS semana, SUM(pp.total) AS ventas")
            ->join('producto_pedido pp', 'pp.id_pedido = p.id')
            ->where('p.fecha >=', date('Y-m-d', strtotime('-8 weeks')))
            ->groupBy('semana')
            ->orderBy('semana', 'ASC')
            ->get()->getResultArray();
    }

    // Compatibilidad hacia atrás (por si se usan en otro lado)
    public function pedidosPorMes()   { return $this->pedidosPorSemana(); }
    public function totalVentasPorMes(){ return $this->totalVentasPorSemana(); }

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