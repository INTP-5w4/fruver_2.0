<?php 
namespace App\Controllers;

use App\Models\Modelo_pedido;
use App\Models\Modelo_producto;
use App\Models\Modelo_productopedidos;
use App\Models\Modelo_estatus;
use App\Models\Modelo_cliente;
use App\Models\Modelo_repartidor;
use CodeIgniter\Controller;

class P_pedidos extends Controller {

    public function crea_p_pedido() {
        $m_pedido   = new Modelo_pedido();
        $m_producto = new Modelo_producto();
        $datos = [
            'pedidos'   => $m_pedido->findAll(),
            'productos' => $m_producto->findAll()
        ];
        return view('crea_p_pedido', $datos);
    }

    // ── Guardado COMPLETO (pedido + carrito + estatus) ─────────────
    public function guarda_pedido_completo() {
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

        foreach ($items as $item) {
            $datosItem = [
                'cant'         => $item['cant'],
                'precio_venta' => $item['p_venta'],
                'unidad_venta' => $item['u_venta'],
                'total'        => $item['total'],
                'id_pedido'    => $idPedido,
                'id_producto'  => $item['id_producto'],
            ];

            if (empty($datosItem['cant']) || empty($datosItem['precio_venta'])
                || empty($datosItem['unidad_venta']) || empty($datosItem['id_producto'])) {
                return redirect()->back()->with('mensaje', 'Un ítem del carrito tiene datos incompletos');
            }

            $mPP->insert($datosItem);
        }

        $mEstatus->insert([
            'estado'    => $this->request->getPost('estado'),
            'fecha'     => $this->request->getPost('fecha_estatus'),
            'id_pedido' => $idPedido,
        ]);

        $origen = $this->request->getPost('origen');
        if ($origen === 'lista_p_pedido') {
            return redirect()->to('lista_p_pedido')->with('mensaje', 'Pedido creado exitosamente');
        }
        return redirect()->to('/')->with('mensaje', 'Pedido creado exitosamente');
    }

    // ── Modificación COMPLETA (pedido + carrito + nuevo estatus) ───
    public function modifica_pedido_completo() {
        $mPedido  = new Modelo_pedido();
        $mPP      = new Modelo_productopedidos();
        $mEstatus = new Modelo_estatus();

        $idPedido = $this->request->getPost('id_pedido');

        // 1. Actualizar pedido
        $mPedido->update($idPedido, [
            'fecha'         => $this->request->getPost('fecha'),
            'id_cliente'    => $this->request->getPost('id_cliente'),
            'id_repartidor' => $this->request->getPost('id_repartidor'),
        ]);

        // 2. Reemplazar ítems del carrito
        $mPP->where('id_pedido', $idPedido)->delete();
        $items = json_decode($this->request->getPost('items'), true);

        if (!empty($items) && is_array($items)) {
            foreach ($items as $item) {
                $mPP->insert([
                    'cant'         => $item['cant'],
                    'precio_venta' => $item['p_venta'],
                    'unidad_venta' => $item['u_venta'],
                    'total'        => $item['total'],
                    'id_pedido'    => $idPedido,
                    'id_producto'  => $item['id_producto'],
                ]);
            }
        }

        // 3. Insertar nuevo registro de estatus
        $mEstatus->insert([
            'estado'    => $this->request->getPost('estado'),
            'fecha'     => $this->request->getPost('fecha_estatus'),
            'id_pedido' => $idPedido,
        ]);

        $origen = $this->request->getPost('origen');
        if ($origen === 'lista_p_pedido') {
            return redirect()->to('lista_p_pedido')->with('mensaje', 'Pedido modificado exitosamente');
        }
        return redirect()->to('/')->with('mensaje', 'Pedido modificado exitosamente');
    }

    // ── API para cargar datos de un pedido en el wizard de edición ─
    public function api_pedido($id = null) {
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

    public function lista_p_pedido() {
        $m_p_pedido   = new Modelo_productopedidos();
        $m_producto   = new Modelo_producto();
        $m_pedido     = new Modelo_pedido();
        $m_cliente    = new Modelo_cliente();
        $m_repartidor = new Modelo_repartidor();

        $datos = [
            'productos'                 => $m_producto->findAll(),
            'p_pedidos'                 => $m_p_pedido->obtenerInformacionCompleta(),
            'pedidos'                   => $m_pedido->findAll(),
            'clientes'                  => $m_cliente->findAll(),
            'repartidores'              => $m_repartidor->findAll(),
            'precioSugeridoPorProducto' => [],
        ];
        return view('lista_p_pedido', $datos);
    }

    public function eliminar_datos($id = null) {
        $m_p_pedido = new Modelo_productopedidos();
        $m_p_pedido->delete($id);
        return redirect()->to('lista_p_pedido')->with('mensaje', 'Registro eliminado');
    }

    // ── guarda_p_pedido se mantiene para compatibilidad con rutas viejas ──
    public function guarda_p_pedido() {
        $m_p_pedido = new Modelo_productopedidos();
        $items      = json_decode($this->request->getPost('items'), true);

        if (empty($items) || !is_array($items)) {
            return redirect()->to('lista_p_pedido')->with('mensaje', 'El carrito está vacío');
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

            if (empty($datos['cant']) || empty($datos['precio_venta'])
                || empty($datos['unidad_venta']) || empty($datos['id_pedido'])
                || empty($datos['id_producto'])) {
                return redirect()->to('lista_p_pedido')->with('mensaje', 'Todos los campos son obligatorios');
            }

            $m_p_pedido->insert($datos);
        }

        return redirect()->to('lista_p_pedido')->with('mensaje', 'Registro guardado');
    }
}