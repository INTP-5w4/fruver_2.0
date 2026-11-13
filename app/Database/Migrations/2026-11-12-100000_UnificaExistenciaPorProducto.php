<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Una sola fila de existencia por producto.
 *
 * El problema: el trigger `after_insert_entrada` suma la entrada nueva con
 * "INSERT ... ON DUPLICATE KEY UPDATE e_total = e_total + ...", pero existencia.id_producto
 * no tenía índice UNIQUE, así que nunca había "duplicado": cada entrada creaba una fila
 * NUEVA. Y el sistema lee el stock de la fila más reciente. Resultado: con 3800 kg de kiwi
 * y una entrada nueva de 400 kg, el pedido solo veía 400.
 *
 * La migración:
 *  1. Fusiona las filas repetidas de cada producto en la más antigua, SUMANDO e_total,
 *     e_bloqueado y e_venta (la entrada nueva se agrega a lo que ya había).
 *  2. Borra las filas sobrantes.
 *  3. Agrega el índice UNIQUE en id_producto: desde ahí el ON DUPLICATE KEY del trigger
 *     suma sobre la fila existente, como siempre se pretendió.
 *
 * Todo en una transacción para el paso 1-2. Es idempotente: si ya no hay repetidos ni
 * falta el índice, no hace nada.
 */
class UnificaExistenciaPorProducto extends Migration
{
    private const INDICE = 'uq_existencia_producto';

    public function up()
    {
        $this->db->transStart();

        $repetidos = $this->db->query(
            'SELECT id_producto, MIN(id) AS conservar,
                    SUM(e_total) AS e_total, SUM(e_bloqueado) AS e_bloqueado, SUM(COALESCE(e_venta, 0)) AS e_venta
               FROM existencia
           GROUP BY id_producto
             HAVING COUNT(*) > 1'
        )->getResultArray();

        foreach ($repetidos as $r) {
            $this->db->query(
                'UPDATE existencia SET e_total = ?, e_bloqueado = ?, e_venta = ? WHERE id = ?',
                [(int) $r['e_total'], (int) $r['e_bloqueado'], (int) $r['e_venta'], (int) $r['conservar']]
            );
            $this->db->query(
                'DELETE FROM existencia WHERE id_producto = ? AND id <> ?',
                [(int) $r['id_producto'], (int) $r['conservar']]
            );
        }

        $this->db->transComplete();

        if (! $this->existeIndice()) {
            $this->db->query('ALTER TABLE `existencia` ADD UNIQUE KEY `' . self::INDICE . '` (`id_producto`)');
        }
    }

    public function down()
    {
        // Solo se quita el índice: las filas fusionadas no se pueden volver a separar.
        if ($this->existeIndice()) {
            $this->db->query('ALTER TABLE `existencia` DROP INDEX `' . self::INDICE . '`');
        }
    }

    private function existeIndice(): bool
    {
        return $this->db->query('SHOW INDEX FROM `existencia` WHERE Key_name = ?', [self::INDICE])->getResultArray() !== [];
    }
}
