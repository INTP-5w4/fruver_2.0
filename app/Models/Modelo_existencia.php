<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_existencia extends Model{
    protected $table      = 'existencia';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['e_total','e_bloqueado','e_venta','fecha','id_producto'];

public function buscar($texto)
{
    return $this->groupStart()
        ->like('id', $texto)
        ->orLike('e_total', $texto)
        ->orLike('e_bloqueado', $texto)
        ->orLike('e_venta', $texto)
        ->orLike('fecha', $texto)
    ->groupEnd()
    ->orderBy('id', 'DESC')
    ->findAll();
}
}