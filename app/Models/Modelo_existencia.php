<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_existencia extends Model{
    protected $table      = 'existencia';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['e_total','e_bloqueado','e_venta','fecha','id_producto'];
public function filtrar($buscar = null)
{
    if (empty($buscar)) {
        return $this;
    }

    $this->groupStart()
        ->like('id', $buscar)
        ->orLike('e_total', $buscar)
        ->orLike('e_bloqueado', $buscar)
        ->orLike('e_venta', $buscar)
        ->orLike('fecha', $buscar)
        ->orLike('id_producto', $buscar)
    ->groupEnd();

    return $this;
}
}
