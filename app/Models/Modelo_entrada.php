<?php 
namespace App\Models;

use CodeIgniter\Model; 

class Modelo_entrada extends Model{
    protected $table      = 'entrada';
    protected $primaryKey = 'id';
    protected $allowedFields = ['fecha','fecha_cad','cantidad','u_compra','u_venta','precio_compra_u','conv_pc', 'precio_venta_u','id_producto', 'equivalente', 'conversion']; 

public function getEntradasConProducto()
{
    return $this->db->table('entrada')
        // AÑADIDO: entrada.id_producto
        ->select('entrada.id, entrada.id_producto, entrada.fecha, entrada.u_venta, entrada.cantidad, entrada.u_compra, producto.nombre AS nombre_producto')
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

/**
 * Devuelve la u_venta y el precio_venta_u de la entrada más reciente
 * por cada producto. Mapa: id_producto => ['u_venta' => ..., 'precio_venta_u' => ...]
 */
public function ultimaEntradaPorProducto(): array
{
    $sub = $this->db->table('entrada')
        ->select('MAX(id) AS max_id, id_producto')
        ->groupBy('id_producto')
        ->getCompiledSelect();

    $rows = $this->db->query(
        "SELECT e.id_producto, e.u_venta, e.precio_venta_u
         FROM entrada e
         INNER JOIN ($sub) last ON last.max_id = e.id"
    )->getResultArray();

    $mapa = [];
    foreach ($rows as $row) {
        $mapa[$row['id_producto']] = [
            'u_venta'        => $row['u_venta'],
            'precio_venta_u' => $row['precio_venta_u'],
        ];
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

    if (ctype_digit($buscar)) {
        $this->orWhere('entrada.id', (int)$buscar);
        $this->orWhere('entrada.id_producto', (int)$buscar);
    }

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