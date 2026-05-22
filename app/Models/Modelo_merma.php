<?php

namespace App\Models;

use CodeIgniter\Model;

class Modelo_merma extends Model
{

    protected $table      = 'merma';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'cantidad',
        'fecha',
        'notas',
        'id_entrada',
        'u_venta'
    ];

    // ─────────────────────────────────────────────────────────────
    // PÉRDIDAS POR SEMANA
    // ─────────────────────────────────────────────────────────────
    public function perdidasPorSemana()
    {

        return $this->db->query("
            SELECT 
                DATE_FORMAT(m.fecha, '%x-W%v') AS semana,
                SUM(m.cantidad * e.precio_compra_u) AS perdida
            FROM merma m
            JOIN entrada e 
                ON e.id = m.id_entrada
            WHERE m.fecha >= ?
            GROUP BY semana
            ORDER BY semana ASC
        ", [
            date('Y-m-d', strtotime('-8 weeks'))
        ])->getResultArray();
    }

    // ─────────────────────────────────────────────────────────────
    // PÉRDIDAS POR MES
    // ─────────────────────────────────────────────────────────────
    public function perdidasPorMes()
    {

        return $this->db->query("
            SELECT 
                DATE_FORMAT(m.fecha, '%Y-%m') AS mes,
                SUM(m.cantidad * e.precio_compra_u) AS perdida
            FROM merma m
            JOIN entrada e 
                ON e.id = m.id_entrada
            WHERE m.fecha >= ?
            GROUP BY mes
            ORDER BY mes ASC
        ", [
            date('Y-m-d', strtotime('-6 months'))
        ])->getResultArray();
    }

    // ─────────────────────────────────────────────────────────────
    // FILTRO / BUSCADOR
    // ─────────────────────────────────────────────────────────────
public function filtrar($buscar = null)
{
    if (empty($buscar)) {
        return $this;
    }

    if (is_numeric($buscar)) {
        $this->where('merma.id', (int)$buscar); // ← prefijo de tabla
    } else {
        $this->groupStart()
            ->like('merma.cantidad',   $buscar)
            ->orLike('merma.fecha',    $buscar)
            ->orLike('merma.id_entrada', $buscar)
        ->groupEnd();
    }

    return $this;
}
}