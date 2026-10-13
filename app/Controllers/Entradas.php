<?php

namespace App\Controllers;

use App\Models\Modelo_entrada;
use App\Models\Modelo_producto;
use App\Models\PdoModelo;
use Throwable;

class Entradas extends BaseController
{
    private function reglas(): array
    {
        return [
            'f_ent'       => 'required|valid_date[Y-m-d]',
            'f_cad'       => 'permit_empty|valid_date[Y-m-d]',
            'cant'        => 'required|numeric|greater_than[0]|less_than[100000000]',
            'u_com'       => 'required|in_list[Caja,Arpilla,Bulto,Tonelada,Mazo]',
            'u_ven'       => 'required|in_list[Kilogramo,Litro,Caja,Pieza,Domo,Ramo]',
            'equi'        => 'required|numeric|greater_than[0]|less_than[10000000000]',
            'p_compra'    => 'required|numeric|greater_than_equal_to[0]|less_than[100000000]',
            'p_venta'     => 'permit_empty|numeric|greater_than_equal_to[0]|less_than[10000000000]',
            'id_producto' => 'required|is_natural_no_zero',
        ];
    }

    private function mensajes(): array
    {
        return [
            'f_ent'       => ['required' => 'La Fecha de entrada es obligatoria.', 'valid_date' => 'La Fecha de entrada no es válida.'],
            'f_cad'       => ['valid_date' => 'La Fecha de caducidad no es válida.'],
            'cant'        => ['required' => 'La Cantidad es obligatoria.', 'numeric' => 'La Cantidad debe ser un número.', 'greater_than' => 'La Cantidad debe ser mayor que 0.'],
            'u_com'       => ['required' => 'Selecciona la unidad de compra.', 'in_list' => 'Unidad de compra no válida.'],
            'u_ven'       => ['required' => 'Selecciona la unidad de venta.', 'in_list' => 'Unidad de venta no válida.'],
            'equi'        => ['required' => 'La Equivalencia es obligatoria.', 'numeric' => 'La Equivalencia debe ser un número.', 'greater_than' => 'La Equivalencia debe ser mayor que 0.'],
            'p_compra'    => ['required' => 'El Precio de compra es obligatorio.', 'numeric' => 'El Precio de compra debe ser un número.'],
            'p_venta'     => ['numeric' => 'El Precio de venta debe ser un número.'],
            'id_producto' => ['required' => 'Selecciona un producto.', 'is_natural_no_zero' => 'Producto no válido.'],
        ];
    }

    private function datosDelFormulario(): array
    {
        $f_cad = $this->post('f_cad') ?: $this->post('fecha_cad');

        return [
            'fecha'           => $this->post('f_ent'),
            'fecha_cad'       => $f_cad === '' ? null : $f_cad,
            'cantidad'        => $this->post('cant'),
            'u_compra'        => $this->post('u_com'),
            'u_venta'         => $this->post('u_ven'),
            'precio_compra_u' => $this->post('p_compra'),
            'precio_venta_u'  => $this->post('p_venta') === '' ? 0 : $this->post('p_venta'),
            'equivalente'     => $this->post('equi'),
            'id_producto'     => (int) $this->request->getPost('id_producto'),
        ];
    }

    public function crea_entrada()
    {
        $datos['frutas']    = (new Modelo_producto())->findAll();
        $datos['productos'] = $datos['frutas'];

        return view('crea_entrada', $datos);
    }

    public function guarda_entrada()
    {
        if (! $this->validate($this->reglas(), $this->mensajes())) {
            return $this->volverConErrores('error');
        }

        try {
            (new Modelo_entrada())->insert($this->datosDelFormulario());

            return redirect()->to($this->destino('lista_entrada'))
                ->with('mensaje', 'Entrada registrada correctamente');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo registrar la entrada.'));
        }
    }

    public function lista_entrada()
    {
        $buscar     = $this->textoBusqueda();
        $m_entrada  = new Modelo_entrada();

        $datos = [
            'entradas'  => $m_entrada->listar($buscar),
            'pager'     => $m_entrada->pager,
            'buscar'    => $buscar,
            'productos' => (new Modelo_producto())->findAll(),
        ];

        return view('lista_entrada', $datos);
    }

    public function recupera($id = null)
    {
        $entrada = (new Modelo_entrada())->find($id);

        if (! $entrada) {
            return redirect()->to('lista_entrada');
        }

        return view('modifica_entrada', [
            'entradas'  => $entrada,
            'productos' => (new Modelo_producto())->findAll(),
        ]);
    }

    public function eliminar_datos($id = null)
    {
        $m_entrada = new Modelo_entrada();

        if (! $m_entrada->find($id)) {
            return redirect()->to('lista_entrada');
        }

        try {
            $m_entrada->delete($id);

            return redirect()->to('lista_entrada')->with('mensaje', 'Entrada eliminada correctamente');
        } catch (Throwable $e) {
            $mensaje = PdoModelo::esErrorForaneo($e)
                ? 'No se puede eliminar la entrada porque tiene mermas registradas.'
                : PdoModelo::mensajeSeguro($e, 'No se pudo eliminar la entrada.');

            return redirect()->to('lista_entrada')->with('error', $mensaje);
        }
    }

    public function modifica_entrada()
    {
        $id = $this->request->getPost('id');

        if (! ctype_digit((string) $id)) {
            return redirect()->to('lista_entrada')->with('error', 'ID inválido');
        }

        if (! $this->validate($this->reglas(), $this->mensajes())) {
            return redirect()->to('lista_entrada')
                ->with('error', 'Por favor, complete todos los campos obligatorios: ' . $this->primerError());
        }

        try {
            (new Modelo_entrada())->update($id, $this->datosDelFormulario());

            return redirect()->to('lista_entrada')->with('mensaje', 'Entrada actualizada correctamente');
        } catch (Throwable $e) {
            return redirect()->to('lista_entrada')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo actualizar la entrada.'));
        }
    }
}
