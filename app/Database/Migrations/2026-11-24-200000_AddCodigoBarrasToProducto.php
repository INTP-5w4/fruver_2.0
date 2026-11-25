<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCodigoBarrasToProducto extends Migration
{
    private const INDICE = 'uq_producto_codigo_barras';

    public function up()
    {
        if (! $this->db->fieldExists('codigo_barras', 'producto')) {
            $this->forge->addColumn('producto', [
                'codigo_barras' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'codigo',
                ],
            ]);
        }

        if (! $this->existeIndice()) {
            $this->db->query('ALTER TABLE `producto` ADD UNIQUE KEY `' . self::INDICE . '` (`codigo_barras`)');
        }
    }

    public function down()
    {
        if ($this->existeIndice()) {
            $this->db->query('ALTER TABLE `producto` DROP INDEX `' . self::INDICE . '`');
        }

        if ($this->db->fieldExists('codigo_barras', 'producto')) {
            $this->forge->dropColumn('producto', 'codigo_barras');
        }
    }

    private function existeIndice(): bool
    {
        $r = $this->db->query("SHOW INDEX FROM `producto` WHERE Key_name = '" . self::INDICE . "'");

        return $r->getNumRows() > 0;
    }
}
