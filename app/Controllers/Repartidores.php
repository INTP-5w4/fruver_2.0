<?php

namespace App\Controllers;

use App\Models\Modelo_repartidor;
use App\Models\PdoModelo;
use Throwable;

class Repartidores extends BaseController
{
    private array $configuracion = [
        'reglas' => [
            'nom'     => 'required|max_length[100]',
            'ape_pat' => 'required|max_length[100]',
            'ape_mat' => 'required|max_length[100]',
            'tel'     => 'required|numeric|max_length[12]',
            'dir'     => 'required|max_length[500]',
            'not'     => 'permit_empty|max_length[500]',
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

        $datos = [
            'repartidores' => $m_repartidor->listar($buscar),
            'pager'        => $m_repartidor->pager,
            'buscar'       => $buscar,
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

    public function eliminar_datos($id = null)
    {
        $m_repartidor = new Modelo_repartidor();

        if (! $m_repartidor->find($id)) {
            return redirect()->to('lista_repartidor');
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
}
