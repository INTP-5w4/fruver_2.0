<?php

namespace App\Controllers;

use App\Libraries\Rutas\CspMapas;
use App\Libraries\Rutas\EnlaceNavegacion;
use App\Libraries\Rutas\OpcionesRuta;
use App\Libraries\Rutas\Parada;
use App\Libraries\Rutas\PlaneadorRutas;
use App\Libraries\Rutas\Punto;
use App\Libraries\Rutas\ResultadoRuta;
use App\Libraries\Rutas\UbicadorDirecciones;
use App\Models\Modelo_almacen;
use App\Models\Modelo_cliente;
use App\Models\Modelo_direccion;
use App\Models\Modelo_pedido;
use App\Models\Modelo_repartidor;
use App\Models\Modelo_ruta;
use App\Models\PdoModelo;
use DomainException;
use InvalidArgumentException;
use Throwable;

/**
 * Planeación de rutas de reparto (personal).
 *
 * Flujo:
 *  1. planeador: el admin elige fecha, repartidor, almacén y los pedidos.
 *  2. opciones (JSON): el servidor arma las paradas DESDE LA BASE DE DATOS (nunca
 *     confía en coordenadas del navegador), pide al servicio de mapas (openrouteservice
 *     o Google) 2–3 opciones y las guarda en sesión con un token.
 *  3. guardar: con el token y la opción elegida crea la ruta y sus entregas.
 */
class Rutas extends BaseController
{
    private const SESION = 'rutas_calculo';

    private function sinMigracion()
    {
        return view('rutas_sin_migracion');
    }

    // ─────────────────────────────────────────────────────────────
    // PLANEADOR
    // ─────────────────────────────────────────────────────────────

    public function planeador()
    {
        $m_ruta = new Modelo_ruta();
        if (! $m_ruta->esquemaListo()) {
            return $this->sinMigracion();
        }

        $fecha = $this->request->getGet('fecha');
        $fecha = is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1 ? $fecha : date('Y-m-d');

        $almacenes = (new Modelo_almacen())->activos();
        $cfg       = config('Rutas');

        CspMapas::permitir($this->response);

        return view('rutas_planeador', [
            'fecha'        => $fecha,
            'candidatos'   => $m_ruta->candidatos(),
            'rutas'        => $m_ruta->deFecha($fecha),
            'repartidores' => (new Modelo_repartidor())->findAll(),
            'almacenes'    => $almacenes,
            'almacenMapa'  => $almacenes[0] ?? null,
            'horaSalida'   => $cfg->horaSalida,
            'maxParadas'   => PlaneadorRutas::crear($cfg)->maxParadas(),
            'conServicio'  => $cfg->conServicio(),
            'proveedor'    => $cfg->nombreProveedor(),
            'conMapa'      => $cfg->conMapa(),
        ]);
    }

    /** POST (JSON): calcula las opciones de ruta para comparar. */
    public function opciones()
    {
        $m_ruta = new Modelo_ruta();
        if (! $m_ruta->esquemaListo()) {
            return $this->json(false, 'Falta correr la migración de rutas (php spark migrate).', [], 409);
        }

        $reglas = [
            'id_repartidor' => 'required|is_natural_no_zero',
            'id_almacen'    => 'required|is_natural_no_zero',
            'fecha'         => 'required|valid_date[Y-m-d]',
            // En arreglo: la expresión regular contiene "|" y rompería el formato con barras.
            'hora'          => ['required', 'regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]'],
            'regresa'       => 'permit_empty|in_list[0,1]',
            'estrategia'    => 'permit_empty|in_list[manual]',
        ];
        if (! $this->validate($reglas)) {
            return $this->json(false, $this->primerError(), [], 422);
        }

        $ids = $this->idsPedido();
        $max = PlaneadorRutas::crear()->maxParadas();
        if ($ids === []) {
            return $this->json(false, 'Elige al menos un pedido.', [], 422);
        }
        if (count($ids) > $max) {
            return $this->json(false, "Una ruta admite máximo {$max} paradas. Divide los pedidos en dos rutas.", [], 422);
        }

        $repartidor = (new Modelo_repartidor())->find((int) $this->request->getPost('id_repartidor'));
        $almacen    = (new Modelo_almacen())->find((int) $this->request->getPost('id_almacen'));
        $origen     = $almacen !== null ? Modelo_almacen::punto($almacen) : null;

        if ($repartidor === null || $origen === null || (int) $almacen['activo'] !== 1) {
            return $this->json(false, 'Repartidor o almacén no válido.', [], 422);
        }

        // Solo pedidos que de verdad se pueden rutear (confirmados / asignados sin ruta).
        $permitidos = array_map('intval', array_column($m_ruta->candidatos(), 'id_pedido'));
        $ajenos     = array_diff($ids, $permitidos);
        if ($ajenos !== []) {
            return $this->json(false, 'Los pedidos #' . implode(', #', $ajenos) . ' ya no se pueden poner en una ruta. Recarga la página.', [], 409);
        }

        [$filas, $sinUbicar] = $this->paradasConCoordenadas($m_ruta, $ids);
        if ($sinUbicar !== []) {
            return $this->json(false, 'Estos pedidos no tienen la dirección ubicada en el mapa: #' . implode(', #', $sinUbicar)
                . '. Corrige la dirección (o acomoda el pin) y vuelve a intentar.', ['sin_ubicar' => $sinUbicar], 422);
        }

        $preferenciales = array_flip((new Modelo_cliente())->idsPreferenciales());
        $paradas = [];
        foreach ($ids as $id) {
            $f         = $filas[$id];
            $paradas[] = new Parada(
                'p' . $id,
                Punto::desde($f['lat'], $f['lng']),
                Modelo_pedido::horaValida($f['ventana_fin']),
                isset($preferenciales[(int) $f['id_cliente']]) ? 1 : 0
            );
        }

        $vehiculo = (string) ($repartidor['vehiculo'] ?? 'moto');
        $salida   = PlaneadorRutas::salida($this->post('fecha'), $this->post('hora'));
        $regresa  = $this->request->getPost('regresa') !== '0';

        try {
            $op        = new OpcionesRuta('rapida', array_key_exists($vehiculo, OpcionesRuta::MODOS) ? $vehiculo : 'moto', $salida, $regresa);
            $planeador = PlaneadorRutas::crear();
            $opciones  = $this->post('estrategia') === 'manual'
                ? ['manual' => $planeador->calcular($origen, $paradas, $op->con('manual'))]
                : $planeador->opciones($origen, $paradas, $op);
        } catch (InvalidArgumentException $e) {
            return $this->json(false, $e->getMessage(), [], 422);
        } catch (Throwable $e) {
            return $this->json(false, PdoModelo::mensajeSeguro($e, 'No se pudo calcular la ruta.'), [], 500);
        }

        $token = bin2hex(random_bytes(12));
        $this->guardarCalculo($token, [
            'id_repartidor' => (int) $repartidor['id'],
            'id_almacen'    => (int) $almacen['id'],
            'fecha'         => $this->post('fecha'),
            'salida'        => $salida->format(DATE_ATOM),
            'regresa'       => $regresa,
            'ids'           => $ids,
            'opciones'      => array_map(static fn (ResultadoRuta $r) => $r->aArreglo(), $opciones),
        ]);

        $minutos = config('Rutas')->minutosPorParada;
        $salidaJs = [];
        foreach ($opciones as $clave => $r) {
            $etas = $r->etas($salida, $minutos);
            $salidaJs[$clave] = [
                'estrategia' => $clave,
                'proveedor'  => $r->proveedor,
                'orden'      => array_map(static fn (string $c) => (int) substr($c, 1), $r->orden),
                'etas'       => array_combine(array_map(static fn (string $c) => substr($c, 1), array_keys($etas)), array_map('rutas_hora', $etas)),
                'km'         => rutas_km($r->distanciaM),
                'tiempo'     => rutas_duracion($r->duracionS),
                'fin'        => rutas_hora($r->fin($salida, $minutos)),
                'polyline'   => $r->polyline,
                'aviso'      => $r->aviso,
            ];
        }

        $puntos = [];
        foreach ($ids as $id) {
            $puntos[$id] = [
                'lat'     => (float) $filas[$id]['lat'],
                'lng'     => (float) $filas[$id]['lng'],
                'cliente' => $filas[$id]['cliente'],
                'dir'     => Modelo_ruta::textoDireccion($filas[$id]),
            ];
        }

        return $this->json(true, '', [
            'token'    => $token,
            'opciones' => $salidaJs,
            'puntos'   => $puntos,
            'almacen'  => ['lat' => $origen->lat, 'lng' => $origen->lng],
            'regresa'  => $regresa,
        ]);
    }

    /** POST: crea la ruta con la opción elegida. */
    public function guardar()
    {
        $token      = $this->post('token');
        $estrategia = $this->post('estrategia');
        $calculos   = (array) session(self::SESION);
        $calculo    = $calculos[$token] ?? null;

        if ($calculo === null || ! isset($calculo['opciones'][$estrategia])) {
            return redirect()->to('rutas')->with('error', 'El cálculo expiró o no es válido. Vuelve a calcular la ruta.');
        }

        $r       = ResultadoRuta::desdeArreglo($calculo['opciones'][$estrategia]);
        $salida  = new \DateTimeImmutable($calculo['salida']);
        $etas    = $r->etas($salida, config('Rutas')->minutosPorParada);
        $paradas = [];

        foreach ($r->orden as $i => $clave) {
            $paradas[] = [
                'id_pedido'         => (int) substr($clave, 1),
                'eta'               => $etas[$clave] ?? null,
                'distancia_tramo_m' => $r->tramos[$i]['distancia_m'] ?? null,
                'duracion_tramo_s'  => $r->tramos[$i]['duracion_s'] ?? null,
            ];
        }

        try {
            $idRuta = (new Modelo_ruta())->crear([
                'fecha'           => $calculo['fecha'],
                'id_repartidor'   => $calculo['id_repartidor'],
                'id_almacen'      => $calculo['id_almacen'],
                'estrategia'      => $r->proveedor === 'local' && $r->estrategia !== 'manual' ? 'aproximada' : $r->estrategia,
                'regresa_almacen' => $calculo['regresa'] ? 1 : 0,
                'distancia_m'     => $r->distanciaM,
                'duracion_s'      => $r->duracionS,
                'polyline'        => $r->polyline,
                'proveedor'       => $r->proveedor,
                'creada_por'      => (int) auth()->id(),
            ], $paradas);
        } catch (DomainException $e) {
            return redirect()->to('rutas?fecha=' . rawurlencode($calculo['fecha']))->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to('rutas?fecha=' . rawurlencode($calculo['fecha']))
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo guardar la ruta.'));
        }

        unset($calculos[$token]);
        session()->set(self::SESION, $calculos);

        return redirect()->to('rutas/' . $idRuta)->with('mensaje', 'Ruta asignada. El repartidor ya la ve en su teléfono.');
    }

    // ─────────────────────────────────────────────────────────────
    // DETALLE / CANCELAR
    // ─────────────────────────────────────────────────────────────

    public function detalle($id = null)
    {
        $m_ruta = new Modelo_ruta();
        if (! $m_ruta->esquemaListo()) {
            return $this->sinMigracion();
        }

        $ruta = ctype_digit((string) $id) ? $m_ruta->detalle((int) $id) : null;
        if ($ruta === null) {
            return redirect()->to('rutas')->with('error', 'La ruta no existe.');
        }

        helper('whatsapp');

        $paradas = array_map(static function (array $p) use ($ruta): array {
            $punto        = Punto::desde($p['lat'], $p['lng']);
            $p['navegar'] = EnlaceNavegacion::hacia($punto, $p['place_id'] ?? null, (string) $ruta['vehiculo'], Modelo_ruta::textoDireccion($p));
            $p['aviso_wa'] = $p['eta'] && in_array($p['estado'], ['asignada', 'en_ruta'], true)
                ? whatsapp_url(
                    whatsapp_telefono($p['telefono_cliente']),
                    'Hola ' . whatsapp_texto($p['cliente']) . ', tu pedido #' . (int) $p['id_pedido']
                    . ' de Fruver llegará aproximadamente a las ' . rutas_hora($p['eta']) . '. ¡Gracias!'
                )
                : null;

            return $p;
        }, $m_ruta->paradas((int) $id));

        CspMapas::permitir($this->response);

        return view('rutas_detalle', [
            'ruta'        => $ruta,
            'paradas'     => $paradas,
            'almacenMapa' => ['lat' => $ruta['almacen_lat'], 'lng' => $ruta['almacen_lng']],
        ]);
    }

    public function cancelar($id = null)
    {
        $ok = ctype_digit((string) $id) && (new Modelo_ruta())->cancelar((int) $id);

        return redirect()->to($ok ? 'rutas' : 'rutas/' . (int) $id)->with(
            $ok ? 'mensaje' : 'error',
            $ok ? 'Ruta cancelada. Sus pedidos siguen asignados al repartidor y se pueden volver a planear.'
                : 'Solo se puede cancelar una ruta que aún no sale.'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // ALMACÉN (punto de salida)
    // ─────────────────────────────────────────────────────────────

    public function almacen()
    {
        if (! (new Modelo_ruta())->esquemaListo()) {
            return $this->sinMigracion();
        }

        $almacenes = (new Modelo_almacen())->findAll();
        CspMapas::permitir($this->response);

        return view('rutas_almacen', [
            'almacenes'   => $almacenes,
            'almacenMapa' => $almacenes[0] ?? null,
        ]);
    }

    public function guarda_almacen()
    {
        $reglas = [
            'id'        => 'permit_empty|is_natural_no_zero',
            'nombre'    => 'required|max_length[100]',
            'direccion' => 'required|max_length[300]',
            'geo_lat'   => 'required|decimal|greater_than_equal_to[-90]|less_than_equal_to[90]',
            'geo_lng'   => 'required|decimal|greater_than_equal_to[-180]|less_than_equal_to[180]',
        ];
        $mensajes = [
            'geo_lat' => ['required' => 'Ubica el almacén en el mapa (busca la dirección o toca el mapa).'],
            'geo_lng' => ['required' => 'Ubica el almacén en el mapa (busca la dirección o toca el mapa).'],
        ];

        $id     = $this->post('id');
        $actual = $id !== '' && ctype_digit($id) ? (new Modelo_almacen())->find((int) $id) : null;

        // Al editar sin mover el pin se conservan las coordenadas actuales.
        $conservaPin = $actual !== null && $this->post('geo_lat') === '';
        if ($conservaPin) {
            unset($reglas['geo_lat'], $reglas['geo_lng']);
        }

        if (! $this->validate($reglas, $mensajes)) {
            return $this->volverConErrores();
        }

        $datos = [
            'nombre'    => $this->post('nombre'),
            'direccion' => $this->post('direccion'),
            'activo'    => $this->request->getPost('activo') === '0' ? 0 : 1,
        ];
        if (! $conservaPin) {
            $datos['lat']      = (float) $this->post('geo_lat');
            $datos['lng']      = (float) $this->post('geo_lng');
            $datos['place_id'] = $this->post('geo_place_id') !== '' ? mb_substr($this->post('geo_place_id'), 0, 255) : null;
        }

        try {
            $m = new Modelo_almacen();
            $actual !== null ? $m->update((int) $actual['id'], $datos) : $m->insert($datos);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo guardar el almacén.'));
        }

        return redirect()->to('rutas/almacen')->with('mensaje', 'Almacén guardado.');
    }

    // ─────────────────────────────────────────────────────────────

    /** @return list<int> ids de pedido en el orden recibido, sin repetir */
    private function idsPedido(): array
    {
        $crudos = $this->request->getPost('pedidos');
        if (! is_array($crudos)) {
            return [];
        }

        $ids = [];
        foreach ($crudos as $v) {
            if (is_string($v) && ctype_digit($v) && (int) $v > 0) {
                $ids[(int) $v] = (int) $v;
            }
        }

        return array_values($ids);
    }

    /**
     * Lee las paradas de la BD; a las que no tienen coordenadas intenta ubicarlas
     * con el servicio de mapas (una vez). Devuelve [filas por id, ids que siguen sin ubicar].
     *
     * @return array{0:array<int,array<string,mixed>>,1:list<int>}
     */
    private function paradasConCoordenadas(Modelo_ruta $m_ruta, array $ids): array
    {
        $filas       = $m_ruta->paradasDePedidos($ids);
        $reintentar  = false;
        $m_direccion = new Modelo_direccion();
        $ubicador    = null;
        $conBusqueda = config('Rutas')->conServicio();

        foreach ($ids as $id) {
            $f = $filas[$id] ?? null;
            if ($f !== null && $f['id_direccion'] !== null && Punto::desde($f['lat'], $f['lng']) === null && $conBusqueda) {
                $ubicador ??= UbicadorDirecciones::crear($m_direccion);
                $d = $m_direccion->find((int) $f['id_direccion']);
                if ($d !== null) {
                    $ubicador->ubicar((int) $d['id'], $d, null, true);
                    $reintentar = true;
                }
            }
        }

        if ($reintentar) {
            $filas = $m_ruta->paradasDePedidos($ids);
        }

        $sin = [];
        foreach ($ids as $id) {
            if (! isset($filas[$id]) || Punto::desde($filas[$id]['lat'], $filas[$id]['lng']) === null) {
                $sin[] = $id;
            }
        }

        return [$filas, $sin];
    }

    /** Guarda el cálculo en sesión (máximo 5 recientes). */
    private function guardarCalculo(string $token, array $datos): void
    {
        $calculos          = (array) session(self::SESION);
        $calculos[$token]  = $datos;
        session()->set(self::SESION, array_slice($calculos, -5, null, true));
    }

    private function json(bool $ok, string $mensaje, array $extra = [], int $codigo = 200)
    {
        return $this->response->setStatusCode($codigo)->setJSON(['ok' => $ok, 'mensaje' => $mensaje, 'csrf' => csrf_hash()] + $extra);
    }
}
