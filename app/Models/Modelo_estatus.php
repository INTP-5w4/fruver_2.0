<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_estatus extends Model{
    protected $table      = 'estatus';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['estado', 'fecha', 'id_pedido'];
}