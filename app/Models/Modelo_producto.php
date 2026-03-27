<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_producto extends Model{
    protected $table      = 'producto';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre', 'descripcion', 'categoria', 'img'];
        public function getproducto($id){
        return $this->where('id',$id)-> first();
    }

    public function borrar($id){
        if($this->delete($id))
            return true;
            else
                return false;
        
    }
    //Busquedas
    public function busqueda_compleja($filtros){
        $builder=$this->builder();
        if (!empty($filtros['f'])){
            $builder->groupStart()->like('nombre',$filtros['f'])->groupEnd();
        }
        if (!empty($filtros['categoria'])){
            $builder->where('categoria_id', $filtros['categoria']);
        }
        $orden= $filtros['orden']??'id';
        $dir=$filtros['dir']??'ASC';
        $builder->orderBy($orden,$dir);
        return $this;
    }
}