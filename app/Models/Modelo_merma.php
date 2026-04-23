<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_merma extends Model{
    protected $table      = 'merma';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['cantidad', 'fecha', 'notas', 'id_entrada', 'u_venta'];

}