<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_productopedidos extends Model{
    protected $table      = 'producto_pedido';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['id','cant','precio_venta','unidad_venta','total','id_pedido','id_producto'];
    public function obtenerInformacionCompleta() {
        return $this->db->table('producto_pedido pp')
            ->select('
                pp.*, 
                pr.nombre AS nombre_producto, 
                rep.nombre AS nombre_repartidor,
                (SELECT estado FROM estatus WHERE id_pedido = pp.id_pedido ORDER BY id DESC LIMIT 1) as estado_actual
            ')
            ->join('producto pr', 'pr.id = pp.id_producto')
            ->join('pedido ped', 'ped.id = pp.id_pedido')
            ->join('repartidor rep', 'rep.id = ped.id_repartidor')
            ->get()->getResultArray();
    }
    
    
    }