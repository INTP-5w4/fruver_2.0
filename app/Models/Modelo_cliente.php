<?php

namespace App\Models;

class Modelo_cliente extends PdoModelo
{
    protected string $tabla         = 'cliente';
    protected string $llavePrimaria = 'id';

    // id_user enlaza al cliente con su cuenta de acceso (tabla users de Shield).
    protected array $camposPermitidos = ['nombre', 'ape_pat', 'ape_mat', 'telefono', 'id_user'];

    /** PROVISIONAL: compras necesarias para ser cliente preferencial (confirmar con el cliente). */
    public const UMBRAL_PREFERENCIAL = 5;

    /** Estados del pedido que cuentan como una compra realizada. */
    public const ESTADOS_COMPRA = ['pedido_entregado', 'pedido_a_credito', 'pedido_pagado'];

    /** Lista de estados de compra lista para usarse dentro de un IN (...). Son constantes, no entrada del usuario. */
    private function estadosCompraSql(): string
    {
        return "'" . implode("','", self::ESTADOS_COMPRA) . "'";
    }

    /**
     * Lista paginada de clientes con búsqueda opcional.
     * Cada fila incluye "compras" (int) y "es_preferencial" (bool).
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

        $filas = $this->paginarSql(
            'SELECT cliente.id, cliente.nombre, cliente.ape_pat, cliente.ape_mat, cliente.telefono,
                    (SELECT COUNT(*)
                       FROM pedido p
                      WHERE p.id_cliente = cliente.id
                        AND (SELECT e.estado FROM estatus e
                              WHERE e.id_pedido = p.id
                              ORDER BY e.id DESC LIMIT 1) IN (' . $this->estadosCompraSql() . ')) AS compras
               FROM cliente' . $where . ' ORDER BY cliente.id DESC',
            $params,
            $porPagina
        );

        return array_map(function (array $fila): array {
            $fila['compras']         = (int) $fila['compras'];
            $fila['es_preferencial'] = $fila['compras'] >= self::UMBRAL_PREFERENCIAL;

            return $fila;
        }, $filas);
    }

    /** Número de compras de un cliente (pedidos cuyo último estatus cuenta como compra). */
    public function comprasDeCliente(int $idCliente): int
    {
        $fila = $this->fila(
            'SELECT COUNT(*) AS compras
               FROM pedido p
              WHERE p.id_cliente = ?
                AND (SELECT e.estado FROM estatus e
                      WHERE e.id_pedido = p.id
                      ORDER BY e.id DESC LIMIT 1) IN (' . $this->estadosCompraSql() . ')',
            [$idCliente]
        );

        return (int) ($fila['compras'] ?? 0);
    }

    public function esPreferencial(int $idCliente): bool
    {
        return $this->comprasDeCliente($idCliente) >= self::UMBRAL_PREFERENCIAL;
    }

    /**
     * Ids de todos los clientes preferenciales (una sola consulta).
     * Útil para marcarlos en el formulario de pedidos (requerimiento #7).
     *
     * @return list<int>
     */
    public function idsPreferenciales(): array
    {
        $filas = $this->filas(
            'SELECT p.id_cliente
               FROM pedido p
              WHERE (SELECT e.estado FROM estatus e
                      WHERE e.id_pedido = p.id
                      ORDER BY e.id DESC LIMIT 1) IN (' . $this->estadosCompraSql() . ')
           GROUP BY p.id_cliente
             HAVING COUNT(*) >= ?',
            [self::UMBRAL_PREFERENCIAL]
        );

        return array_map('intval', array_column($filas, 'id_cliente'));
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