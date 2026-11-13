<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Consumo de combustible y costo de traslado (ver App\Libraries\Rutas\Combustible).
 *
 *  repartidor.rendimiento_km_l  km que recorre su vehículo con un litro (null = típico del vehículo)
 *  repartidor.combustible       magna (verde) | premium (roja) | diesel
 *  ruta.litros                  litros estimados de la ruta al planearla
 *  ruta.costo_combustible       costo estimado (MXN) con el precio de ese día
 *  ruta.precio_litro            precio por litro usado en el cálculo (queda como constancia)
 *
 * Idempotente: no vuelve a crear lo que ya existe.
 */
class AddCombustible extends Migration
{
    public function up()
    {
        $repartidor = [];
        if (! $this->db->fieldExists('rendimiento_km_l', 'repartidor')) {
            $repartidor['rendimiento_km_l'] = ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true, 'default' => null];
        }
        if (! $this->db->fieldExists('combustible', 'repartidor')) {
            $repartidor['combustible'] = ['type' => 'ENUM', 'constraint' => ['magna', 'premium', 'diesel'], 'null' => true, 'default' => null];
        }
        if ($repartidor !== []) {
            $this->forge->addColumn('repartidor', $repartidor);
        }

        $ruta = [];
        if (! $this->db->fieldExists('litros', 'ruta')) {
            $ruta['litros'] = ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true, 'default' => null];
        }
        if (! $this->db->fieldExists('costo_combustible', 'ruta')) {
            $ruta['costo_combustible'] = ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true, 'default' => null];
        }
        if (! $this->db->fieldExists('precio_litro', 'ruta')) {
            $ruta['precio_litro'] = ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => true, 'default' => null];
        }
        if ($ruta !== [] && $this->db->tableExists('ruta')) {
            $this->forge->addColumn('ruta', $ruta);
        }
    }

    public function down()
    {
        foreach (['rendimiento_km_l', 'combustible'] as $c) {
            if ($this->db->fieldExists($c, 'repartidor')) {
                $this->forge->dropColumn('repartidor', $c);
            }
        }
        foreach (['litros', 'costo_combustible', 'precio_litro'] as $c) {
            if ($this->db->tableExists('ruta') && $this->db->fieldExists($c, 'ruta')) {
                $this->forge->dropColumn('ruta', $c);
            }
        }
    }
}
