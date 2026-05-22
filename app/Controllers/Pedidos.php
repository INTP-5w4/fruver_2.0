<?php

namespace App\Controllers;

use App\Models\Modelo_cliente;
use App\Models\Modelo_pedido;
use App\Models\Modelo_repartidor;
use App\Models\Modelo_productopedidos;
use CodeIgniter\Controller;

class Pedidos extends Controller
{

    // ─────────────────────────────────────────────────────────────
    // CREAR PEDIDO
    // ─────────────────────────────────────────────────────────────
    public function crea_pedido()
    {

        $m_cliente    = new Modelo_cliente();
        $m_repartidor = new Modelo_repartidor();

        $datos = [
            'clientes'     => $m_cliente->findAll(),
            'repartidores' => $m_repartidor->findAll(),
        ];

        return view('crea_pedido', $datos);
    }

    // ─────────────────────────────────────────────────────────────
    // GUARDAR PEDIDO
    // ─────────────────────────────────────────────────────────────
    public function guarda_pedido()
    {

        $m_pedido = new Modelo_pedido();

        $datos = [
            'fecha'         => $this->request->getPost('fecha'),
            'id_cliente'    => $this->request->getPost('id_cliente'),
            'id_repartidor' => $this->request->getPost('id_repartidor'),
        ];

        // VALIDAR CAMPOS
        if (
            empty($datos['fecha']) ||
            empty($datos['id_cliente']) ||
            empty($datos['id_repartidor'])
        ) {

            $m_cliente    = new Modelo_cliente();
            $m_repartidor = new Modelo_repartidor();

            $datos_recuperados = [
                'clientes'     => $m_cliente->findAll(),
                'repartidores' => $m_repartidor->findAll(),
            ];

            return view('crea_pedido', $datos_recuperados);
        }

        $m_pedido->insert($datos);

        // REDIRECCIÓN SEGÚN ORIGEN
        if ($this->request->getPost('origen') == 'main_page') {

            return redirect()->to('/')
                ->with('mensaje', 'Pedido creado exitosamente');
        }

        return redirect()->to('lista_pedido')
            ->with('mensaje', 'Pedido creado exitosamente');
    }

    // ─────────────────────────────────────────────────────────────
    // LISTA PEDIDOS
    // ─────────────────────────────────────────────────────────────
    public function lista_pedido()
    {

        $buscar = $this->request->getGet('buscar') ?? '';

        $m_pedido     = new Modelo_pedido();
        $m_cliente    = new Modelo_cliente();
        $m_repartidor = new Modelo_repartidor();
        $m_pps        = new Modelo_productopedidos();

        // PAGINACIÓN
        $pedidos = $m_pedido
            ->orderBy('id', 'DESC')
            ->buscarPedidos($buscar)
            ->paginate(20);

        $datos = [
            'pedidos'      => $pedidos,
            'pager'        => $m_pedido->pager,
            'buscar'       => $buscar,

            // CLIENTES INDEXADOS POR ID
            'clientes' => array_column(
                $m_cliente->findAll(),
                null,
                'id'
            ),

            // REPARTIDORES INDEXADOS POR ID
            'repartidores' => array_column(
                $m_repartidor->findAll(),
                null,
                'id'
            ),

            // PRODUCTOS PEDIDOS
            'pps' => $m_pps->findAll(),
        ];

        return view('lista_pedido', $datos);
    }

    // ─────────────────────────────────────────────────────────────
    // RECUPERAR
    // ─────────────────────────────────────────────────────────────
    public function recupera($id = null)
    {

        $m_pedido     = new Modelo_pedido();
        $m_cliente    = new Modelo_cliente();
        $m_repartidor = new Modelo_repartidor();

        $datos = [
            'pedidos'      => $m_pedido->find($id),
            'clientes'     => $m_cliente->findAll(),
            'repartidores' => $m_repartidor->findAll(),
        ];

        return view('modifica_pedido', $datos);
    }

    // ─────────────────────────────────────────────────────────────
    // ELIMINAR
    // ─────────────────────────────────────────────────────────────
    public function eliminar_datos($id = null)
    {

        $m_pedido = new Modelo_pedido();

        try {

            $m_pedido->delete($id);

            return redirect()->to('lista_pedido')
                ->with('mensaje', 'Pedido eliminado correctamente.');

        } catch (\Exception $e) {

            $codigo = $e->getPrevious()
                ? $e->getPrevious()->getCode()
                : $e->getCode();

            // ERROR DE FOREIGN KEY
            if ($codigo == 1451) {

                return redirect()->to('lista_pedido')
                    ->with(
                        'error',
                        'No se puede eliminar este pedido porque tiene registros relacionados.'
                    );
            }

            return redirect()->to('lista_pedido')
                ->with('error', 'Error inesperado al eliminar.');
        }
    }

    // ─────────────────────────────────────────────────────────────
    // MODIFICAR
    // ─────────────────────────────────────────────────────────────
    public function modifica()
    {

        $m_pedido = new Modelo_pedido();

        $id = $this->request->getPost('id');

        if (empty($id)) {

            return redirect()->to('lista_pedido')
                ->with('error', 'ID inválido');
        }

        $datos = [
            'fecha'         => $this->request->getPost('fecha'),
            'id_cliente'    => $this->request->getPost('id_cliente'),
            'id_repartidor' => $this->request->getPost('id_repartidor'),
        ];

        $m_pedido->update($id, $datos);

        return redirect()->to('lista_pedido')
            ->with('mensaje', 'Pedido actualizado exitosamente');
    }
}