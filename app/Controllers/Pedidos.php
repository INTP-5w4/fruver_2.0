<?php

namespace App\Controllers;

use App\Models\Modelo_cliente;
use App\Models\Modelo_pedido;
use App\Models\Modelo_productopedidos;
use App\Models\Modelo_repartidor;
use App\Models\PdoModelo;
use Throwable;

class Pedidos extends BaseController
{
    private array $reglas = [
        'fecha'         => 'required|valid_date[Y-m-d]',
        'id_cliente'    => 'required|is_natural_no_zero',
        'id_repartidor' => 'permit_empty|is_natural_no_zero',
    ];

    private function datosDelFormulario(): array
    {
        $repartidor = $this->post('id_repartidor');

        return [
            'fecha'         => $this->post('fecha'),
            'id_cliente'    => (int) $this->request->getPost('id_cliente'),
            'id_repartidor' => $repartidor === '' ? null : (int) $repartidor,
        ];
    }

    public function crea_pedido()
    {
        $datos = [
            'clientes'     => (new Modelo_cliente())->findAll(),
            'repartidores' => (new Modelo_repartidor())->findAll(),
        ];

        return view('crea_pedido', $datos);
    }

    public function guarda_pedido()
    {
        if (! $this->validate($this->reglas) || $this->post('id_repartidor') === '') {
            return redirect()->to('crea_pedido')->with('error', 'Completa fecha, cliente y repartidor.');
        }

        try {
            (new Modelo_pedido())->insert($this->datosDelFormulario());

            return redirect()->to($this->destino('lista_pedido'))->with('mensaje', 'Pedido creado exitosamente');
        } catch (Throwable $e) {
            return redirect()->to('lista_pedido')->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo crear el pedido.'));
        }
    }

    public function lista_pedido()
    {
        $buscar   = $this->textoBusqueda();
        $m_pedido = new Modelo_pedido();

        $datos = [
            'pedidos' => $m_pedido->listar($buscar),
            'pager'   => $m_pedido->pager,
            'buscar'  => $buscar,

            // Clientes y repartidores indexados por id
            'clientes'     => array_column((new Modelo_cliente())->findAll(), null, 'id'),
            'repartidores' => array_column((new Modelo_repartidor())->findAll(), null, 'id'),

            // Productos pedidos
            'pps' => (new Modelo_productopedidos())->findAll(),
        ];

        return view('lista_pedido', $datos);
    }

    public function recupera($id = null)
    {
        $pedido = (new Modelo_pedido())->find($id);

        if (! $pedido) {
            return redirect()->to('lista_pedido');
        }

        return view('modifica_pedido', [
            'pedidos'      => $pedido,
            'clientes'     => (new Modelo_cliente())->findAll(),
            'repartidores' => (new Modelo_repartidor())->findAll(),
        ]);
    }

    public function eliminar_datos($id = null)
    {
        try {
            (new Modelo_pedido())->delete($id);

            return redirect()->to('lista_pedido')->with('mensaje', 'Pedido eliminado correctamente.');
        } catch (Throwable $e) {
            $mensaje = PdoModelo::esErrorForaneo($e)
                ? 'No se puede eliminar este pedido porque tiene registros relacionados.'
                : PdoModelo::mensajeSeguro($e, 'Error inesperado al eliminar.');

            return redirect()->to('lista_pedido')->with('error', $mensaje);
        }
    }

    public function modifica()
    {
        $id = $this->request->getPost('id');

        if (! ctype_digit((string) $id)) {
            return redirect()->to('lista_pedido')->with('error', 'ID inválido');
        }

        if (! $this->validate($this->reglas)) {
            return redirect()->to('lista_pedido')->with('error', $this->primerError());
        }

        try {
            (new Modelo_pedido())->update($id, $this->datosDelFormulario());

            return redirect()->to('lista_pedido')->with('mensaje', 'Pedido actualizado exitosamente');
        } catch (Throwable $e) {
            return redirect()->to('lista_pedido')->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo actualizar el pedido.'));
        }
    }
}
