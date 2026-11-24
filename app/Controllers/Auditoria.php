<?php

namespace App\Controllers;

use App\Models\Modelo_auditoria;

/**
 * Consulta de la auditoría de clientes y productos (solo lectura).
 * Los registros los genera la base de datos; aquí no hay acciones que los cambien.
 */
class Auditoria extends BaseController
{
    public function index()
    {
        $tabla = $this->request->getGet('tabla');
        $tabla = is_string($tabla) && isset(Modelo_auditoria::TABLAS[$tabla]) ? $tabla : null;

        $id = $this->request->getGet('id');
        $id = is_string($id) && ctype_digit($id) && (int) $id > 0 ? (int) $id : null;

        $buscar = $this->textoBusqueda();
        $modelo = new Modelo_auditoria();

        return view('auditoria', [
            'movimientos' => $modelo->listar($tabla, $id, $buscar),
            'pager'       => $modelo->pager,
            'instalada'   => $modelo->tablaExiste(),
            'tabla'       => $tabla,
            'idRegistro'  => $id,
            'buscar'      => $buscar,
        ]);
    }
}
