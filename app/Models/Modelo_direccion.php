<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_direccion extends Model{
    protected $table      = 'direccion';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['colonia','calle','numero','municipio','estado','id_cliente'];

public function busqueda($texto){
    $builder = $this->db->table('direccion d');
    $builder->select('d.*, c.nombre, c.ape_pat, c.ape_mat');
    $builder->join('cliente c', 'c.id = d.id_cliente');

    if (!empty($texto)){
        $builder->groupStart()
            ->like('c.nombre', $texto)
            ->orLike('d.colonia', $texto)
            ->orLike('d.calle', $texto)
            ->orLike('d.municipio', $texto)
            ->orLike('d.estado', $texto)
        ->groupEnd();
    }

    return $builder->get()->getResultArray();
}
}