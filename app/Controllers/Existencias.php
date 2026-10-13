<?php

namespace App\Controllers;

use App\Models\Modelo_existencia;
use App\Models\Modelo_producto;
use App\Models\PdoModelo;
use Throwable;

class Existencias extends BaseController
{
    private array $reglas = [
        'e_total'     => 'required|integer|greater_than_equal_to[0]|less_than[2000000000]',
        'e_bloqueado' => 'required|integer|greater_than_equal_to[0]|less_than[2000000000]',
        'e_venta'     => 'required|integer|greater_than_equal_to[0]|less_than[2000000000]',
        'id_producto' => 'required|is_natural_no_zero',
    ];

    private array $mensajes = [
        'e_total'     => ['required' => 'Completa todos los campos.', 'integer' => 'La existencia total debe ser un número entero.', 'greater_than_equal_to' => 'La existencia total no puede ser negativa.'],
        'e_bloqueado' => ['required' => 'Completa todos los campos.', 'integer' => 'La existencia bloqueada debe ser un número entero.', 'greater_than_equal_to' => 'La existencia bloqueada no puede ser negativa.'],
        'e_venta'     => ['required' => 'Completa todos los campos.', 'integer' => 'La existencia para venta debe ser un número entero.', 'greater_than_equal_to' => 'La existencia para venta no puede ser negativa.'],
        'id_producto' => ['required' => 'Completa todos los campos.', 'is_natural_no_zero' => 'Producto no válido.'],
    ];

    /** @return array<string,mixed>|null null si la fecha/hora no es válida */
    private function datosDelFormulario(): ?array
    {
        $fecha = $this->fechaHoraSql($this->post('fecha'));

        if ($fecha === null) {
            return null;
        }

        return [
            'e_total'     => (int) $this->request->getPost('e_total'),
            'e_bloqueado' => (int) $this->request->getPost('e_bloqueado'),
            'e_venta'     => (int) $this->request->getPost('e_venta'),
            'fecha'       => $fecha,
            'id_producto' => (int) $this->request->getPost('id_producto'),
        ];
    }

    public function crea_existencia()
    {
        $datos = [
            'productos' => (new Modelo_producto())->findAll(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        return view('crea_existencia', $datos);
    }

    public function guarda_existencia()
    {
        $datos = $this->validate($this->reglas, $this->mensajes) ? $this->datosDelFormulario() : null;

        if ($datos === null) {
            return redirect()->to('lista_existencia')->with('error', $this->validator && $this->validator->getErrors() ? $this->primerError() : 'Completa todos los campos.');
        }

        try {
            (new Modelo_existencia())->insert($datos);

            return redirect()->to($this->destino('lista_existencia'))
                ->with('mensaje', $this->request->getPost('origen') === 'main_page' ? 'Existencia registrada correctamente' : 'Existencia registrada.');
        } catch (Throwable $e) {
            return redirect()->to('lista_existencia')->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo registrar la existencia.'));
        }
    }

    public function lista_existencia()
    {
        $buscar = $this->textoBusqueda();

        $datos = [
            'buscar'      => $buscar,
            'existencias' => (new Modelo_existencia())->listarFiltrado($buscar),
            'productos'   => array_column((new Modelo_producto())->findAll(), null, 'id'),
        ];

        return view('lista_existencia', $datos);
    }

    public function eliminar_datos($id = null)
    {
        try {
            (new Modelo_existencia())->delete($id);

            return redirect()->to('lista_existencia')->with('mensaje', 'Existencia eliminada correctamente');
        } catch (Throwable $e) {
            return redirect()->to('lista_existencia')->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo eliminar la existencia.'));
        }
    }

    public function recupera($id = null)
    {
        $existencia = (new Modelo_existencia())->find($id);

        if (! $existencia) {
            return redirect()->to('lista_existencia');
        }

        return view('modifica_existencia', [
            'existencias' => $existencia,
            'productos'   => array_column((new Modelo_producto())->findAll(), null, 'id'),
        ]);
    }

    public function modifica()
    {
        $id = $this->request->getPost('id');

        if (! ctype_digit((string) $id)) {
            return redirect()->to('lista_existencia')->with('error', 'ID inválido');
        }

        $datos = $this->validate($this->reglas, $this->mensajes) ? $this->datosDelFormulario() : null;

        if ($datos === null) {
            return redirect()->to('lista_existencia')->with('error', 'Completa todos los campos.');
        }

        try {
            (new Modelo_existencia())->update($id, $datos);

            return redirect()->to('lista_existencia')->with('mensaje', 'Existencia actualizada.');
        } catch (Throwable $e) {
            return redirect()->to('lista_existencia')->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo actualizar la existencia.'));
        }
    }
}
