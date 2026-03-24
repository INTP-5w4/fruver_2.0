<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_producto extends Model{
    protected $table      = 'producto';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre', 'descripcion'];
        public function getproducto($id){
        return $this->where('id',$id)-> first();
    }

    public function borrar($id){
        if($this->delete($id))
            return true;
            else
                return false;
        
    }
}