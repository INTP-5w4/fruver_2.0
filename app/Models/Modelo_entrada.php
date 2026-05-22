<?php 
namespace App\Models;

use CodeIgniter\Model; 

class Modelo_entrada extends Model{
    protected $table      = 'entrada';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['fecha','fecha_cad','cantidad','u_compra','u_venta','precio_compra_u','conv_pc', 'precio_venta_u','id_producto', 'equivalente', 'conversion']; 
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
public function conProducto(): static
{
    return $this
        ->select('entrada.*, producto.nombre as nombre_producto')
        ->join('producto', 'producto.id = entrada.id_producto');
}
public function filtrar(string $buscar = null): static
{
    if (empty($buscar)) {
        return $this;
    }

    $this->groupStart();

    // Buscar por ID exacto si es número
    if (ctype_digit($buscar)) {
        $this->orWhere('entrada.id', (int)$buscar);
        $this->orWhere('entrada.id_producto', (int)$buscar);
    }

    // Buscar en todos los campos
    $this->orLike('entrada.fecha', $buscar)
        ->orLike('entrada.fecha_cad', $buscar)
        ->orLike('entrada.cantidad', $buscar)
        ->orLike('entrada.u_compra', $buscar)
        ->orLike('entrada.u_venta', $buscar)
        ->orLike('entrada.precio_compra_u', $buscar)
        ->orLike('entrada.precio_venta_u', $buscar)
        ->orLike('entrada.equivalente', $buscar)
        ->orLike('entrada.conversion', $buscar)
        ->orLike('producto.nombre', $buscar);

    $this->groupEnd();

    return $this;
}
}