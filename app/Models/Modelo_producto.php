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
    public function productosLowStock(int $limit = 5): array
{
    return $this->db->table('producto p')
        ->select('p.nombre, SUM(e.cantidad) AS total')
        ->join('entrada e', 'e.id_producto = p.id', 'left')
        ->groupBy('p.id, p.nombre')
        ->orderBy('total', 'ASC')
        ->limit($limit)
        ->get()
        ->getResultArray();
}
public function topProductos()
{
    return $this->db->table('producto_pedido pp')
        ->select('p.nombre, SUM(pp.total) AS total_vendido')
        ->join('producto p', 'p.id = pp.id_producto')
        ->groupBy('pp.id_producto')
        ->orderBy('total_vendido', 'DESC')
        ->limit(5)
        ->get()->getResultArray();
}

}