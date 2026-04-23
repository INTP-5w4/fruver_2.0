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
    return $this->db->table('merma m')
        ->select("DATE_FORMAT(m.fecha, '%Y-%m') AS mes, SUM(m.cantidad * e.precio_compra_u) AS perdida")
        ->join('entrada e', 'e.id = m.id_entrada')
        ->where('m.fecha >=', date('Y-m-d', strtotime('-6 months')))
        ->groupBy('mes')
        ->orderBy('mes', 'ASC')
        ->get()->getResultArray();
}
}