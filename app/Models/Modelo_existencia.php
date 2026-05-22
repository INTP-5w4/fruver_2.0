<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_existencia extends Model {
    protected $table         = 'existencia';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['e_total','e_bloqueado','e_venta','fecha','id_producto'];

    public function filtrar($buscar = null)
    {
        // JOIN siempre activo para poder ordenar y seleccionar nombre_producto
        $this->select('existencia.*, producto.nombre AS nombre_producto')
             ->join('producto', 'producto.id = existencia.id_producto', 'left');

        if (!empty($buscar)) {
            $this->groupStart()
                ->like('existencia.id', $buscar)
                ->orLike('existencia.fecha', $buscar)
                ->orLike('existencia.id_producto', $buscar)
                ->orLike('producto.nombre', $buscar)
            ->groupEnd();
        }

        return $this;
    }

    public function stockDisponiblePorProducto(): array {
        $rows = $this->select('id_producto, e_venta')->findAll();
        return array_column($rows, 'e_venta', 'id_producto');
    }
}