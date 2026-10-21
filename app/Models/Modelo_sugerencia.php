<?php

namespace App\Models;

use InvalidArgumentException;

/**
 * Buzón de sugerencias. Todo el SQL va con sentencias preparadas de PdoModelo.
 *
 * `fecha_creacion` no está en la lista blanca: la asigna la base de datos
 * (DEFAULT CURRENT_TIMESTAMP) y ni el usuario ni el controlador pueden fijarla.
 */
class Modelo_sugerencia extends PdoModelo
{
    protected string $tabla         = 'sugerencia';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = [
        'titulo', 'descripcion', 'valoracion', 'id_usuario', 'usuario', 'rol',
    ];

    /** Deben coincidir con el ENUM de la tabla. */
    public const VALORACIONES = ['No muy importante', 'Importante', 'Muy importante'];

    public const TITULO_MAX      = 100;
    public const DESCRIPCION_MAX = 2000;

    /** Órdenes permitidos en la lista (lista blanca: nunca llega SQL del usuario). */
    public const ORDENES = ['recientes', 'importancia'];

    /**
     * Guarda una sugerencia y devuelve su id.
     * Repite las reglas de negocio más importantes como última defensa: si algún día otro
     * controlador llama a este método sin validar, la base nunca recibe un valor fuera de lista.
     *
     * @throws InvalidArgumentException si la valoración o las longitudes no son válidas.
     */
    public function guardar(array $datos): int
    {
        if (! in_array($datos['valoracion'] ?? null, self::VALORACIONES, true)) {
            throw new InvalidArgumentException('Valoración no válida.');
        }

        if (mb_strlen((string) ($datos['titulo'] ?? '')) > self::TITULO_MAX
            || mb_strlen((string) ($datos['descripcion'] ?? '')) > self::DESCRIPCION_MAX) {
            throw new InvalidArgumentException('Texto demasiado largo.');
        }

        return $this->insert($datos);
    }

    // ─────────────────────────────────────────────────────────────
    // LECTURA (personal)
    // ─────────────────────────────────────────────────────────────

    /**
     * Lista paginada de sugerencias con búsqueda, filtro por valoración y orden.
     * Todo valor externo viaja como parámetro; el ORDER BY sale de una lista blanca.
     * Deja el paginador en $this->pager.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(string $buscar = '', string $valoracion = '', string $orden = 'recientes', int $porPagina = 20): array
    {
        $condiciones = [];
        $params      = [];
        $buscar      = trim($buscar);

        if ($buscar !== '') {
            $like          = $this->patronLike($buscar);
            $condiciones[] = '(titulo LIKE ? OR descripcion LIKE ? OR usuario LIKE ? OR rol LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        if (in_array($valoracion, self::VALORACIONES, true)) {
            $condiciones[] = 'valoracion = ?';
            $params[]      = $valoracion;
        }

        $where = $condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones);

        // CASE en lugar de ORDER BY valoracion: el ENUM ordena por posición, no por importancia real.
        $orderBy = $orden === 'importancia'
            ? "CASE valoracion WHEN 'Muy importante' THEN 1 WHEN 'Importante' THEN 2 ELSE 3 END ASC, fecha_creacion DESC, id DESC"
            : 'fecha_creacion DESC, id DESC';

        return $this->paginarSql(
            "SELECT * FROM sugerencia{$where} ORDER BY {$orderBy}",
            $params,
            $porPagina
        );
    }

    /**
     * Conteo por valoración y total, para las etiquetas de resumen de la lista.
     *
     * @return array{total:int, 'No muy importante':int, 'Importante':int, 'Muy importante':int}
     */
    public function conteos(): array
    {
        $conteos = array_fill_keys(self::VALORACIONES, 0);

        foreach ($this->filas('SELECT valoracion, COUNT(*) AS n FROM sugerencia GROUP BY valoracion') as $fila) {
            if (isset($conteos[$fila['valoracion']])) {
                $conteos[$fila['valoracion']] = (int) $fila['n'];
            }
        }

        return ['total' => array_sum($conteos)] + $conteos;
    }
}
