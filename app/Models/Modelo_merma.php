<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_merma extends Model{
    protected $table      = 'merma';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['cantidad', 'fecha', 'notas', 'id_entrada', 'u_venta'];


public function perdidasPorMes()
{
    return $this->db->query("
        SELECT DATE_FORMAT(m.fecha, '%Y-%m') AS mes, 
               SUM(m.cantidad * e.precio_compra_u) AS perdida
        FROM merma m
        JOIN entrada e ON e.id = m.id_entrada
        WHERE m.fecha >= ?
        GROUP BY mes
        ORDER BY mes ASC
    ", [date('Y-m-d', strtotime('-6 months'))])
    ->getResultArray();
}
}