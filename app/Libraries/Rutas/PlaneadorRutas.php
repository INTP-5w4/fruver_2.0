<?php

namespace App\Libraries\Rutas;

use Config\Rutas as ConfigRutas;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Punto de entrada del motor de rutas.
 *
 * Decide qué optimizador usar (Google o local), aplica la estrategia y, si Google
 * falla, cae al optimizador local para que el repartidor nunca se quede sin ruta.
 *
 * Estrategias que se ofrecen para que el repartidor/admin ESCOJA:
 *  - rapida     : Google reordena las paradas (menor tiempo, con tráfico).
 *  - prioridad  : primero quien tiene hora límite y los preferenciales; Google solo mide.
 *  - sin_cuotas : como "rapida" pero evitando casetas y autopistas.
 *  - manual     : el orden que el usuario acomodó; Google solo mide.
 *  - aproximada : sin Google (línea recta).
 */
final class PlaneadorRutas
{
    public function __construct(
        private readonly ConfigRutas $cfg,
        private readonly ?OptimizadorInterface $google,
        private readonly OptimizadorInterface $local,
    ) {
    }

    /** Construye el planeador con la configuración de .env. */
    public static function crear(?ConfigRutas $cfg = null, ?TransporteHttp $http = null): self
    {
        $cfg ??= config('Rutas');

        $google = $cfg->conGoogle()
            ? new OptimizadorGoogle($cfg->llaveServidor, $http ?? new TransporteCurl(), $cfg->idioma, $cfg->region, $cfg->tiempoEsperaSeg)
            : null;

        return new self($cfg, $google, new OptimizadorLocal($cfg->velocidadLocal));
    }

    public function hayGoogle(): bool
    {
        return $this->google !== null;
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

        if ($this->google === null || $op->estrategia === 'aproximada') {
            return $this->local->calcular($origen, $paradas, $op);
        }

        try {
            return $this->google->calcular($origen, $paradas, $op);
        } catch (ErrorProveedorRutas $e) {
            log_message('error', 'Rutas: Google falló, se usa el optimizador local. ' . $e->getMessage());

            $r = $this->local->calcular($origen, $paradas, $op);

            return new ResultadoRuta(
                $r->estrategia, 'local', $r->orden, $r->tramos, $r->distanciaM, $r->duracionS, null, $r->regresa,
                'Google Maps no respondió; se muestra una ruta aproximada (línea recta, sin tráfico).'
            );
        }
    }

    /**
     * Calcula las opciones para comparar. Si Google falla en la primera, no se
     * insiste con las demás (evita esperas y cobros): se devuelve solo la aproximada.
     *
     * @param list<Parada> $paradas
     * @return array<string,ResultadoRuta> estrategia => resultado
     */
    public function opciones(Punto $origen, array $paradas, OpcionesRuta $base): array
    {
        if ($this->google === null) {
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
        $max = min($this->cfg->maxParadas, OptimizadorGoogle::MAX_INTERMEDIAS);

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
