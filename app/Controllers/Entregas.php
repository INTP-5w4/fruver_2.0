<?php

namespace App\Controllers;

use App\Libraries\Rutas\CspMapas;
use App\Libraries\Rutas\EnlaceNavegacion;
use App\Libraries\Rutas\OpcionesRuta;
use App\Libraries\Rutas\Parada;
use App\Libraries\Rutas\PlaneadorRutas;
use App\Libraries\Rutas\Punto;
use App\Models\Modelo_almacen;
use App\Models\Modelo_entrega;
use App\Models\Modelo_pedido;
use App\Models\Modelo_repartidor;
use App\Models\Modelo_ruta;
use App\Models\PdoModelo;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Throwable;

/**
 * Entregas de pedidos.
 *
 *  - Personal (admin): asigna repartidor a un pedido, ve y cancela entregas.
 *  - Repartidor: ve SOLO sus entregas y las avanza (salir, entregar, reportar falla).
 *
 * El repartidor se identifica siempre por auth()->id() (la sesión de Shield); nunca
 * por un id que llegue en el formulario o en la URL.
 */
class Entregas extends BaseController
{
    private const ESTADOS = ['asignada', 'en_ruta', 'entregada', 'fallida', 'cancelada'];

    // ─────────────────────────────────────────────────────────────
    // PERSONAL
    // ─────────────────────────────────────────────────────────────

    public function lista_entrega()
    {
        $buscar    = $this->textoBusqueda();
        $m_entrega = new Modelo_entrega();

        return view('lista_entrega', [
            'entregas'     => $m_entrega->listarTodas($buscar),
            'pager'        => $m_entrega->pager,
            'buscar'       => $buscar,
            'pedidos'      => $m_entrega->pedidosAsignables(),
            'repartidores' => (new Modelo_repartidor())->findAll(),
        ]);
    }

    public function asigna_entrega()
    {
        $reglas = [
            'id_pedido'     => 'required|is_natural_no_zero',
            'id_repartidor' => 'required|is_natural_no_zero',
        ];

        if (! $this->validate($reglas)) {
            return redirect()->to('lista_entrega')->with('error', 'Elige un pedido y un repartidor.');
        }

        try {
            (new Modelo_entrega())->asignar(
                (int) $this->request->getPost('id_pedido'),
                (int) $this->request->getPost('id_repartidor')
            );

            return redirect()->to('lista_entrega')->with('mensaje', 'Repartidor asignado correctamente.');
        } catch (DomainException $e) {
            return redirect()->to('lista_entrega')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to('lista_entrega')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo asignar el repartidor.'));
        }
    }

    public function cancela_entrega($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return redirect()->to('lista_entrega');
        }

        try {
            $ok = (new Modelo_entrega())->cancelar((int) $id, $this->post('motivo'));

            // Si era la última parada abierta de una ruta, la ruta se da por terminada.
            $m_ruta = new Modelo_ruta();
            if ($ok && $m_ruta->esquemaListo() && ($idRuta = $m_ruta->deEntrega((int) $id)) !== null) {
                $m_ruta->cerrarSiTermino($idRuta);
            }

            return redirect()->to('lista_entrega')->with(
                $ok ? 'mensaje' : 'error',
                $ok ? 'Entrega cancelada.' : 'Solo se pueden cancelar entregas asignadas o en ruta.'
            );
        } catch (Throwable $e) {
            return redirect()->to('lista_entrega')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo cancelar la entrega.'));
        }
    }

    // ─────────────────────────────────────────────────────────────
    // REPARTIDOR
    // ─────────────────────────────────────────────────────────────

    public function mis_entregas()
    {
        $estado = (string) $this->request->getGet('estado');
        $estado = in_array($estado, self::ESTADOS, true) ? $estado : null;

        $m_entrega = new Modelo_entrega();

        $m_ruta  = new Modelo_ruta();
        $conRuta = $m_ruta->esquemaListo();

        $entregas = array_map(static function (array $e): array {
            $e['navegar'] = array_key_exists('lat', $e)
                ? EnlaceNavegacion::hacia(Punto::desde($e['lat'], $e['lng']), $e['place_id'] ?? null, 'moto', Modelo_ruta::textoDireccion($e))
                : null;

            return $e;
        }, $m_entrega->listarDeRepartidor((int) auth()->id(), $estado));

        return view('mis_entregas', [
            'entregas'        => $entregas,
            'pager'           => $m_entrega->pager,
            'estado'          => $estado,
            'rutasPendientes' => $conRuta ? $m_ruta->pendientesDeUsuario((int) auth()->id()) : 0,
        ]);
    }

    public function salir($id = null)
    {
        return $this->accionRepartidor($id, function (Modelo_entrega $m, int $idEntrega): array {
            return $m->iniciarRuta($idEntrega, (int) auth()->id())
                ? ['mensaje', 'Entrega en ruta.']
                : ['error', 'Esta entrega no está disponible para salir.'];
        });
    }

    public function entregar($id = null)
    {
        $reglas = [
            'monto'  => 'permit_empty|decimal|greater_than_equal_to[0]|less_than[100000000]',
            'metodo' => 'permit_empty|in_list[efectivo,transferencia,tarjeta]',
            'notas'  => 'permit_empty|max_length[500]',
        ];

        if (! $this->validate($reglas)) {
            return redirect()->to($this->volver())->with('error', $this->primerError());
        }

        $monto = $this->post('monto');

        if ($monto !== '' && $this->post('metodo') === '') {
            return redirect()->to($this->volver())->with('error', 'Indica cómo se cobró.');
        }

        return $this->accionRepartidor($id, function (Modelo_entrega $m, int $idEntrega) use ($monto): array {
            $evidencia = $this->guardarEvidencia();
            if ($evidencia === false) {
                return ['error', 'La foto de evidencia debe ser una imagen JPG, PNG o WEBP de máximo 5 MB.'];
            }

            $ok = $m->marcarEntregada(
                $idEntrega,
                (int) auth()->id(),
                $monto === '' ? null : (float) $monto,
                $monto === '' ? null : $this->post('metodo'),
                $evidencia,
                $this->post('notas'),
                Punto::desde($this->post('gps_lat'), $this->post('gps_lng'))
            );

            if (! $ok && $evidencia !== null) {
                @unlink(WRITEPATH . 'uploads/' . $evidencia);
            }

            return $ok
                ? ['mensaje', 'Entrega registrada como entregada.']
                : ['error', 'Esta entrega no se puede marcar como entregada.'];
        });
    }

    public function fallar($id = null)
    {
        if (! $this->validate(['incidencia' => 'required|max_length[500]'])) {
            return redirect()->to($this->volver())
                ->with('error', 'Describe qué pasó (máximo 500 caracteres).');
        }

        return $this->accionRepartidor($id, function (Modelo_entrega $m, int $idEntrega): array {
            return $m->marcarFallida($idEntrega, (int) auth()->id(), $this->post('incidencia'))
                ? ['mensaje', 'Incidencia registrada. El administrador podrá reasignar la entrega.']
                : ['error', 'Esta entrega no se puede reportar como fallida.'];
        });
    }

    /**
     * Valida el id de la URL, ejecuta la acción y convierte el resultado (o la
     * excepción) en un mensaje flash. Mantiene los tres métodos de arriba cortos.
     *
     * @param callable(Modelo_entrega,int):array{0:string,1:string} $accion
     */
    private function accionRepartidor($id, callable $accion)
    {
        $volver = $this->volver();

        if (! ctype_digit((string) $id)) {
            return redirect()->to($volver);
        }

        try {
            [$clave, $texto] = $accion(new Modelo_entrega(), (int) $id);

            // Si la entrega era parte de una ruta y ya no quedan paradas abiertas, se cierra.
            $m_ruta = new Modelo_ruta();
            if ($clave === 'mensaje' && $m_ruta->esquemaListo()) {
                $idRuta = $m_ruta->deEntrega((int) $id);
                if ($idRuta !== null && $m_ruta->cerrarSiTermino($idRuta)) {
                    $texto .= ' ¡Ruta terminada!';
                }
            }

            return redirect()->to($volver)->with($clave, $texto);
        } catch (DomainException $e) {
            return redirect()->to($volver)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to($volver)
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo completar la acción.'));
        }
    }

    /** A dónde regresar tras una acción: "Mi ruta" o "Mis entregas" (valor fijo, sin redirección abierta). */
    private function volver(): string
    {
        $ruta = $this->request->getPost('volver_ruta');

        return is_string($ruta) && ctype_digit($ruta) ? 'repartidor/ruta/' . $ruta : 'repartidor/entregas';
    }

    /**
     * Guarda la foto de evidencia en writable/uploads/evidencias (fuera de public/:
     * son fotos de domicilios, solo el personal las ve).
     *
     * @return string|null|false ruta relativa, null si no se mandó foto, false si no es válida
     */
    private function guardarEvidencia(): string|null|false
    {
        $f = $this->request->getFile('evidencia');

        if ($f === null || $f->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (! $f->isValid() || $f->hasMoved() || $f->getSize() > 5 * 1024 * 1024
            || ! in_array($f->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return false;
        }

        $nombre = $f->getRandomName();
        $f->move(WRITEPATH . 'uploads/evidencias', $nombre);

        return 'evidencias/' . $nombre;
    }

    // ─────────────────────────────────────────────────────────────
    // RUTA DEL REPARTIDOR
    // ─────────────────────────────────────────────────────────────

    public function mi_ruta($id = null)
    {
        $m_ruta = new Modelo_ruta();
        if (! $m_ruta->esquemaListo()) {
            return redirect()->to('repartidor/entregas');
        }

        $idUser = (int) auth()->id();
        $ruta   = $m_ruta->activaDeUsuario($idUser, ctype_digit((string) $id) ? (int) $id : null);

        if ($ruta === null) {
            return redirect()->to('repartidor/entregas')->with('mensaje', 'No tienes rutas pendientes.');
        }

        $siguiente = null;
        $paradas   = array_map(static function (array $p) use ($ruta, &$siguiente): array {
            $p['navegar'] = EnlaceNavegacion::hacia(Punto::desde($p['lat'], $p['lng']), $p['place_id'] ?? null, (string) $ruta['vehiculo'], Modelo_ruta::textoDireccion($p));
            if ($siguiente === null && in_array($p['estado'], ['en_ruta', 'asignada'], true)) {
                $siguiente = (int) $p['id'];
            }

            return $p;
        }, $m_ruta->paradas((int) $ruta['id']));

        $regreso = $ruta['regresa_almacen']
            ? EnlaceNavegacion::hacia(Punto::desde($ruta['almacen_lat'], $ruta['almacen_lng']), null, (string) $ruta['vehiculo'])
            : null;

        CspMapas::permitir($this->response);

        return view('mi_ruta', [
            'ruta'        => $ruta,
            'paradas'     => $paradas,
            'siguiente'   => $siguiente,
            'regreso'     => $regreso,
            'almacenMapa' => ['lat' => $ruta['almacen_lat'], 'lng' => $ruta['almacen_lng']],
        ]);
    }

    public function iniciar_ruta($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return redirect()->to('repartidor/entregas');
        }

        try {
            $r = (new Modelo_ruta())->iniciar((int) $id, (int) auth()->id());
        } catch (DomainException $e) {
            return redirect()->to('repartidor/ruta/' . (int) $id)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to('repartidor/ruta/' . (int) $id)->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo iniciar la ruta.'));
        }

        $texto = $r['salieron'] . ' entrega(s) en camino. ¡Buen viaje!';
        if ($r['avisos'] !== []) {
            $texto .= ' Atención: ' . implode(' · ', $r['avisos']);
        }

        return redirect()->to('repartidor/ruta/' . (int) $id)->with('mensaje', $texto);
    }

    /** Recalcula el orden de las paradas pendientes desde la ubicación actual del teléfono. */
    public function reoptimizar($id = null)
    {
        $volver = 'repartidor/ruta/' . (int) $id;
        $gps    = Punto::desde($this->post('gps_lat'), $this->post('gps_lng'));

        if (! ctype_digit((string) $id) || $gps === null) {
            return redirect()->to($volver)->with('error', 'No se pudo leer tu ubicación. Activa el GPS y permite el acceso a la ubicación.');
        }

        $m_ruta = new Modelo_ruta();
        $ruta   = $m_ruta->activaDeUsuario((int) auth()->id(), (int) $id);
        if ($ruta === null || ! in_array($ruta['estado'], ['asignada', 'en_curso'], true)) {
            return redirect()->to('repartidor/entregas')->with('error', 'Esta ruta ya no está activa.');
        }

        $pendientes = array_values(array_filter($m_ruta->paradas((int) $id), static fn ($p) => in_array($p['estado'], ['asignada', 'en_ruta'], true)));
        $paradas    = [];
        foreach ($pendientes as $p) {
            $punto = Punto::desde($p['lat'], $p['lng']);
            if ($punto === null) {
                return redirect()->to($volver)->with('error', 'El pedido #' . $p['id_pedido'] . ' no tiene la dirección ubicada; no se puede reoptimizar.');
            }
            $paradas[] = new Parada('e' . $p['id'], $punto, Modelo_pedido::horaValida($p['ventana_fin']));
        }

        if (count($paradas) < 2) {
            return redirect()->to($volver)->with('mensaje', 'Solo queda una parada: no hay nada que reordenar.');
        }

        $vehiculo = array_key_exists((string) $ruta['vehiculo'], OpcionesRuta::MODOS) ? (string) $ruta['vehiculo'] : 'moto';
        $ahora    = new DateTimeImmutable('now');

        try {
            $op = new OpcionesRuta('rapida', $vehiculo, $ahora, (bool) $ruta['regresa_almacen'], Punto::desde($ruta['almacen_lat'], $ruta['almacen_lng']));
            $r  = PlaneadorRutas::crear()->calcular($gps, $paradas, $op);
        } catch (InvalidArgumentException $e) {
            return redirect()->to($volver)->with('error', $e->getMessage());
        }

        $etas  = $r->etas($ahora, config('Rutas')->minutosPorParada);
        $orden = [];
        foreach ($r->orden as $i => $clave) {
            $orden[] = [
                'id_entrega'        => (int) substr($clave, 1),
                'eta'               => $etas[$clave] ?? null,
                'distancia_tramo_m' => $r->tramos[$i]['distancia_m'] ?? null,
                'duracion_tramo_s'  => $r->tramos[$i]['duracion_s'] ?? null,
            ];
        }

        try {
            $m_ruta->reordenarPendientes((int) $id, $orden, [
                'estrategia'  => $r->proveedor === 'local' ? 'aproximada' : 'rapida',
                'proveedor'   => $r->proveedor,
                'polyline'    => $r->polyline,
                'distancia_m' => $r->distanciaM,
                'duracion_s'  => $r->duracionS,
            ]);
        } catch (Throwable $e) {
            return redirect()->to($volver)->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo guardar el nuevo orden.'));
        }

        $texto = 'Ruta reordenada desde tu ubicación: ' . rutas_km($r->distanciaM) . ', ' . rutas_duracion($r->duracionS) . '.';

        return redirect()->to($volver)->with($r->aviso ? 'aviso' : 'mensaje', $r->aviso ? $texto . ' ' . $r->aviso : $texto);
    }

    // ─────────────────────────────────────────────────────────────
    // PERSONAL: ver la foto de evidencia
    // ─────────────────────────────────────────────────────────────

    public function evidencia($id = null)
    {
        $archivo = ctype_digit((string) $id) ? (new Modelo_entrega())->evidencia((int) $id) : null;
        $ruta    = $archivo !== null ? realpath(WRITEPATH . 'uploads/' . $archivo) : false;
        $base    = realpath(WRITEPATH . 'uploads/evidencias');

        // Solo archivos dentro de la carpeta de evidencias (nada de "../").
        if ($ruta === false || $base === false || ! str_starts_with($ruta, $base . DIRECTORY_SEPARATOR) || ! is_file($ruta)) {
            return $this->response->setStatusCode(404)->setBody('Sin evidencia');
        }

        return $this->response
            ->setHeader('Content-Type', mime_content_type($ruta) ?: 'application/octet-stream')
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody((string) file_get_contents($ruta));
    }
}
