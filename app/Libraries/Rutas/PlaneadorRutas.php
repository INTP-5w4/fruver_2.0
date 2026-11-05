<?php

namespace App\Libraries\Rutas;

use Config\Rutas as ConfigRutas;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Punto de entrada del motor de rutas.
 *
 * Decide qué optimizador usar (openrouteservice, Google o local), aplica la estrategia
 * y, si el servicio falla, cae al optimizador local para que el repartidor nunca se
 * quede sin ruta.
 *
 * Estrategias que se ofrecen para que el repartidor/admin ESCOJA:
 *  - rapida     : el servicio reordena las paradas (menor tiempo).
 *  - prioridad  : primero quien tiene hora límite y los preferenciales; el servicio solo mide.
 *  - sin_cuotas : como "rapida" pero evitando casetas y autopistas.
 *  - manual     : el orden que el usuario acomodó; el servicio solo mide.
 *  - aproximada : sin servicio externo (línea recta).
 */
final class PlaneadorRutas
{
    public function __construct(
        private readonly ConfigRutas $cfg,
        private readonly ?OptimizadorInterface $servicio,
        private readonly OptimizadorInterface $local,
    ) {
    }

    /** Construye el planeador con el proveedor configurado en .env. */
    public static function crear(?ConfigRutas $cfg = null, ?TransporteHttp $http = null): self
    {
        $cfg ??= config('Rutas');

        return new self($cfg, ServiciosMapa::optimizador($cfg, $http), new OptimizadorLocal($cfg->velocidadLocal));
    }

    /** ¿Hay un servicio externo (openrouteservice o Google)? */
    public function hayServicio(): bool
    {
        return $this->servicio !== null;
    }

    /** Paradas permitidas por ruta según la configuración y el servicio. */
    public function maxParadas(): int
    {
        $tope = match (true) {
            $this->servicio instanceof OptimizadorGoogle => OptimizadorGoogle::MAX_INTERMEDIAS,
            $this->servicio instanceof OptimizadorOrs    => OptimizadorOrs::MAX_PARADAS,
            default                                      => 50,
        };

        return max(1, min($this->cfg->maxParadas, $tope));
    }

    /**
     * Calcula UNA ruta con la estrategia de $op.
     *
     * @param list<Parada> $paradas
     */
    public function calcular(Punto $origen, array $paradas, OpcionesRuta $op): ResultadoRuta
    {
        $paradas = array_values($paradas);
        $this->validarCantidad($paradas);

        if ($op->estrategia === 'prioridad') {
            $paradas = self::porPrioridad($paradas);
        }

        if ($this->servicio === null || $op->estrategia === 'aproximada') {
            return $this->local->calcular($origen, $paradas, $op);
        }

        try {
            return $this->servicio->calcular($origen, $paradas, $op);
        } catch (ErrorProveedorRutas $e) {
            log_message('error', 'Rutas: el servicio de mapas falló, se usa el optimizador local. ' . $e->getMessage());

            $r = $this->local->calcular($origen, $paradas, $op);

            return new ResultadoRuta(
                $r->estrategia, 'local', $r->orden, $r->tramos, $r->distanciaM, $r->duracionS, null, $r->regresa,
                'El servicio de mapas no respondió; se muestra una ruta aproximada (línea recta, sin tráfico).'
            );
        }
    }

    /**
     * Calcula las opciones para comparar. Si el servicio falla en la primera, no se
     * insiste con las demás (evita esperas y gasto de cuota): se devuelve solo la aproximada.
     *
     * @param list<Parada> $paradas
     * @return array<string,ResultadoRuta> estrategia => resultado
     */
    public function opciones(Punto $origen, array $paradas, OpcionesRuta $base): array
    {
        if ($this->servicio === null) {
            return ['aproximada' => $this->calcular($origen, $paradas, $base->con('aproximada'))];
        }

        $rapida = $this->calcular($origen, $paradas, $base->con('rapida'));
        if ($rapida->proveedor === 'local') {
            return ['aproximada' => new ResultadoRuta(
                'aproximada', 'local', $rapida->orden, $rapida->tramos, $rapida->distanciaM, $rapida->duracionS,
                null, $rapida->regresa, $rapida->aviso
            )];
        }

        $opciones = ['rapida' => $rapida];

        if (self::hayPrioridades($paradas) && count($paradas) > 1) {
            $opciones['prioridad'] = $this->calcular($origen, $paradas, $base->con('prioridad'));
        }

        if ($base->vehiculo !== 'bicicleta') {
            $opciones['sin_cuotas'] = $this->calcular($origen, $paradas, $base->con('sin_cuotas'));
        }

        return $opciones;
    }

    /**
     * Orden por prioridad: hora límite más temprana primero (sin hora al final), luego
     * mayor prioridad. Estable: ante empate conserva el orden recibido.
     *
     * @param list<Parada> $paradas
     * @return list<Parada>
     */
    public static function porPrioridad(array $paradas): array
    {
        $indexadas = [];
        foreach (array_values($paradas) as $i => $p) {
            $indexadas[] = [$i, $p];
        }

        usort($indexadas, static function (array $a, array $b): int {
            [$ia, $pa] = $a;
            [$ib, $pb] = $b;

            $va = $pa->ventanaFin ?? '99:99:99';
            $vb = $pb->ventanaFin ?? '99:99:99';

            return [$va, -$pa->prioridad, $ia] <=> [$vb, -$pb->prioridad, $ib];
        });

        return array_map(static fn (array $x) => $x[1], $indexadas);
    }

    /** @param list<Parada> $paradas */
    private static function hayPrioridades(array $paradas): bool
    {
        foreach ($paradas as $p) {
            if ($p->ventanaFin !== null || $p->prioridad > 0) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Parada> $paradas */
    private function validarCantidad(array $paradas): void
    {
        $max = $this->maxParadas();

        if (count($paradas) > $max) {
            throw new InvalidArgumentException("Una ruta admite máximo {$max} paradas. Divide los pedidos en dos rutas.");
        }

        $claves = array_map(static fn (Parada $p) => $p->clave, $paradas);
        if (count($claves) !== count(array_unique($claves))) {
            throw new InvalidArgumentException('Hay paradas repetidas en la ruta.');
        }
    }

    /** Fecha + "HH:MM" en la zona horaria de la aplicación. */
    public static function salida(string $fecha, string $hora, ?string $zona = null): DateTimeImmutable
    {
        $tz = new DateTimeZone($zona ?? (config('App')->appTimezone ?? 'America/Mexico_City'));
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fecha . ' ' . substr($hora, 0, 5), $tz);

        return $dt !== false ? $dt : new DateTimeImmutable('now', $tz);
    }
}
