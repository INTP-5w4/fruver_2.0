<?php

namespace App\Libraries;

use CodeIgniter\Session\Session;
use DomainException;

/**
 * Carrito de la tienda, guardado en la sesión del servidor.
 *
 * Solo guarda "qué producto y cuántas unidades" (id => cantidad). Precios, unidades y
 * stock se leen SIEMPRE de la base de datos cuando se muestra o se confirma el carrito,
 * así que nadie puede cambiar un precio manipulando lo que envía el navegador.
 *
 * No hace consultas: es estado de sesión, no acceso a datos (el MVC se respeta).
 */
final class Carrito
{
    private const LLAVE = 'carrito_tienda';

    public const MAX_LINEAS   = 50;
    public const MAX_CANTIDAD = 999;

    private Session $sesion;

    public function __construct(?Session $sesion = null)
    {
        $this->sesion = $sesion ?? session();
    }

    /** @return array<int,int> id_producto => cantidad */
    public function lineas(): array
    {
        $guardado = $this->sesion->get(self::LLAVE);
        $limpio   = [];

        if (is_array($guardado)) {
            foreach ($guardado as $id => $cantidad) {
                $id       = (int) $id;
                $cantidad = (int) $cantidad;

                if ($id > 0 && $cantidad > 0) {
                    $limpio[$id] = min($cantidad, self::MAX_CANTIDAD);
                }
            }
        }

        return $limpio;
    }

    public function cantidadDe(int $idProducto): int
    {
        return $this->lineas()[$idProducto] ?? 0;
    }

    public function totalPiezas(): int
    {
        return array_sum($this->lineas());
    }

    /** Suma $cantidad a lo que ya hay de ese producto. */
    public function agregar(int $idProducto, int $cantidad = 1): void
    {
        $this->fijar($idProducto, $this->cantidadDe($idProducto) + $cantidad);
    }

    /** Deja exactamente $cantidad de ese producto (0 lo quita). */
    public function fijar(int $idProducto, int $cantidad): void
    {
        if ($idProducto < 1) {
            throw new DomainException('Producto no válido.');
        }

        $lineas = $this->lineas();

        if ($cantidad <= 0) {
            unset($lineas[$idProducto]);
        } else {
            if (! isset($lineas[$idProducto]) && count($lineas) >= self::MAX_LINEAS) {
                throw new DomainException('Tu carrito admite máximo ' . self::MAX_LINEAS . ' productos distintos.');
            }

            $lineas[$idProducto] = min($cantidad, self::MAX_CANTIDAD);
        }

        $this->sesion->set(self::LLAVE, $lineas);
    }

    public function quitar(int $idProducto): void
    {
        $this->fijar($idProducto, 0);
    }

    public function vaciar(): void
    {
        $this->sesion->remove(self::LLAVE);
    }
}
