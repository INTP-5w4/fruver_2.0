<?php

namespace App\Controllers;

use App\Models\Modelo_entrada;
use App\Models\Modelo_existencia;
use App\Models\Modelo_merma;
use App\Models\PdoModelo;
use Throwable;

class Mermas extends BaseController
{
    private function reglas(string $campoCantidad): array
    {
        return [
            $campoCantidad => 'required|numeric|greater_than[0]|less_than[100000000]',
            'fecha'        => 'required|valid_date[Y-m-d]',
            'notas'        => 'permit_empty|max_length[500]',
            'id_entrada'   => 'required|is_natural_no_zero',
        ];
    }

    private function mensajes(string $campoCantidad): array
    {
        return [
            $campoCantidad => ['required' => 'Por favor, complete todos los campos obligatorios.', 'numeric' => 'La cantidad debe ser un número.', 'greater_than' => 'La cantidad debe ser mayor que 0.'],
            'fecha'        => ['required' => 'Por favor, complete todos los campos obligatorios.', 'valid_date' => 'La fecha no es válida.'],
            'notas'        => ['max_length' => 'Las notas admiten máximo 500 caracteres.'],
            'id_entrada'   => ['required' => 'Por favor, complete todos los campos obligatorios.', 'is_natural_no_zero' => 'Entrada no válida.'],
        ];
    }

    private function datosDelFormulario(string $campoCantidad): array
    {
        return [
            'cantidad'   => $this->post($campoCantidad),
            'fecha'      => $this->post('fecha'),
            'notas'      => $this->post('notas'),
            'id_entrada' => (int) $this->request->getPost('id_entrada'),
        ];
    }

    public function crea_merma()
    {
        $datos = [
            'entradas'         => (new Modelo_entrada())->getEntradasConProducto(),
            'stockPorProducto' => (new Modelo_existencia())->stockDisponiblePorProducto(),
            'date'             => date('Y-m-d'),
        ];

        return view('crea_merma', $datos);
    }

    public function guarda_merma()
    {
        if (! $this->validate($this->reglas('cant'), $this->mensajes('cant'))) {
            return redirect()->to('crea_merma')->with('error', $this->primerError());
        }

        try {
            (new Modelo_merma())->insert($this->datosDelFormulario('cant'));

            return redirect()->to($this->destino('lista_merma'))->with('mensaje', 'Merma registrada exitosamente.');
        } catch (Throwable $e) {
            return redirect()->to('lista_merma')->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo registrar la merma.'));
        }
    }

    public function lista_merma()
    {
        $buscar   = $this->textoBusqueda();
        $m_merma  = new Modelo_merma();

        $datos = [
            'mermas'           => $m_merma->listar($buscar),
            'entradas'         => (new Modelo_entrada())->getEntradasConProducto(),
            'stockPorProducto' => (new Modelo_existencia())->stockDisponiblePorProducto(),
            'pager'            => $m_merma->pager,
            'buscar'           => $buscar,
        ];

        return view('lista_merma', $datos);
    }

    public function recupera($id = null)
    {
        $merma = (new Modelo_merma())->find($id);

        if (! $merma) {
            return redirect()->to('lista_merma');
        }

        return view('modifica_merma', [
            'mermas'   => $merma,
            'entradas' => (new Modelo_entrada())->getEntradasConProducto(),
        ]);
    }

    public function eliminar_datos($id = null)
    {
        try {
            (new Modelo_merma())->delete($id);

            return redirect()->to('lista_merma')->with('mensaje', 'Merma eliminada correctamente');
        } catch (Throwable $e) {
            return redirect()->to('lista_merma')->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo eliminar la merma.'));
        }
    }

    public function modifica()
    {
        $id = $this->request->getPost('id');

        if (! ctype_digit((string) $id)) {
            return redirect()->to('lista_merma')->with('error', 'ID inválido');
        }

        if (! $this->validate($this->reglas('cantidad'), $this->mensajes('cantidad'))) {
            return redirect()->to('lista_merma')->with('error', $this->primerError());
        }

        try {
            (new Modelo_merma())->update($id, $this->datosDelFormulario('cantidad'));

            return redirect()->to('lista_merma')->with('mensaje', 'Merma actualizada correctamente');
        } catch (Throwable $e) {
            return redirect()->to('lista_merma')->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo actualizar la merma.'));
        }
    }
}
