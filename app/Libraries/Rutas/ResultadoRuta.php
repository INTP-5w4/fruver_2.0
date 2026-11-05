<?php

namespace App\Libraries\Rutas;

use DateTimeImmutable;

/**
 * Resultado de un cálculo de ruta.
 *
 *  - $orden  : claves de las paradas en el orden de visita.
 *  - $tramos : un tramo por cada llegada a parada (mismo orden que $orden) y, si la ruta
 *              regresa al almacén, uno más al final. Cada tramo: [distancia_m, duracion_s].
 */
final class ResultadoRuta
{
    /**
     * @param list<string>                                 $orden
     * @param list<array{distancia_m:int,duracion_s:int}> $tramos
     */
    public function __construct(
        public readonly string $estrategia,
        public readonly string $proveedor,        // google | local
        public readonly array $orden,
        public readonly array $tramos,
        public readonly int $distanciaM,
        public readonly int $duracionS,
        public readonly ?string $polyline = null,
        public readonly bool $regresa = true,
        public readonly ?string $aviso = null,
    ) {
    }

    /**
     * Hora estimada de llegada a cada parada (misma posición que $orden), sumando
     * los tramos y un tiempo fijo de atención en cada parada.
     *
     * @return array<string,string> clave => 'Y-m-d H:i:s'
     */
    public function etas(DateTimeImmutable $salida, int $minutosPorParada): array
    {
        $etas = [];
        $t    = $salida->getTimestamp();

        foreach ($this->orden as $i => $clave) {
            $t += (int) ($this->tramos[$i]['duracion_s'] ?? 0);
            $etas[$clave] = date('Y-m-d H:i:s', $t);
            $t += $minutosPorParada * 60;
        }

        return $etas;
    }

    /** Hora estimada de fin (regreso al almacén o última entrega). */
    public function fin(DateTimeImmutable $salida, int $minutosPorParada): string
    {
        return date('Y-m-d H:i:s', $salida->getTimestamp() + $this->duracionS + count($this->orden) * $minutosPorParada * 60);
    }

    /** Representación para JSON / sesión. */
    public function aArreglo(): array
    {
        return [
            'estrategia'  => $this->estrategia,
            'proveedor'   => $this->proveedor,
            'orden'       => $this->orden,
            'tramos'      => $this->tramos,
            'distancia_m' => $this->distanciaM,
            'duracion_s'  => $this->duracionS,
            'polyline'    => $this->polyline,
            'regresa'     => $this->regresa,
            'aviso'       => $this->aviso,
        ];
    }

    public static function desdeArreglo(array $a): self
    {
        return new self(
            (string) $a['estrategia'],
            (string) $a['proveedor'],
            array_values(array_map('strval', $a['orden'] ?? [])),
            array_values($a['tramos'] ?? []),
            (int) ($a['distancia_m'] ?? 0),
            (int) ($a['duracion_s'] ?? 0),
            isset($a['polyline']) ? (string) $a['polyline'] : null,
            (bool) ($a['regresa'] ?? true),
            isset($a['aviso']) ? (string) $a['aviso'] : null,
        );
    }
}
