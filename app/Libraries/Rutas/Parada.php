<?php

namespace App\Libraries\Rutas;

/**
 * Un lugar que la ruta debe visitar.
 *
 * $clave identifica la parada de regreso (p. ej. "p15" = pedido 15) y nunca se manda a Google.
 */
final class Parada
{
    public function __construct(
        public readonly string $clave,
        public readonly Punto $punto,
        public readonly ?string $ventanaFin = null,   // "HH:MM[:SS]" o null
        public readonly int $prioridad = 0,           // mayor = antes (p. ej. cliente preferencial)
    ) {
    }
}
