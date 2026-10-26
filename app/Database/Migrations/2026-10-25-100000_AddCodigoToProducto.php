<?php

namespace App\Database\Migrations;

use App\Libraries\CodigoProducto;
use CodeIgniter\Database\Migration;

/**
 * Código único de producto (requerimiento #8).
 *
 * Agrega producto.codigo (VARCHAR 30) con un índice UNIQUE y rellena los productos que ya
 * existen, en orden de id, con el mismo generador que usa el alta (App\Libraries\CodigoProducto):
 * categoría + nombre, y consecutivo -02, -03... si dos productos generan el mismo código.
 *
 * La columna admite NULL a propósito: así un producto cargado por fuera de la app (por
 * ejemplo con un INSERT directo) no rompe nada; la app siempre le pone código a los nuevos.
 *
 * Es idempotente: si la columna o el índice ya existen, no los vuelve a crear, y solo
 * rellena los productos que siguen sin código.
 */
class AddCodigoToProducto extends Migration
{
    private const INDICE = 'uq_producto_codigo';

    public function up()
    {
        if (! $this->db->fieldExists('codigo', 'producto')) {
            $this->forge->addColumn('producto', [
                'codigo' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => true,
                    'default'    => null,
                ],
            ]);
        }

        $this->rellenarExistentes();

        if (! $this->existeIndice()) {
            $this->db->query('ALTER TABLE `producto` ADD UNIQUE KEY `' . self::INDICE . '` (`codigo`)');
        }
    }

    public function down()
    {
        if ($this->existeIndice()) {
            $this->db->query('ALTER TABLE `producto` DROP INDEX `' . self::INDICE . '`');
        }

        if ($this->db->fieldExists('codigo', 'producto')) {
            $this->forge->dropColumn('producto', 'codigo');
        }
    }

    /** Asigna código a los productos que no tienen, respetando los que ya lo tienen. */
    private function rellenarExistentes(): void
    {
        $usados = array_map(
            'strtoupper',
            array_column($this->db->query('SELECT codigo FROM producto WHERE codigo IS NOT NULL')->getResultArray(), 'codigo')
        );

        $pendientes = $this->db
            ->query('SELECT id, nombre, categoria FROM producto WHERE codigo IS NULL ORDER BY id ASC')
            ->getResultArray();

        foreach ($pendientes as $p) {
            $base   = CodigoProducto::base((string) $p['categoria'], (string) $p['nombre']);
            $codigo = CodigoProducto::siguienteLibre($base, $usados);

            $usados[] = $codigo;

            $this->db->query('UPDATE producto SET codigo = ? WHERE id = ?', [$codigo, (int) $p['id']]);
        }
    }

    private function existeIndice(): bool
    {
        $filas = $this->db->query(
            'SHOW INDEX FROM `producto` WHERE Key_name = ?',
            [self::INDICE]
        )->getResultArray();

        return $filas !== [];
    }
}
