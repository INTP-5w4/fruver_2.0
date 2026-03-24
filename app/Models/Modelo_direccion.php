<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_direccion extends Model{
    protected $table      = 'direccion';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['colonia','calle','numero','municipio','estado','id_cliente'];
}