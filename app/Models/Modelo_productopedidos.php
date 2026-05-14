<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_productopedidos extends Model{
    protected $table      = 'producto_pedido';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['id','cant','precio_venta','unidad_venta','total','id_pedido','id_producto'];
    public function conNombreProducto() {
        return $this->db->table('producto_pedido pp')
            ->select('pp.*, pr.nombre AS nombre_producto')
            ->join('producto pr', 'pr.id = pp.id_producto')
            ->get()->getResultArray();
    }
    }