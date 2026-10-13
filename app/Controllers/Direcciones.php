<?php

namespace App\Controllers;

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
    ];

    private array $mensajes = [
        'col'        => ['required' => 'La Colonia es obligatoria.',   'max_length' => 'La Colonia admite máximo 200 caracteres.'],
        'calle'      => ['required' => 'La Calle es obligatoria.',     'max_length' => 'La Calle admite máximo 200 caracteres.'],
        'num'        => ['required' => 'El Número es obligatorio.',    'max_length' => 'El Número admite máximo 50 caracteres.'],
        'mun'        => ['required' => 'El Municipio es obligatorio.', 'max_length' => 'El Municipio admite máximo 200 caracteres.'],
        'edo'        => ['required' => 'El Estado es obligatorio.',    'max_length' => 'El Estado admite máximo 100 caracteres.'],
        'id_cliente' => ['required' => 'Selecciona un cliente.',       'is_natural_no_zero' => 'Selecciona un cliente válido.'],
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
        ];
    }

    public function crea_direccion()
    {
        $m_cliente                  = new Modelo_cliente();
        $datos_clientes['clientes'] = $m_cliente->findAll();

        return view('crea_direccion', $datos_clientes);
    }

    public function guarda_direccion()
    {
        if (! $this->validate($this->reglas, $this->mensajes)) {
            return $this->volverConErrores('error');
        }

        try {
            (new Modelo_direccion())->insert($this->datosDelFormulario());

            return redirect()->to($this->destino('lista_direccion'))
                ->with('mensaje', 'Dirección registrada correctamente');
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

        $datos = [
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
            (new Modelo_direccion())->update($id, $this->datosDelFormulario());

            return redirect()->to('lista_direccion')->with('mensaje', 'Dirección actualizada correctamente');
        } catch (Throwable $e) {
            return redirect()->to('lista_direccion')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo actualizar la dirección.'));
        }
    }
}
