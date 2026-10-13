<?php

namespace App\Models;

class Modelo_cliente extends PdoModelo
{
    protected string $tabla         = 'cliente';
    protected string $llavePrimaria = 'id';

    // id_user enlaza al cliente con su cuenta de acceso (tabla users de Shield).
    protected array $camposPermitidos = ['nombre', 'ape_pat', 'ape_mat', 'telefono', 'id_user'];

    /**
     * Lista paginada de clientes con búsqueda opcional.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(?string $buscar = null, int $porPagina = 20): array
    {
        $buscar = trim((string) $buscar);
        $where  = '';
        $params = [];

        if ($buscar !== '') {
            $like  = $this->patronLike($buscar);
            $parte = [];

            if (ctype_digit($buscar)) {
                $parte[]  = 'id = ?';
                $params[] = (int) $buscar;
            }

            $parte[] = 'nombre LIKE ?';
            $parte[] = 'ape_pat LIKE ?';
            $parte[] = 'ape_mat LIKE ?';
            $parte[] = 'telefono LIKE ?';
            array_push($params, $like, $like, $like, $like);

            $where = ' WHERE ' . implode(' OR ', $parte);
        }

        return $this->paginarSql(
            'SELECT id, nombre, ape_pat, ape_mat, telefono FROM cliente' . $where . ' ORDER BY id DESC',
            $params,
            $porPagina
        );
    }

    /** Cliente asociado a una cuenta de usuario (o null si aún no tiene perfil). */
    public function buscarPorUsuario(int $idUsuario): ?array
    {
        return $this->fila('SELECT * FROM cliente WHERE id_user = ? LIMIT 1', [$idUsuario]);
    }

    /**
     * Crea el perfil de cliente de una cuenta de usuario (primer pedido).
     * Lanza PDOException si el trigger no_duplica_cliente detecta un duplicado.
     */
    public function crearParaUsuario(int $idUsuario, string $nombre, string $apePat, ?string $apeMat, string $telefono): int
    {
        return $this->insert([
            'nombre'   => $nombre,
            'ape_pat'  => $apePat,
            'ape_mat'  => ($apeMat === null || $apeMat === '') ? null : $apeMat,
            'telefono' => $telefono,
            'id_user'  => $idUsuario,
        ]);
    }
}
