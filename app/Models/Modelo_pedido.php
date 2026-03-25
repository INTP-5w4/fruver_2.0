<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_pedido extends Model{
    protected $table      = 'pedido';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['fecha','id_cliente','id_repartidor'];
}