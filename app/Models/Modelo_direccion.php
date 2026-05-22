<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_direccion extends Model{
    protected $table      = 'direccion';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['colonia','calle','numero','municipio','estado','id_cliente'];

public function busqueda($texto = '')
{
    $this->select('d.*, c.nombre, c.ape_pat, c.ape_mat')
         ->from('direccion d', true)   // reemplaza la tabla base
         ->join('cliente c', 'c.id = d.id_cliente');

    if (!empty($texto)) {
        $this->groupStart()
                ->like('c.nombre',   $texto)
                ->orLike('d.id', $texto)
                ->orLike('c.ape_pat', $texto)
                ->orLike('d.colonia', $texto)
                ->orLike('d.calle',   $texto)
                ->orLike('d.municipio', $texto)
                ->orLike('d.estado',  $texto)
             ->groupEnd();
    }

    return $this; // devuelve el builder del modelo para encadenar
}
}