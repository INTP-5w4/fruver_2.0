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
}
