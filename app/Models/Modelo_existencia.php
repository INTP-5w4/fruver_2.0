<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_existencia extends Model {
    protected $table         = 'existencia';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['e_total','e_bloqueado','e_venta','fecha','id_producto'];

public function filtrar($buscar = null)
{
    // JOINs necesarios para traer los datos de producto y la venta de entrada
    $this->select('existencia.*, producto.nombre AS nombre_producto, entrada.u_venta as u_venta')
        ->join('producto', 'producto.id = existencia.id_producto', 'left')
        ->join('entrada', 'entrada.id_producto = existencia.id_producto', 'left')
        ->groupby('existencia.id');

    if (!empty($buscar)) {
        $this->groupStart()
                ->like('existencia.id', $buscar)
                ->orLike('existencia.fecha', $buscar)
                ->orLike('existencia.id_producto', $buscar)
                ->orLike('producto.nombre', $buscar)
                ->orLike('entrada.u_venta', $buscar) // Opcional: incluir la venta en la búsqueda
            ->groupEnd();
    }

    return $this;
}

    public function stockDisponiblePorProducto(): array {
        $rows = $this->select('id_producto, e_venta')->findAll();
        return array_column($rows, 'e_venta', 'id_producto');
    }
}