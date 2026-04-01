<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_productopedido extends Model{
    protected $table      = 'producto_pedido';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['cantidad' , 'precio_venta' ,
     'unidad_venta' , 'total' , 'id_pedido' , 'id_producto' ];
}