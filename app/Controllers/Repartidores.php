<?php

namespace App\Controllers;

use App\Libraries\Rutas\Combustible;
use App\Models\Modelo_repartidor;
use App\Models\PdoModelo;
use DomainException;
use Throwable;

class Repartidores extends BaseController
{
    private array $configuracion = [
        'reglas' => [
            'nom'     => 'required|max_length[100]|sin_groserias',
            'ape_pat' => 'required|max_length[100]|sin_groserias',
            'ape_mat' => 'required|max_length[100]|sin_groserias',
            'tel'     => 'required|numeric|max_length[12]',
            'dir'     => 'required|max_length[500]|sin_groserias',
            'not'     => 'permit_empty|max_length[500]|sin_groserias',
            'combustible' => 'permit_empty|in_list[magna,premium,diesel]',
            'rendimiento' => 'permit_empty|decimal|greater_than[0]|less_than_equal_to[150]',
        ],
        'errores' => [
            'nom' => [
                'required'   => 'El campo Nombre es obligatorio.',
                'max_length' => 'El Nombre admite máximo 100 caracteres.',
            ],
            'ape_pat' => [
                'required'   => 'El Apellido Paterno es obligatorio.',
                'max_length' => 'El Apellido Paterno admite máximo 100 caracteres.',
            ],
            'ape_mat' => [
                'required'   => 'El Apellido Materno es obligatorio.',
                'max_length' => 'El Apellido Materno admite máximo 100 caracteres.',
            ],
            'tel' => [
                'required'   => 'El Teléfono es obligatorio.',
                'numeric'    => 'El Teléfono debe contener solo números.',
                'max_length' => 'El Teléfono debe tener máximo 12 dígitos.',
            ],
            'dir' => [
                'required'   => 'El campo Dirección es obligatorio.',
                'max_length' => 'La Dirección admite máximo 500 caracteres.',
            ],
            'not' => [
                'max_length' => 'Las Notas admiten máximo 500 caracteres.',
            ],
            'combustible' => [
                'in_list' => 'Elige un combustible de la lista.',
            ],
            'rendimiento' => [
                'decimal'            => 'El rendimiento debe ser un número (km por litro).',
                'greater_than'       => 'El rendimiento debe ser mayor que cero.',
                'less_than_equal_to' => 'El rendimiento parece demasiado alto (máximo 150 km por litro).',
            ],
        ],
    ];

    public function crea_repartidor()
    {
        return view('crea_repartidor');
    }

    private function datosDelFormulario(): array
    {
        return [
            'nombre'    => $this->post('nom'),
            'ape_pat'   => $this->post('ape_pat'),
            'ape_mat'   => $this->post('ape_mat'),
            'telefono'  => $this->post('tel'),
            'direccion' => $this->post('dir'),
            'notas'     => $this->post('not'),
            // Rutas: solo valores de la lista blanca; el modelo lo ignora si aún no hay migración.
            'vehiculo'  => array_key_exists($this->post('vehiculo'), Modelo_repartidor::VEHICULOS) ? $this->post('vehiculo') : 'moto',
            // Combustible: vacío = se usa el típico del vehículo al calcular el costo.
            'combustible'      => array_key_exists($this->post('combustible'), Combustible::TIPOS) ? $this->post('combustible') : null,
            'rendimiento_km_l' => is_numeric($this->post('rendimiento')) && (float) $this->post('rendimiento') > 0
                ? round((float) $this->post('rendimiento'), 2) : null,
        ];
    }

    public function guarda_repartidor()
    {
        if (! $this->validate($this->configuracion['reglas'], $this->configuracion['errores'])) {
            return $this->volverConErrores('error');
        }

        try {
            (new Modelo_repartidor())->insert($this->datosDelFormulario());

            return redirect()->to($this->destino('lista_repartidor'))
                ->with('mensaje', 'Repartidor registrado correctamente');
        } catch (Throwable $e) {
            return redirect()->to('lista_repartidor')
                ->with('error', PdoModelo::mensajeSeguro($e, 'Ocurrió un error inesperado al procesar la solicitud.'));
        }
    }

    public function modifica()
    {
        $id = $this->request->getPost('id');

        if (! ctype_digit((string) $id)) {
            return redirect()->to('lista_repartidor')->with('error', 'ID inválido');
        }

        if (! $this->validate($this->configuracion['reglas'], $this->configuracion['errores'])) {
            return $this->volverConErrores('error');
        }

        try {
            (new Modelo_repartidor())->update($id, $this->datosDelFormulario());

            return redirect()->to('lista_repartidor')->with('mensaje', 'Repartidor actualizado correctamente');
        } catch (Throwable $e) {
            return redirect()->to('lista_repartidor')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo actualizar el repartidor.'));
        }
    }

    public function lista_repartidor()
    {
        $buscar       = $this->textoBusqueda();
        $m_repartidor = new Modelo_repartidor();

        // Cuentas nuevas del grupo "repartidor" → ficha de repartidor automática.
        $nuevos = 0;
        try {
            $nuevos = $m_repartidor->sincronizarCuentas();
        } catch (Throwable $e) {
            log_message('error', 'No se pudieron sincronizar las cuentas de repartidor: ' . $e->getMessage());
        }

        $datos = [
            'repartidores' => $m_repartidor->listar($buscar),
            'pager'        => $m_repartidor->pager,
            'buscar'       => $buscar,
            'conCuenta'    => $m_repartidor->tieneCuenta(),
            'nuevos'       => $nuevos,
        ];

        return view('lista_repartidor', $datos);
    }

    public function recupera($id = null)
    {
        $m_repartidor          = new Modelo_repartidor();
        $datos['repartidores'] = $m_repartidor->find($id);

        if (! $datos['repartidores']) {
            return redirect()->to('lista_repartidor');
        }

        return view('modifica_repartidor', $datos);
    }

    // ─────────────────────────────────────────────────────────────
    // EL PROPIO REPARTIDOR (grupo "repartidor"): completa o corrige su ficha
    // ─────────────────────────────────────────────────────────────

    /** GET repartidor/mis-datos */
    public function mis_datos()
    {
        $m    = new Modelo_repartidor();
        $ficha = $this->fichaPropia($m);

        if ($ficha === null) {
            return redirect()->to('repartidor/entregas')->with('error', 'Tu cuenta aún no tiene ficha de repartidor. Avisa al administrador.');
        }

        $incompleta = Modelo_repartidor::faltanDatos($ficha);
        // La nota de alta automática es para el personal; no se le muestra como suya.
        if (str_starts_with((string) ($ficha['notas'] ?? ''), 'Alta automática')) {
            $ficha['notas'] = '';
        }
        // Al alta automática se le puso el usuario como nombre: mejor que escriba el suyo.
        if ($incompleta && trim((string) $ficha['ape_pat']) === '') {
            $ficha['nombre'] = '';
        }

        return view('mis_datos_repartidor', [
            'rep'        => $ficha,
            'incompleta' => $incompleta,
        ]);
    }

    /** POST repartidor/mis-datos: solo puede tocar SU ficha (se busca por la cuenta en sesión, nunca por un id del formulario). */
    public function guarda_mis_datos()
    {
        $m     = new Modelo_repartidor();
        $ficha = $this->fichaPropia($m);

        if ($ficha === null) {
            return redirect()->to('repartidor/entregas')->with('error', 'Tu cuenta aún no tiene ficha de repartidor. Avisa al administrador.');
        }

        if (! $this->validate($this->configuracion['reglas'], $this->configuracion['errores'])) {
            return $this->volverConErrores('error');
        }

        $datos = $this->datosDelFormulario();

        try {
            $m->update((int) $ficha['id'], $datos);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', PdoModelo::mensajeSeguro($e, 'No se pudieron guardar tus datos.'));
        }

        session()->set(\App\Filters\RepartidorDatosGuard::LLAVE_SESION, (int) auth()->id());

        return redirect()->to('repartidor/entregas')->with('mensaje', '¡Listo! Tus datos quedaron guardados.');
    }

    private function fichaPropia(Modelo_repartidor $m): ?array
    {
        $idUser = (int) auth()->id();

        try {
            $m->sincronizarCuentas($idUser);
        } catch (Throwable $e) {
            log_message('error', 'No se pudo crear la ficha de repartidor: ' . $e->getMessage());
        }

        return $m->deUsuario($idUser);
    }

    public function eliminar_datos($id = null)
    {
        $m_repartidor = new Modelo_repartidor();
        $r            = ctype_digit((string) $id) ? $m_repartidor->find($id) : null;

        if (! $r) {
            return redirect()->to('lista_repartidor');
        }

        // Con historial no se borra: las entregas, rutas y pedidos lo siguen apuntando.
        if ($m_repartidor->tieneHistorial((int) $id)) {
            return redirect()->to('lista_repartidor')->with('error',
                'No se puede eliminar a ' . Modelo_repartidor::nombreCompleto($r) . ' porque tiene pedidos, entregas o rutas a su nombre. '
                . ($m_repartidor->tieneBaja() ? 'Usa "Dar de baja": conserva su historial y deja de aparecer al asignar.' : ''));
        }

        try {
            $m_repartidor->delete($id);

            return redirect()->to('lista_repartidor')->with('mensaje', 'Repartidor eliminado correctamente.');
        } catch (Throwable $e) {
            $mensaje = PdoModelo::esErrorForaneo($e)
                ? 'No se puede eliminar el repartidor porque tiene pedidos'
                : PdoModelo::mensajeSeguro($e, 'No se pudo eliminar el repartidor.');

            return redirect()->to('lista_repartidor')->with('error', $mensaje);
        }
    }

    /**
     * POST baja_repartidor/{id}: baja lógica de una ficha SIN cuenta de repartidor (capturada a
     * mano). Si la cuenta tiene el rol, la baja la hace el superadmin al quitárselo en Roles,
     * para que el rol y la ficha no se contradigan.
     */
    public function baja($id = null)
    {
        $m = new Modelo_repartidor();
        $r = ctype_digit((string) $id) ? $m->find($id) : null;

        if (! $r) {
            return redirect()->to('lista_repartidor');
        }
        if ($m->cuentaEsRepartidor($r)) {
            return redirect()->to('lista_repartidor')->with('error',
                Modelo_repartidor::nombreCompleto($r) . ' entra con una cuenta de repartidor: su baja la hace el superadmin al cambiarle el rol en Roles.');
        }

        try {
            $baja = $m->darDeBaja((int) $id, mb_substr($this->post('motivo'), 0, 255), (int) auth()->id(), $this->post('cancelar_pendientes') === '1');
        } catch (DomainException $e) {
            // Regla de negocio (entregas en camino, etc.): su texto es para el usuario.
            return redirect()->to('lista_repartidor')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to('lista_repartidor')->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo dar de baja al repartidor.'));
        }

        $mensaje = Modelo_repartidor::nombreCompleto($r) . ' quedó dado de baja. Conserva su historial, pero ya no aparece al asignar.';
        if ($baja['entregas_canceladas'] > 0) {
            $mensaje .= ' Se cancelaron ' . $baja['entregas_canceladas'] . ' entrega(s) que aún no salían: asigna esos pedidos a otro repartidor.';
        }
        log_message('notice', 'Repartidores: la cuenta {usuario} dio de baja al repartidor #{id}.', ['usuario' => auth()->id(), 'id' => (int) $id]);

        return redirect()->to('lista_repartidor')->with('mensaje', $mensaje);
    }

    /** POST reactiva_repartidor/{id}: vuelve a activar una ficha dada de baja (con su mismo historial). */
    public function reactiva($id = null)
    {
        $m = new Modelo_repartidor();
        $r = ctype_digit((string) $id) ? $m->find($id) : null;

        if (! $r) {
            return redirect()->to('lista_repartidor');
        }
        if (! empty($r['id_user']) && ! $m->cuentaEsRepartidor($r)) {
            return redirect()->to('lista_repartidor')->with('error',
                'La cuenta de ' . Modelo_repartidor::nombreCompleto($r) . ' ya no tiene el rol de repartidor: el superadmin debe devolvérselo en Roles (eso reactiva su ficha).');
        }

        try {
            $ok = $m->reactivar((int) $id);
        } catch (Throwable $e) {
            return redirect()->to('lista_repartidor')->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo reactivar al repartidor.'));
        }
        log_message('notice', 'Repartidores: la cuenta {usuario} reactivó al repartidor #{id}.', ['usuario' => auth()->id(), 'id' => (int) $id]);

        return redirect()->to('lista_repartidor')->with(
            $ok ? 'mensaje' : 'error',
            $ok ? Modelo_repartidor::nombreCompleto($r) . ' está activo de nuevo, con todo su historial.' : 'Ese repartidor ya estaba activo.'
        );
    }
}
