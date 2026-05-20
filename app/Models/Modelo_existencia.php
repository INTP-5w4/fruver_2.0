<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_existencia extends Model{
    protected $table      = 'existencia';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['e_total','e_bloqueado','e_venta','fecha','id_producto'];
    public function stockDisponiblePorProducto(): array {
        $rows = $this->select('id_producto, e_venta')->findAll();
        return array_column($rows, 'e_venta', 'id_producto');
    }
    }