<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_entrada extends Model{
    protected $table      = 'entrada';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['fecha','fecha_cad','cantidad','u_compra','u_venta','precio_compra_u', 'precio_venta_u','id_producto', 'equivalente', 'conversion']; 
public function getEntradasConProducto()
{
    return $this->db->table('entrada')
        ->select('entrada.id, entrada.fecha, entrada.u_venta, entrada.cantidad, entrada.u_compra, producto.nombre AS nombre_producto')
        ->join('producto', 'producto.id = entrada.id_producto')
        ->get()
        ->getResultArray();
}
public function precioMaximoPorProducto(): array
{
    $rows = $this->db->table('entrada')
        ->select('id_producto, MAX(precio_venta_u) AS precio_maximo')
        ->groupBy('id_producto')
        ->get()
        ->getResultArray();

    $mapa = [];
    foreach ($rows as $row) {
        $mapa[$row['id_producto']] = $row['precio_maximo'];
    }
    return $mapa;
}
    }