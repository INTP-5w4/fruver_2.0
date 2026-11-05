<?php

namespace App\Controllers;

use App\Libraries\Rutas\CspMapas;
use App\Libraries\Rutas\ErrorProveedorRutas;
use App\Libraries\Rutas\GeocodificadorOrs;
use App\Libraries\Rutas\ServiciosMapa;
use App\Libraries\Rutas\UbicadorDirecciones;
use App\Models\Modelo_cliente;
use App\Models\Modelo_direccion;
use App\Models\PdoModelo;
use Throwable;

class Direcciones extends BaseController
{
    private array $reglas = [
        'col'        => 'required|max_length[200]',
        'calle'      => 'required|max_length[200]',
        'num'        => 'required|max_length[50]',
        'mun'        => 'required|max_length[200]',
        'edo'        => 'required|max_length[100]',
        'id_cliente' => 'required|is_natural_no_zero',
        // Rutas de reparto (opcionales)
        'cp'          => 'permit_empty|alpha_numeric|max_length[10]',
        'referencias' => 'permit_empty|max_length[300]',
        'geo_lat'     => 'permit_empty|decimal|greater_than_equal_to[-90]|less_than_equal_to[90]',
        'geo_lng'     => 'permit_empty|decimal|greater_than_equal_to[-180]|less_than_equal_to[180]',
        'geo_origen'  => 'permit_empty|in_list[google,osm,manual]',
    ];

    private array $mensajes = [
        'col'        => ['required' => 'La Colonia es obligatoria.',   'max_length' => 'La Colonia admite máximo 200 caracteres.'],
        'calle'      => ['required' => 'La Calle es obligatoria.',     'max_length' => 'La Calle admite máximo 200 caracteres.'],
        'num'        => ['required' => 'El Número es obligatorio.',    'max_length' => 'El Número admite máximo 50 caracteres.'],
        'mun'        => ['required' => 'El Municipio es obligatorio.', 'max_length' => 'El Municipio admite máximo 200 caracteres.'],
        'edo'        => ['required' => 'El Estado es obligatorio.',    'max_length' => 'El Estado admite máximo 100 caracteres.'],
        'id_cliente' => ['required' => 'Selecciona un cliente.',       'is_natural_no_zero' => 'Selecciona un cliente válido.'],
        'cp'          => ['alpha_numeric' => 'El código postal solo admite números y letras.', 'max_length' => 'El código postal admite máximo 10 caracteres.'],
        'referencias' => ['max_length' => 'Las referencias admiten máximo 300 caracteres.'],
        'geo_lat'     => ['decimal' => 'La ubicación del mapa no es válida.'],
        'geo_lng'     => ['decimal' => 'La ubicación del mapa no es válida.'],
        'geo_origen'  => ['in_list' => 'La ubicación del mapa no es válida.'],
    ];

    private function datosDelFormulario(): array
    {
        return [
            'colonia'    => $this->post('col'),
            'calle'      => $this->post('calle'),
            'numero'     => $this->post('num'),
            'municipio'  => $this->post('mun'),
            'estado'     => $this->post('edo'),
            'id_cliente' => (int) $this->request->getPost('id_cliente'),
            // El modelo los ignora si todavía no se corre la migración de rutas.
            'codigo_postal' => $this->post('cp') !== '' ? $this->post('cp') : null,
            'referencias'   => $this->post('referencias') !== '' ? $this->post('referencias') : null,
        ];
    }

    /** Pin del mapa (autocompletado o movido a mano); null si el usuario no lo tocó. */
    private function pinDelFormulario(): ?array
    {
        if ($this->post('geo_lat') === '' || $this->post('geo_lng') === '') {
            return null;
        }

        return [
            'lat'                  => $this->post('geo_lat'),
            'lng'                  => $this->post('geo_lng'),
            'origen'               => $this->post('geo_origen'),
            'place_id'             => $this->post('geo_place_id'),
            'direccion_formateada' => $this->post('geo_formateada'),
            'precision'            => $this->post('geo_precision'),
        ];
    }

    /**
     * Después de guardar: ubica la dirección (pin o Geocoding) y la marca como principal
     * si se pidió o si el cliente no tenía otra. Devuelve un aviso o null.
     */
    private function despuesDeGuardar(Modelo_direccion $m, int $id, array $datos, bool $cambioTexto): ?string
    {
        if (! $m->tieneColumnasGeo()) {
            return null;
        }

        $aviso = UbicadorDirecciones::crear($m)->ubicar($id, $datos, $this->pinDelFormulario(), $cambioTexto);

        $tienePrincipal = false;
        foreach ($m->deCliente((int) $datos['id_cliente']) as $d) {
            $tienePrincipal = $tienePrincipal || ((int) $d['es_principal'] === 1 && (int) $d['id'] !== $id);
        }
        if ($this->request->getPost('principal') === '1' || ! $tienePrincipal) {
            $m->marcarPrincipal($id);
        }

        return $aviso;
    }

    public function crea_direccion()
    {
        $m_cliente                  = new Modelo_cliente();
        $datos_clientes['clientes'] = $m_cliente->findAll();

        CspMapas::permitir($this->response);

        return view('crea_direccion', $datos_clientes);
    }

    public function guarda_direccion()
    {
        if (! $this->validate($this->reglas, $this->mensajes)) {
            return $this->volverConErrores('error');
        }

        try {
            $m     = new Modelo_direccion();
            $datos = $this->datosDelFormulario();
            $id    = $m->insert($datos);
            $aviso = $this->despuesDeGuardar($m, $id, $datos, true);

            $r = redirect()->to($this->destino('lista_direccion'))
                ->with('mensaje', 'Dirección registrada correctamente');

            return $aviso !== null ? $r->with('aviso', $aviso) : $r;
        } catch (Throwable $e) {
            return redirect()->back()->withInput()
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo registrar la dirección.'));
        }
    }

    public function lista_direccion()
    {
        $buscar      = $this->textoBusqueda();
        $m_direccion = new Modelo_direccion();
        $m_cliente   = new Modelo_cliente();

        $clientes_lista = $m_cliente->findAll();

        CspMapas::permitir($this->response);

        $datos = [
            'resumenGeo'     => $m_direccion->resumenGeo(),
            'conGeo'         => $m_direccion->tieneColumnasGeo(),
            'direcciones'    => $m_direccion->listar($buscar),
            'clientes'       => array_column($clientes_lista, null, 'id'),
            'clientes_lista' => $clientes_lista,
            'buscar'         => $buscar,
            'pager'          => $m_direccion->pager,
        ];

        return view('lista_direccion', $datos);
    }

    public function recupera($id = null)
    {
        $m_direccion = new Modelo_direccion();
        $direccion   = $m_direccion->find($id);

        if (! $direccion) {
            return redirect()->to('lista_direccion');
        }

        CspMapas::permitir($this->response);

        return view('modifica_direccion', [
            'direcciones' => $direccion,
            'clientes'    => (new Modelo_cliente())->findAll(),
        ]);
    }

    public function eliminar_datos($id = null)
    {
        $m_direccion = new Modelo_direccion();

        if (! $m_direccion->find($id)) {
            return redirect()->to('lista_direccion');
        }

        try {
            $m_direccion->delete($id);

            return redirect()->to('lista_direccion')->with('mensaje', 'Dirección eliminada correctamente.');
        } catch (Throwable $e) {
            $mensaje = PdoModelo::esErrorForaneo($e)
                ? 'No se puede eliminar la dirección porque tiene registros relacionados.'
                : PdoModelo::mensajeSeguro($e, 'No se pudo eliminar la dirección.');

            return redirect()->to('lista_direccion')->with('error', $mensaje);
        }
    }

    public function modifica()
    {
        $id = $this->request->getPost('id');

        if (! ctype_digit((string) $id)) {
            return redirect()->to('lista_direccion')->with('error', 'ID inválido');
        }

        if (! $this->validate($this->reglas, $this->mensajes)) {
            return $this->volverConErrores('error');
        }

        try {
            $m      = new Modelo_direccion();
            $antes  = $m->find($id);
            $datos  = $this->datosDelFormulario();
            $m->update($id, $datos);

            $cambioTexto = $antes === null;
            foreach (['calle', 'numero', 'colonia', 'municipio', 'estado', 'codigo_postal'] as $c) {
                $cambioTexto = $cambioTexto || trim((string) ($antes[$c] ?? '')) !== trim((string) ($datos[$c] ?? ''));
            }
            $aviso = $this->despuesDeGuardar($m, (int) $id, $datos, $cambioTexto);

            $r = redirect()->to('lista_direccion')->with('mensaje', 'Dirección actualizada correctamente');

            return $aviso !== null ? $r->with('aviso', $aviso) : $r;
        } catch (Throwable $e) {
            return redirect()->to('lista_direccion')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo actualizar la dirección.'));
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RUTAS DE REPARTO
    // ─────────────────────────────────────────────────────────────

    /** JSON con las direcciones de un cliente (selector de dirección en los pedidos). */
    public function api_direcciones_cliente($idCliente = null)
    {
        if (! ctype_digit((string) $idCliente)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Cliente inválido']);
        }

        $m     = new Modelo_direccion();
        $lista = array_map(static fn (array $d): array => [
            'id'        => (int) $d['id'],
            'texto'     => trim($d['calle'] . ' ' . $d['numero']) . ', ' . $d['colonia'] . ', ' . $d['municipio'],
            'principal' => (int) ($d['es_principal'] ?? 0) === 1,
            'ubicada'   => isset($d['lat']) && $d['lat'] !== null,
        ], $m->deCliente((int) $idCliente));

        return $this->response->setJSON(['direcciones' => $lista]);
    }

    /** Vuelve a ubicar una dirección con el servicio de mapas a partir de su texto. */
    public function geocodificar($id = null)
    {
        $m = new Modelo_direccion();
        $d = ctype_digit((string) $id) ? $m->find($id) : null;

        if ($d === null || ! $m->tieneColumnasGeo()) {
            return redirect()->to('lista_direccion');
        }

        if (($d['origen_coord'] ?? null) === 'manual' || ($d['origen_coord'] ?? null) === 'gps_entrega') {
            return redirect()->to('lista_direccion')
                ->with('aviso', 'Esta dirección ya tiene una ubicación confirmada (pin o GPS de entrega); edítala para cambiarla.');
        }

        $aviso = UbicadorDirecciones::crear($m)->ubicar((int) $id, $d, null, true);

        return redirect()->to('lista_direccion')->with(
            $aviso === null ? 'mensaje' : 'aviso',
            $aviso ?? 'Dirección ubicada en el mapa.'
        );
    }

    /**
     * Buscador de direcciones del formulario (proveedor openrouteservice).
     *
     * El navegador nunca recibe la llave: pregunta a este endpoint y el servidor consulta
     * a openrouteservice. Límite por usuario para cuidar la cuota diaria gratuita.
     */
    public function buscar()
    {
        $q = $this->textoBusqueda('q');

        if (mb_strlen($q) < 4) {
            return $this->response->setJSON(['resultados' => []]);
        }

        $geo = ServiciosMapa::geocodificador();
        if (! $geo instanceof GeocodificadorOrs) {
            return $this->response->setStatusCode(404)->setJSON(['resultados' => [], 'mensaje' => 'El buscador requiere openrouteservice.']);
        }

        if (! service('throttler')->check('buscar_dir_' . (int) auth()->id(), 40, MINUTE)) {
            return $this->response->setStatusCode(429)->setJSON(['resultados' => [], 'mensaje' => 'Demasiadas búsquedas; espera un momento.']);
        }

        try {
            $resultados = $geo->sugerencias($q, 5);
        } catch (ErrorProveedorRutas $e) {
            log_message('error', 'Buscador de direcciones: ' . $e->getMessage());

            return $this->response->setStatusCode(502)->setJSON(['resultados' => [], 'mensaje' => 'El servicio de mapas no respondió.']);
        }

        return $this->response->setJSON(['resultados' => $resultados]);
    }
}
