<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega la estrategia «arbol» (orden tomado del árbol de expansión mínima, algoritmo de
 * Prim) a ruta.estrategia. Ver App\Libraries\Rutas\ArbolExpansionMinima.
 *
 * Mientras no se corra, el planeador sigue ofreciendo la opción y la guarda como «manual».
 * Idempotente: volver a correrla deja la columna igual.
 */
class AddEstrategiaArbol extends Migration
{
    private const SIN_ARBOL = "'rapida','prioridad','sin_cuotas','manual','aproximada'";

    public function up()
    {
        if (! $this->db->tableExists('ruta')) {
            return;
        }

        $this->db->query('ALTER TABLE ' . $this->db->prefixTable('ruta')
            . ' MODIFY `estrategia` ENUM(' . self::SIN_ARBOL . ",'arbol') NOT NULL DEFAULT 'rapida'");
    }

    public function down()
    {
        if (! $this->db->tableExists('ruta')) {
            return;
        }

        $tabla = $this->db->prefixTable('ruta');
        $this->db->query("UPDATE {$tabla} SET `estrategia` = 'manual' WHERE `estrategia` = 'arbol'");
        $this->db->query("ALTER TABLE {$tabla} MODIFY `estrategia` ENUM(" . self::SIN_ARBOL . ") NOT NULL DEFAULT 'rapida'");
    }
}
