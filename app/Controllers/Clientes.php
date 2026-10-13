<?php

namespace App\Controllers;

use App\Models\Modelo_cliente;
use App\Models\PdoModelo;
use Throwable;

class Clientes extends BaseController
{
    private array $configuracion = [
        'reglas' => [
            'nom'     => 'required|max_length[100]',
            'ape_pat' => 'required|max_length[100]',
            'ape_mat' => 'required|max_length[100]',
            'tel'     => 'required|numeric|max_length[12]',
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
        ],
    ];

    public function crea_cliente()
    {
        return view('crea_cliente');
    }

    public function guarda_cliente()
    {
        if (! $this->validate($this->configuracion['reglas'], $this->configuracion['errores'])) {
            return $this->volverConErrores('error');
        }

        $m_cliente = new Modelo_cliente();

        $datos = [
            'nombre'   => $this->post('nom'),
            'ape_pat'  => $this->post('ape_pat'),
            'ape_mat'  => $this->post('ape_mat'),
            'telefono' => $this->post('tel'),
        ];

        try {
            $m_cliente->insert($datos);

            return redirect()->to($this->destino('lista_cliente'))
                ->with('mensaje', 'Cliente registrado correctamente');
        } catch (Throwable $e) {
            // El trigger no_duplica_cliente avisa con un "Error: ..." legible.
            return redirect()->back()->withInput()->with('error', PdoModelo::mensajeSeguro($e, 'Ocurrió un error inesperado al procesar la solicitud.'));
        }
    }

    public function lista_cliente()
    {
        $buscar    = $this->textoBusqueda();
        $m_cliente = new Modelo_cliente();

        $datos['clientes'] = $m_cliente->listar($buscar);
        $datos['pager']    = $m_cliente->pager;
        $datos['buscar']   = $buscar;

        return view('lista_cliente', $datos);
    }

    public function modifica()
    {
        $id = $this->request->getPost('id');

        if (! $this->validate($this->configuracion['reglas'], $this->configuracion['errores'])) {
            return $this->volverConErrores('error2');
        }

        if (! ctype_digit((string) $id)) {
            return redirect()->to('lista_cliente')->with('error', 'ID inválido');
        }

        $m_cliente = new Modelo_cliente();

        $datos = [
            'nombre'   => $this->post('nom'),
            'ape_pat'  => $this->post('ape_pat'),
            'ape_mat'  => $this->post('ape_mat'),
            'telefono' => $this->post('tel'),
        ];

        try {
            $m_cliente->update($id, $datos);

            return redirect()->to('lista_cliente')->with('mensaje', 'Cliente actualizado correctamente');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error2', PdoModelo::mensajeSeguro($e, 'Ocurrió un error inesperado al procesar la actualización.'));
        }
    }

    public function recupera($id = null)
    {
        $m_cliente         = new Modelo_cliente();
        $datos['clientes'] = $m_cliente->find($id);

        if (! $datos['clientes']) {
            return redirect()->to('lista_cliente');
        }

        return view('modifica_cliente', $datos);
    }

    public function eliminar_datos($id = null)
    {
        $m_cliente = new Modelo_cliente();

        if (! $m_cliente->find($id)) {
            return redirect()->to('lista_cliente');
        }

        try {
            $m_cliente->delete($id);

            return redirect()->to('lista_cliente')->with('mensaje', 'Cliente eliminado correctamente.');
        } catch (Throwable $e) {
            $mensaje = PdoModelo::esErrorForaneo($e)
                ? 'No se puede eliminar el cliente porque tiene registros relacionados (pedidos, direcciones, etc.).'
                : PdoModelo::mensajeSeguro($e, 'No se pudo eliminar el cliente.');

            return redirect()->to('lista_cliente')->with('error', $mensaje);
        }
    }
}
