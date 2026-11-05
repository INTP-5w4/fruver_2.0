<?php

namespace App\Models;

use App\Libraries\Rutas\Punto;

/** Puntos de salida de las rutas (bodega / sucursal). */
class Modelo_almacen extends PdoModelo
{
    protected string $tabla         = 'almacen';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = ['nombre', 'direccion', 'place_id', 'lat', 'lng', 'activo'];

    /** @return list<array<string,mixed>> */
    public function activos(): array
    {
        return $this->filas('SELECT * FROM almacen WHERE activo = 1 ORDER BY id ASC');
    }

    public function principal(): ?array
    {
        return $this->fila('SELECT * FROM almacen WHERE activo = 1 ORDER BY id ASC LIMIT 1');
    }

    public static function punto(array $almacen): ?Punto
    {
        return Punto::desde($almacen['lat'] ?? null, $almacen['lng'] ?? null);
    }
}
