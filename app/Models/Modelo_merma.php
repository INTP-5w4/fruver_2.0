<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_merma extends Model{
    protected $table      = 'merma';
    protected $primaryKey = 'id';
    protected $allowedFields = ['cantidad', 'fecha', 'notas', 'id_entrada', 'u_venta'];

    public function perdidasPorSemana()
    {
        return $this->db->query("
            SELECT DATE_FORMAT(m.fecha, '%x-W%v') AS semana, 
                   SUM(m.cantidad * e.precio_compra_u) AS perdida
            FROM merma m
            JOIN entrada e ON e.id = m.id_entrada
            WHERE m.fecha >= ?
            GROUP BY semana
            ORDER BY semana ASC
        ", [date('Y-m-d', strtotime('-8 weeks'))])
        ->getResultArray();
    }

    // Alias para compatibilidad
    public function perdidasPorMes() { return $this->perdidasPorSemana(); }
}