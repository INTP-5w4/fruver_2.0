<?php

namespace App\Libraries\Rutas;

use DateTimeImmutable;
use InvalidArgumentException;

/** Parámetros de un cálculo de ruta. */
final class OpcionesRuta
{
    public const ESTRATEGIAS = ['rapida', 'prioridad', 'sin_cuotas', 'manual', 'aproximada'];

    /** Vehículo del repartidor → travelMode de Routes API. */
    public const MODOS = [
        'moto'      => 'TWO_WHEELER',
        'auto'      => 'DRIVE',
        'camioneta' => 'DRIVE',
        'bicicleta' => 'BICYCLE',
    ];

    public function __construct(
        public readonly string $estrategia,
        public readonly string $vehiculo,
        public readonly DateTimeImmutable $salida,
        public readonly bool $regresar = true,
        // Punto de regreso si no es el origen (p. ej. reoptimizar desde el GPS y volver al almacén).
        public readonly ?Punto $regresoA = null,
    ) {
        if (! in_array($estrategia, self::ESTRATEGIAS, true)) {
            throw new InvalidArgumentException('Estrategia de ruta no válida.');
        }
        if (! array_key_exists($vehiculo, self::MODOS)) {
            throw new InvalidArgumentException('Vehículo no válido.');
        }
    }

    public function con(string $estrategia): self
    {
        return new self($estrategia, $this->vehiculo, $this->salida, $this->regresar, $this->regresoA);
    }

    /** ¿Google debe reordenar las paradas? (manual y prioridad respetan el orden dado). */
    public function optimizaOrden(): bool
    {
        return in_array($this->estrategia, ['rapida', 'sin_cuotas', 'aproximada'], true);
    }

    public function evitaCuotas(): bool
    {
        return $this->estrategia === 'sin_cuotas';
    }

    public function modoViaje(): string
    {
        return self::MODOS[$this->vehiculo];
    }
}
