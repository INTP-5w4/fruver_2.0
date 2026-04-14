<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_entrada extends Model{
    protected $table      = 'entrada';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['fecha','fecha_cad','cantidad','u_compra','u_venta', 'equivalente', 'conversion', 'precio_compra','id_producto']; 
}