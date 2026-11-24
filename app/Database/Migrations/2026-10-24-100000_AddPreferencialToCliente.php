<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Estado de cliente preferencial MODIFICABLE (reversible) y con motivo.
 *
 * Hasta ahora "preferencial" se calculaba solo (compras >= umbral). Ahora cada cliente tiene
 * un modo:
 *   - 'automatico' (por omisión): se decide por sus compras, como antes.
 *   - 'si'        : preferencial siempre, aunque no llegue al umbral.
 *   - 'no'        : nunca preferencial, aunque tenga muchas compras.
 * Volver a 'automatico' deshace cualquier cambio manual. Cuando el modo es 'si' o 'no' se
 * guarda por qué, cuándo y quién lo cambió. Los clientes existentes quedan en 'automatico',
 * así que nada cambia hasta que alguien lo modifique.
 *
 * Es idempotente: si una columna ya existe, no la vuelve a crear.
 */
class AddPreferencialToCliente extends Migration
{
    public function up()
    {
        $campos = [
            'preferencial_modo' => [
                'type'       => 'ENUM',
                'constraint' => ['automatico', 'si', 'no'],
                'null'       => false,
                'default'    => 'automatico',
            ],
            'preferencial_motivo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
            ],
            'preferencial_fecha' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'preferencial_por' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
            ],
        ];

        foreach ($campos as $nombre => $definicion) {
            if (! $this->db->fieldExists($nombre, 'cliente')) {
                $this->forge->addColumn('cliente', [$nombre => $definicion]);
            }
        }
    }

    public function down()
    {
        foreach (['preferencial_por', 'preferencial_fecha', 'preferencial_motivo', 'preferencial_modo'] as $nombre) {
            if ($this->db->fieldExists($nombre, 'cliente')) {
                $this->forge->dropColumn('cliente', $nombre);
            }
        }
    }
}
