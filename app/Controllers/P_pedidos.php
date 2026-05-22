<?php

namespace App\Controllers;

use App\Models\Modelo_pedido;
use App\Models\Modelo_producto;
use App\Models\Modelo_productopedidos;
use App\Models\Modelo_estatus;
use App\Models\Modelo_cliente;
use App\Models\Modelo_repartidor;
use App\Models\Modelo_existencia;
use CodeIgniter\Controller;

class P_pedidos extends Controller
{

    public function crea_p_pedido()
    {
        $m_pedido   = new Modelo_pedido();
        $m_producto = new Modelo_producto();

        $datos = [
            'pedidos'   => $m_pedido->findAll(),
            'productos' => $m_producto->findAll()
        ];

        return view('crea_p_pedido', $datos);
    }

    // ─────────────────────────────────────────────────────────────
    // GUARDAR PEDIDO COMPLETO
    // ─────────────────────────────────────────────────────────────
    public function guarda_pedido_completo()
    {
        $mPedido  = new Modelo_pedido();
        $mPP      = new Modelo_productopedidos();
        $mEstatus = new Modelo_estatus();

        $datosPedido = [
            'fecha'         => $this->request->getPost('fecha'),
            'id_cliente'    => $this->request->getPost('id_cliente'),
            'id_repartidor' => $this->request->getPost('id_repartidor'),
        ];

        if (in_array('', $datosPedido, true)) {
            return redirect()->back()->with('mensaje', 'Datos del pedido incompletos');
        }

        $mPedido->insert($datosPedido);
        $idPedido = $mPedido->insertID();

        $items = json_decode($this->request->getPost('items'), true);

        if (empty($items) || !is_array($items)) {
            return redirect()->back()->with('mensaje', 'El carrito está vacío');
        }

        // VALIDAR STOCK
        $mExistencia = new Modelo_existencia();
        $stockMap    = $mExistencia->stockDisponiblePorProducto();

        foreach ($items as $item) {

            $disponible = $stockMap[$item['id_producto']] ?? 0;

            if ($item['cant'] > $disponible) {

                $mPedido->delete($idPedido);

                $mProducto = new Modelo_producto();
                $prod      = $mProducto->find($item['id_producto']);

                $nombre = $prod['nombre'] ?? 'Producto';

                return redirect()->back()->with(
                    'error',
                    "Stock insuficiente para \"$nombre\". Disponible: $disponible"
                );
            }
        }

        // INSERTAR ITEMS
        foreach ($items as $item) {

            $datosItem = [
                'cant'         => $item['cant'],
                'precio_venta' => $item['p_venta'],
                'unidad_venta' => $item['u_venta'],
                'total'        => $item['total'],
                'id_pedido'    => $idPedido,
                'id_producto'  => $item['id_producto'],
            ];

            $mPP->insert($datosItem);
        }

        // INSERTAR ESTATUS
        $mEstatus->insert([
            'estado'    => $this->request->getPost('estado'),
            'fecha'     => $this->request->getPost('fecha_estatus'),
            'id_pedido' => $idPedido,
        ]);

        return redirect()->to('lista_p_pedido')
            ->with('mensaje', 'Pedido creado exitosamente');
    }

    // ─────────────────────────────────────────────────────────────
    // MODIFICAR PEDIDO COMPLETO
    // ─────────────────────────────────────────────────────────────
    public function modifica_pedido_completo()
    {

        $mPedido  = new Modelo_pedido();
        $mPP      = new Modelo_productopedidos();
        $mEstatus = new Modelo_estatus();

        $idPedido    = $this->request->getPost('id_pedido');
        $nuevoEstado = $this->request->getPost('estado');

        $estatusActual = $mEstatus
            ->where('id_pedido', $idPedido)
            ->orderBy('fecha', 'DESC')
            ->first();

        $estadoActual = $estatusActual['estado'] ?? 'pedido_pendiente';

        $transicionesValidas = [
            'pedido_pendiente'   => ['pedido_realizado'],
            'pedido_realizado'   => ['pedido_confirmado', 'pedido_cancelado'],
            'pedido_confirmado'  => ['pedido_en_transito', 'pedido_cancelado'],
            'pedido_en_transito' => ['pedido_entregado', 'pedido_a_credito'],
            'pedido_entregado'   => ['pedido_pagado', 'pedido_a_credito'],
            'pedido_a_credito'   => ['pedido_pagado'],
            'pedido_pagado'      => [],
            'pedido_cancelado'   => [],
        ];

        $permitidos = $transicionesValidas[$estadoActual] ?? [];

        if (!in_array($nuevoEstado, $permitidos)) {

            return redirect()->to('lista_p_pedido')
                ->with(
                    'error',
                    "No se puede cambiar a '$nuevoEstado'"
                );
        }

        // ACTUALIZAR PEDIDO
        $mPedido->update($idPedido, [
            'fecha'         => $this->request->getPost('fecha'),
            'id_cliente'    => $this->request->getPost('id_cliente'),
            'id_repartidor' => $this->request->getPost('id_repartidor'),
        ]);

        // ITEMS
        $items = json_decode($this->request->getPost('items'), true);

        if (!is_array($items)) {
            $items = [];
        }

        $idsEntrantes = array_filter(
            array_column($items, 'id'),
            fn($id) => !empty($id)
        );

        $existentes = $mPP->where('id_pedido', $idPedido)->findAll();

        foreach ($existentes as $fila) {

            if (!in_array($fila['id'], $idsEntrantes)) {
                $mPP->delete($fila['id']);
            }
        }

        foreach ($items as $item) {

            $datos = [
                'cant'         => $item['cant'],
                'precio_venta' => $item['p_venta'],
                'unidad_venta' => $item['u_venta'],
                'total'        => $item['total'],
                'id_pedido'    => $idPedido,
                'id_producto'  => $item['id_producto'],
            ];

            if (!empty($item['id'])) {

                $mPP->update($item['id'], $datos);

            } else {

                $mPP->insert($datos);
            }
        }

        // NUEVO ESTATUS
        $mEstatus->insert([
            'estado'    => $nuevoEstado,
            'fecha'     => $this->request->getPost('fecha_estatus'),
            'id_pedido' => $idPedido,
        ]);

        return redirect()->to('lista_p_pedido')
            ->with('mensaje', 'Pedido modificado exitosamente');
    }

    // ─────────────────────────────────────────────────────────────
    // API PEDIDO
    // ─────────────────────────────────────────────────────────────
    public function api_pedido($id = null)
    {

        $mPedido  = new Modelo_pedido();
        $mPP      = new Modelo_productopedidos();
        $mEstatus = new Modelo_estatus();

        $pedido  = $mPedido->find($id);
        $items   = $mPP->where('id_pedido', $id)->findAll();
        $estatus = $mEstatus->where('id_pedido', $id)
            ->orderBy('fecha', 'DESC')
            ->first();

        return $this->response->setJSON([
            'pedido'  => $pedido,
            'items'   => $items,
            'estatus' => $estatus,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // LISTA
    // ─────────────────────────────────────────────────────────────
public function lista_p_pedido()
{
    $buscar   = $this->request->getGet('buscar') ?? '';
    $pagina   = (int)($this->request->getGet('page') ?? 1);
    $porPagina = 20;

    $m_p_pedido   = new Modelo_productopedidos();
    $m_producto   = new Modelo_producto();
    $m_cliente    = new Modelo_cliente();
    $m_repartidor = new Modelo_repartidor();
    $m_existencia = new Modelo_existencia();

    $todos  = $m_p_pedido->obtenerPedidosAgrupados($buscar);
    $total  = count($todos);
    $offset = ($pagina - 1) * $porPagina;
    $pagina_actual = array_slice($todos, $offset, $porPagina);

    $datos = [
        'buscar'                    => $buscar,
        'pedidos_agrupados'         => $pagina_actual,
        'total_pedidos'             => $total,
        'pagina_actual'             => $pagina,
        'por_pagina'                => $porPagina,
        'total_paginas'             => ceil($total / $porPagina),
        'productos'                 => $m_producto->findAll(),
        'clientes'                  => $m_cliente->findAll(),
        'repartidores'              => $m_repartidor->findAll(),
        'stockPorProducto'          => $m_existencia->stockDisponiblePorProducto(),
        'precioSugeridoPorProducto' => [],
    ];

    return view('lista_p_pedido', $datos);
}

    // ─────────────────────────────────────────────────────────────
    // RECUPERAR
    // ─────────────────────────────────────────────────────────────
    public function recupera($id = null)
    {

        $m_p_pedido = new Modelo_productopedidos();
        $m_pedido   = new Modelo_pedido();
        $m_producto = new Modelo_producto();

        $datos = [
            'p_pedidos' => $m_p_pedido->find($id),
            'pedidos'   => $m_pedido->findAll(),
            'productos' => $m_producto->findAll()
        ];

        return view('modifica_p_pedido', $datos);
    }

    // ─────────────────────────────────────────────────────────────
    // ELIMINAR ITEM
    // ─────────────────────────────────────────────────────────────
    public function eliminar_datos($id = null)
    {

        $m_p_pedido = new Modelo_productopedidos();

        $m_p_pedido->delete($id);

        return redirect()->to('lista_p_pedido')
            ->with('mensaje', 'Registro eliminado');
    }

    // ─────────────────────────────────────────────────────────────
    // BORRAR PEDIDO COMPLETO
    // ─────────────────────────────────────────────────────────────
    public function borra_pedido_completo($id = null)
    {

        $mPedido  = new Modelo_pedido();
        $mPP      = new Modelo_productopedidos();
        $mEstatus = new Modelo_estatus();

        $mEstatus->where('id_pedido', $id)->delete();
        $mPP->where('id_pedido', $id)->delete();
        $mPedido->delete($id);

        return redirect()->to('lista_p_pedido')
            ->with('mensaje', 'Pedido eliminado correctamente');
    }

    // ─────────────────────────────────────────────────────────────
    // MODIFICAR ITEM SIMPLE
    // ─────────────────────────────────────────────────────────────
    public function modifica()
    {

        $m_p_pedido = new Modelo_productopedidos();

        $datos = [
            'cant'         => $this->request->getPost('cant'),
            'precio_venta' => $this->request->getPost('p_venta'),
            'unidad_venta' => $this->request->getPost('u_venta'),
            'total'        => $this->request->getPost('tot'),
            'id_pedido'    => $this->request->getPost('id_pedido'),
            'id_producto'  => $this->request->getPost('id_producto')
        ];

        $id = $this->request->getPost('id');

        $m_p_pedido->update($id, $datos);

        return redirect()->to('lista_p_pedido')
            ->with('mensaje', 'Registro modificado');
    }

    // ─────────────────────────────────────────────────────────────
    // GUARDAR ITEMS VIEJO
    // ─────────────────────────────────────────────────────────────
    public function guarda_p_pedido()
    {

        $m_p_pedido = new Modelo_productopedidos();

        $items = json_decode($this->request->getPost('items'), true);

        if (empty($items)) {

            return redirect()->to('lista_p_pedido')
                ->with('mensaje', 'El carrito está vacío');
        }

        foreach ($items as $item) {

            $datos = [
                'cant'         => $item['cant'],
                'precio_venta' => $item['p_venta'],
                'unidad_venta' => $item['u_venta'],
                'total'        => $item['total'],
                'id_pedido'    => $item['id_pedido'],
                'id_producto'  => $item['id_producto'],
            ];

            $m_p_pedido->insert($datos);
        }

        return redirect()->to('lista_p_pedido')
            ->with('mensaje', 'Registro guardado');
    }
}