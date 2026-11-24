<?php

namespace App\Models;

use Throwable;

/**
 * Auditoría de clientes y productos (tabla `auditoria`, migración CreateAuditoriaCatalogos).
 *
 * Solo lectura: las filas las escriben los triggers de la base de datos y la propia base
 * rechaza cualquier UPDATE o DELETE sobre ellas. Este modelo no tiene insert/update/delete
 * propios a propósito.
 */
class Modelo_auditoria extends PdoModelo
{
    protected string $tabla = 'auditoria';

    public const TABLAS = [
        'cliente'  => 'Clientes',
        'producto' => 'Productos',
    ];

    public const ACCIONES = [
        'alta'         => 'Alta',
        'modificacion' => 'Modificación',
        'eliminacion'  => 'Eliminación',
    ];

    private static ?bool $hayTabla = null;

    public function tablaExiste(): bool
    {
        if (self::$hayTabla === null) {
            try {
                $this->filas('SELECT id FROM auditoria LIMIT 0');
                self::$hayTabla = true;
            } catch (Throwable) {
                self::$hayTabla = false;
            }
        }

        return self::$hayTabla;
    }

    /**
     * Movimientos, del más reciente al más antiguo, con filtros opcionales.
     * Deja el paginador en $this->pager.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(?string $tabla, ?int $idRegistro, string $buscar = '', int $porPagina = 30): array
    {
        if (! $this->tablaExiste()) {
            return [];
        }

        $donde  = [];
        $params = [];

        if ($tabla !== null && isset(self::TABLAS[$tabla])) {
            $donde[]  = 'tabla = ?';
            $params[] = $tabla;
        }
        if ($idRegistro !== null && $idRegistro > 0) {
            $donde[]  = 'id_registro = ?';
            $params[] = $idRegistro;
        }
        if ($buscar !== '') {
            $donde[]  = '(detalle LIKE ? OR usuario LIKE ?)';
            $params[] = $this->patronLike($buscar);
            $params[] = $this->patronLike($buscar);
        }

        $sql = 'SELECT id, tabla, id_registro, accion, detalle, usuario, fecha FROM auditoria'
            . ($donde !== [] ? ' WHERE ' . implode(' AND ', $donde) : '')
            . ' ORDER BY id DESC';

        return $this->paginarSql($sql, $params, $porPagina);
    }
}
