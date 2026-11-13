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
 *                 Los pedidos normales que quedan DE PASO hacia un preferencial (desviarse
 *                 cuesta poco) se entregan al pasar; el resto va al final, por cercanía.
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

        $dePaso = [];
        if ($op->estrategia === 'prioridad') {
            [$paradas, $dePaso] = self::prioridadConParadasDePaso(
                $origen, $paradas, $this->cfg->desvioMaxKm, $this->cfg->desvioMaxPorcentaje
            );
        }

        $resultado = $this->calcularOrden($origen, $paradas, $op);

        return $dePaso === [] ? $resultado : self::conAviso(
            $resultado,
            'Se entregan de paso, camino a un cliente preferente: #'
            . implode(', #', array_map(static fn (string $c): string => ltrim($c, 'p'), $dePaso)) . '.'
        );
    }

    /** @param list<Parada> $paradas */
    private function calcularOrden(Punto $origen, array $paradas, OpcionesRuta $op): ResultadoRuta
    {
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

    /**
     * Orden "por prioridad" que no hace dar vueltas de más:
     *
     *  1. Primero las urgentes (hora límite y preferenciales), en el orden de porPrioridad().
     *  2. Un pedido normal se entrega ANTES de un preferencial si queda de paso: meterlo en el
     *     tramo hacia ese preferencial agrega como máximo min($maxKm, $maxPct % del tramo).
     *     Ej.: Grajales → Puebla (~80 km) pasando por Acajete: los pedidos de Acajete se dejan
     *     al pasar, en lugar de ir hasta Puebla y regresar.
     *     Solo se intercalan antes de un preferencial SIN hora límite: una hora límite es una
     *     promesa y no se arriesga por una parada extra.
     *  3. Los normales que no quedaron de paso van al final, por cercanía (vecino más cercano
     *     desde la última urgente).
     *
     * Distancias en línea recta × FACTOR_CALLES (lo mismo que el optimizador local): basta
     * para decidir qué está "en el camino"; la ruta real la mide después el servicio de mapas.
     *
     * @param list<Parada> $paradas
     * @return array{0:list<Parada>,1:list<string>} [orden, claves que se intercalaron de paso]
     */
    public static function prioridadConParadasDePaso(Punto $origen, array $paradas, float $maxKm = 5.0, float $maxPct = 15.0): array
    {
        $urgentes = [];
        $normales = [];
        foreach (array_values($paradas) as $p) {
            if ($p->ventanaFin !== null || $p->prioridad > 0) {
                $urgentes[] = $p;
            } else {
                $normales[] = $p;
            }
        }

        if ($urgentes === [] || $normales === []) {
            return [self::porPrioridad($paradas), []];
        }

        $urgentes = self::porPrioridad($urgentes);
        $d        = static fn (Punto $a, Punto $b): float => $a->distanciaA($b) * OptimizadorLocal::FACTOR_CALLES;

        // Tramos origen → u1 → u2 …; cada tramo guarda las paradas que se le intercalan.
        $tramos = [];
        $previo = $origen;
        foreach ($urgentes as $u) {
            $largo    = $d($previo, $u->punto);
            $tramos[] = [
                'desde'        => $previo,
                'hacia'        => $u,
                'intercaladas' => [],
                // Metros extra permitidos: solo antes de un preferencial sin hora límite.
                'presupuesto'  => $u->ventanaFin === null ? min($maxKm * 1000, $largo * $maxPct / 100) : 0.0,
            ];
            $previo = $u->punto;
        }

        $pendientes = $normales;
        $dePaso     = [];

        // Voraz: en cada vuelta se intercala la parada (y el hueco) que menos desvío cuesta.
        while (true) {
            $mejor = null;
            foreach ($pendientes as $iN => $n) {
                foreach ($tramos as $iT => $t) {
                    if ($t['presupuesto'] <= 0) {
                        continue;
                    }
                    $nodos = [$t['desde'], ...array_map(static fn (Parada $x) => $x->punto, $t['intercaladas']), $t['hacia']->punto];
                    for ($k = 0; $k < count($nodos) - 1; $k++) {
                        $extra = $d($nodos[$k], $n->punto) + $d($n->punto, $nodos[$k + 1]) - $d($nodos[$k], $nodos[$k + 1]);
                        if ($extra <= $t['presupuesto'] + 0.01 && ($mejor === null || $extra < $mejor['extra'])) {
                            $mejor = ['n' => $iN, 't' => $iT, 'k' => $k, 'extra' => max(0.0, $extra)];
                        }
                    }
                }
            }

            if ($mejor === null) {
                break;
            }

            $parada = $pendientes[$mejor['n']];
            array_splice($tramos[$mejor['t']]['intercaladas'], $mejor['k'], 0, [$parada]);
            $tramos[$mejor['t']]['presupuesto'] -= $mejor['extra'];
            $dePaso[] = $parada->clave;
            unset($pendientes[$mejor['n']]);
        }

        $orden = [];
        foreach ($tramos as $t) {
            array_push($orden, ...$t['intercaladas']);
            $orden[] = $t['hacia'];
        }

        // Lo que no quedó de paso: al final, por cercanía desde la última parada.
        $actual = $orden[count($orden) - 1]->punto;
        while ($pendientes !== []) {
            $iMejor = null;
            foreach ($pendientes as $i => $n) {
                if ($iMejor === null || $d($actual, $n->punto) < $d($actual, $pendientes[$iMejor]->punto)) {
                    $iMejor = $i;
                }
            }
            $orden[] = $pendientes[$iMejor];
            $actual  = $pendientes[$iMejor]->punto;
            unset($pendientes[$iMejor]);
        }

        // Las claves de paso, en el orden en que se visitan.
        $posicion = array_flip(array_map(static fn (Parada $p) => $p->clave, $orden));
        usort($dePaso, static fn (string $a, string $b): int => $posicion[$a] <=> $posicion[$b]);

        return [$orden, $dePaso];
    }

    /** Mismo resultado con un aviso más (se suma al que ya tenga). */
    private static function conAviso(ResultadoRuta $r, string $aviso): ResultadoRuta
    {
        return new ResultadoRuta(
            $r->estrategia, $r->proveedor, $r->orden, $r->tramos, $r->distanciaM, $r->duracionS,
            $r->polyline, $r->regresa, trim(($r->aviso ?? '') . ' ' . $aviso)
        );
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
