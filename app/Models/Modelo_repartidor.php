<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_repartidor extends Model{
    protected $table      = 'repartidor';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre','ape_pat','ape_mat','telefono','direccion','notas'];
}