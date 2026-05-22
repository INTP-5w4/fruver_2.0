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

// En el modelo — retorna $this en vez de ejecutar la query
public function buscarPedidos(string $buscar = ''): static
{
    $this->select('pedido.*, 
                   cliente.nombre AS nombre_cliente,
                   cliente.ape_pat AS ape_pat_cliente,
                   repartidor.nombre AS nombre_repartidor,
                   repartidor.ape_pat AS ape_pat_repartidor')
         ->join('cliente',    'cliente.id    = pedido.id_cliente',    'left')
         ->join('repartidor', 'repartidor.id = pedido.id_repartidor', 'left');

    if (!empty($buscar)) {
        $this->groupStart()
            ->like('pedido.id',          $buscar)
            ->orLike('pedido.fecha',      $buscar)
            ->orLike('cliente.nombre',    $buscar)
            ->orLike('cliente.ape_pat',   $buscar)
            ->orLike('repartidor.nombre', $buscar)
            ->orLike('repartidor.ape_pat',$buscar)
        ->groupEnd();
    }

    return $this;
}
}