<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_cliente extends Model{
    protected $table      = 'cliente';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre','ape_pat','ape_mat','telefono']; 

}