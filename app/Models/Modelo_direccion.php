<?php

namespace App\Models;

class Modelo_direccion extends PdoModelo
{
    protected string $tabla         = 'direccion';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = ['colonia', 'calle', 'numero', 'municipio', 'estado', 'id_cliente'];

    /**
     * Lista paginada de direcciones junto con el nombre de su cliente.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(?string $buscar = '', int $porPagina = 20): array
    {
        $buscar = trim((string) $buscar);
        $where  = '';
        $params = [];

        if ($buscar !== '') {
            $like  = $this->patronLike($buscar);
            $where = ' WHERE (c.nombre LIKE ? OR d.id LIKE ? OR c.ape_pat LIKE ? OR d.colonia LIKE ?'
                   . ' OR d.calle LIKE ? OR d.municipio LIKE ? OR d.estado LIKE ?)';
            $params = array_fill(0, 7, $like);
        }

        return $this->paginarSql(
            'SELECT d.*, c.nombre, c.ape_pat, c.ape_mat
               FROM direccion d
               JOIN cliente c ON c.id = d.id_cliente' . $where . '
              ORDER BY d.id DESC',
            $params,
            $porPagina
        );
    }
}
