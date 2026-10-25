<?php

namespace App\Models;

class Modelo_cliente extends PdoModelo
{
    protected string $tabla         = 'cliente';
    protected string $llavePrimaria = 'id';

    // id_user enlaza al cliente con su cuenta de acceso (tabla users de Shield).
    protected array $camposPermitidos = [
        'nombre', 'ape_pat', 'ape_mat', 'telefono', 'id_user',
        'preferencial_modo', 'preferencial_motivo', 'preferencial_fecha', 'preferencial_por',
    ];

    /**
     * Modo del estado preferencial (columna cliente.preferencial_modo):
     *  - automatico: se decide por las compras (>= UMBRAL_PREFERENCIAL)
     *  - si:         preferencial siempre (cambio manual, con motivo)
     *  - no:         nunca preferencial (cambio manual, con motivo)
     */
    public const MODOS_PREFERENCIAL = ['automatico', 'si', 'no'];

    /** Largo del motivo (cliente.preferencial_motivo es VARCHAR(255)). */
    public const MOTIVO_MIN = 5;
    public const MOTIVO_MAX = 255;

    private static ?bool $conColumnasPreferencial = null;

    /**
     * ¿Ya existen las columnas preferencial_* (migración 2026-10-24-100000)?
     * Si todavía no se corrió la migración la aplicación sigue funcionando como antes
     * (preferencial automático) en lugar de fallar.
     */
    public function tieneColumnasPreferencial(): bool
    {
        if (self::$conColumnasPreferencial === null) {
            try {
                $this->filas('SELECT preferencial_modo FROM cliente LIMIT 1');
                self::$conColumnasPreferencial = true;
            } catch (\Throwable) {
                self::$conColumnasPreferencial = false;
            }
        }

        return self::$conColumnasPreferencial;
    }

    /** PROVISIONAL: compras necesarias para ser cliente preferencial (confirmar con el cliente). */
    public const UMBRAL_PREFERENCIAL = 5;

    /**
     * PROVISIONAL: porcentaje de descuento del precio preferencial (confirmar con el cliente).
     * Se propone en las líneas del pedido de un cliente preferencial; el personal puede
     * cambiar el precio de cada línea a mano (no se bloquea).
     */
    public const DESCUENTO_PREFERENCIAL = 5.0;

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

        $extra = $this->tieneColumnasPreferencial()
            ? ', cliente.preferencial_modo, cliente.preferencial_motivo, cliente.preferencial_fecha, cliente.preferencial_por'
            : '';

        $filas = $this->paginarSql(
            'SELECT cliente.id, cliente.nombre, cliente.ape_pat, cliente.ape_mat, cliente.telefono' . $extra . ',
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
            $fila['compras']           = (int) $fila['compras'];
            $fila['preferencial_modo'] = $fila['preferencial_modo'] ?? 'automatico';
            $fila['es_preferencial']   = self::calculaPreferencial($fila['preferencial_modo'], $fila['compras']);

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

    /** Decide si un cliente es preferencial según su modo y su número de compras. */
    public static function calculaPreferencial(?string $modo, int $compras): bool
    {
        return match ($modo) {
            'si'    => true,
            'no'    => false,
            default => $compras >= self::UMBRAL_PREFERENCIAL,
        };
    }

    public function esPreferencial(int $idCliente): bool
    {
        $modo = $this->tieneColumnasPreferencial()
            ? ($this->fila('SELECT preferencial_modo FROM cliente WHERE id = ?', [$idCliente])['preferencial_modo'] ?? null)
            : null;

        if ($modo === 'si' || $modo === 'no') {
            return $modo === 'si';
        }

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
        if ($this->tieneColumnasPreferencial()) {
            $filas = $this->filas(
                "SELECT c.id
                   FROM cliente c
                  WHERE c.preferencial_modo = 'si'
                     OR (c.preferencial_modo = 'automatico'
                         AND (SELECT COUNT(*)
                                FROM pedido p
                               WHERE p.id_cliente = c.id
                                 AND (SELECT e.estado FROM estatus e
                                       WHERE e.id_pedido = p.id
                                       ORDER BY e.id DESC LIMIT 1) IN (" . $this->estadosCompraSql() . ")) >= ?)",
                [self::UMBRAL_PREFERENCIAL]
            );

            return array_map('intval', array_column($filas, 'id'));
        }

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

    /**
     * Cambia el estado preferencial de un cliente. Es reversible: el modo 'automatico'
     * quita cualquier cambio manual (y borra motivo, fecha y autor).
     *
     * @param string      $modo   'automatico' | 'si' | 'no'
     * @param string|null $motivo obligatorio (MOTIVO_MIN..MOTIVO_MAX) cuando el modo es 'si' o 'no'
     * @param string|null $por    quien hace el cambio (nombre de usuario)
     *
     * @return bool false si el cliente no existe
     *
     * @throws \DomainException si la migración no se ha corrido o los datos no son válidos
     */
    public function cambiarPreferencial(int $idCliente, string $modo, ?string $motivo, ?string $por): bool
    {
        if (! $this->tieneColumnasPreferencial()) {
            throw new \DomainException('Falta ejecutar la migración de cliente preferencial (php spark migrate).');
        }

        if (! in_array($modo, self::MODOS_PREFERENCIAL, true)) {
            throw new \DomainException('Modo de cliente preferencial no válido.');
        }

        if ($this->fila('SELECT id FROM cliente WHERE id = ?', [$idCliente]) === null) {
            return false;
        }

        if ($modo === 'automatico') {
            $datos = [
                'preferencial_modo'   => 'automatico',
                'preferencial_motivo' => null,
                'preferencial_fecha'  => null,
                'preferencial_por'    => null,
            ];
        } else {
            $motivo = trim((string) $motivo);
            $largo  = mb_strlen($motivo);

            if ($largo < self::MOTIVO_MIN || $largo > self::MOTIVO_MAX) {
                throw new \DomainException(
                    'Escribe el motivo (entre ' . self::MOTIVO_MIN . ' y ' . self::MOTIVO_MAX . ' caracteres).'
                );
            }

            $datos = [
                'preferencial_modo'   => $modo,
                'preferencial_motivo' => $motivo,
                'preferencial_fecha'  => date('Y-m-d H:i:s'),
                'preferencial_por'    => $por === null || $por === '' ? null : mb_substr($por, 0, 100),
            ];
        }

        $this->update($idCliente, $datos);

        return true;
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