<?php

namespace App\Controllers;

use App\Models\Modelo_cliente;
use App\Models\Modelo_entrada;
use App\Models\Modelo_estatus;
use App\Models\Modelo_existencia;
use App\Models\Modelo_pedido;
use App\Models\Modelo_producto;
use App\Models\Modelo_productopedidos;
use App\Models\Modelo_repartidor;
use App\Models\PdoModelo;
use DomainException;
use Throwable;

/**
 * Pedidos completos (cabecera + productos + estatus) del panel de administración.
 * Todo el SQL vive en Modelo_pedido / Modelo_productopedidos / Modelo_estatus (PDO).
 */
class P_pedidos extends BaseController
{
    /** Reglas de la cabecera del pedido. */
    private const REGLAS_CABECERA = [
        'fecha'         => 'required|valid_date[Y-m-d]',
        'id_cliente'    => 'required|is_natural_no_zero',
        'id_repartidor' => 'required|is_natural_no_zero',
    ];

    private function cabecera(): array
    {
        return [
            'fecha'         => $this->post('fecha'),
            'id_cliente'    => (int) $this->request->getPost('id_cliente'),
            'id_repartidor' => (int) $this->request->getPost('id_repartidor'),
        ];
    }

    /** Estado enviado por el formulario, solo si es uno de los estados reales del pedido. */
    private function estadoValido(): ?string
    {
        $estado = $this->post('estado');

        return array_key_exists($estado, Modelo_pedido::TRANSICIONES) ? $estado : null;
    }

    /** Líneas del pedido que llegan como JSON en el campo "items". */
    private function itemsDelFormulario(): array
    {
        $json = json_decode((string) $this->request->getPost('items'), true);

        return Modelo_productopedidos::limpiarItems($json);
    }

    public function crea_p_pedido()
    {
        return view('crea_p_pedido', [
            'pedidos'   => (new Modelo_pedido())->findAll(),
            'productos' => (new Modelo_producto())->findAll(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GUARDAR PEDIDO COMPLETO
    // ─────────────────────────────────────────────────────────────
    public function guarda_pedido_completo()
    {
        $estado = $this->estadoValido();

        if (! $this->validate(self::REGLAS_CABECERA) || $estado === null) {
            return redirect()->back()->with('mensaje', 'Datos del pedido incompletos');
        }

        try {
            $items = $this->itemsDelFormulario();

            (new Modelo_pedido())->crearPedidoCompleto(
                $this->cabecera(),
                $items,
                $estado,
                $this->fechaHoraSql($this->request->getPost('fecha_estatus'))
            );
        } catch (DomainException $e) {
            // Carrito vacío, datos no válidos o stock insuficiente
            return redirect()->back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->back()->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo crear el pedido.'));
        }

        return redirect()->to('lista_p_pedido')->with('mensaje', 'Pedido creado exitosamente');
    }

    // ─────────────────────────────────────────────────────────────
    // MODIFICAR PEDIDO COMPLETO
    // ─────────────────────────────────────────────────────────────
    public function modifica_pedido_completo()
    {
        $idPedido    = $this->request->getPost('id_pedido');
        $nuevoEstado = $this->estadoValido();

        if (! ctype_digit((string) $idPedido) || $nuevoEstado === null || ! $this->validate(self::REGLAS_CABECERA)) {
            return redirect()->to('lista_p_pedido')->with('error', 'Datos del pedido incompletos');
        }

        $m_pedido = new Modelo_pedido();

        if (! $m_pedido->find($idPedido)) {
            return redirect()->to('lista_p_pedido')->with('error', 'Pedido no encontrado');
        }

        $permitidos = Modelo_pedido::TRANSICIONES[$m_pedido->estadoActual($idPedido)] ?? [];

        if (! in_array($nuevoEstado, $permitidos, true)) {
            return redirect()->to('lista_p_pedido')->with('error', "No se puede cambiar a '{$nuevoEstado}'");
        }

        try {
            $m_pedido->modificarPedidoCompleto(
                $idPedido,
                $this->cabecera(),
                $this->itemsDelFormulario(),
                $nuevoEstado,
                $this->fechaHoraSql($this->request->getPost('fecha_estatus'))
            );
        } catch (DomainException $e) {
            return redirect()->to('lista_p_pedido')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to('lista_p_pedido')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo modificar el pedido.'));
        }

        return redirect()->to('lista_p_pedido')->with('mensaje', 'Pedido modificado exitosamente');
    }

    // ─────────────────────────────────────────────────────────────
    // API PEDIDO (JSON para el modal de edición)
    // ─────────────────────────────────────────────────────────────
    public function api_pedido($id = null)
    {
        $m_pedido = new Modelo_pedido();
        $pedido   = $m_pedido->find($id);

        if (! $pedido) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Pedido no encontrado']);
        }

        $estatus      = (new Modelo_estatus())->ultimoPorFecha($id);
        $estadoActual = $estatus['estado'] ?? 'pedido_pendiente';

        return $this->response->setJSON([
            'pedido'               => $pedido,
            'items'                => (new Modelo_productopedidos())->itemsDePedido($id),
            'estatus'              => $estatus,
            'estado_actual'        => $estadoActual,
            'transiciones_validas' => Modelo_pedido::TRANSICIONES[$estadoActual] ?? [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // LISTA
    // ─────────────────────────────────────────────────────────────
    public function lista_p_pedido()
    {
        $buscar    = $this->textoBusqueda();
        $pagina    = max(1, (int) $this->request->getGet('page'));
        $porPagina = 20;

        $todos         = (new Modelo_productopedidos())->obtenerPedidosAgrupados($buscar);
        $total         = count($todos);
        $pagina_actual = array_slice($todos, ($pagina - 1) * $porPagina, $porPagina);

        // Precio y unidad sugeridos: los de la última entrada de cada producto
        $ultimasEntradas = (new Modelo_entrada())->ultimaEntradaPorProducto();

        return view('lista_p_pedido', [
            'buscar'                    => $buscar,
            'pedidos_agrupados'         => $pagina_actual,
            'total_pedidos'             => $total,
            'pagina_actual'             => $pagina,
            'por_pagina'                => $porPagina,
            'total_paginas'             => (int) ceil($total / $porPagina),
            'productos'                 => (new Modelo_producto())->findAll(),
            'clientes'                  => (new Modelo_cliente())->findAll(),
            'repartidores'              => (new Modelo_repartidor())->findAll(),
            'stockPorProducto'          => (new Modelo_existencia())->stockDisponiblePorProducto(),
            'precioSugeridoPorProducto' => array_map(fn ($e) => $e['precio_venta_u'], $ultimasEntradas),
            'uVentaSugeridaPorProducto' => array_map(fn ($e) => $e['u_venta'], $ultimasEntradas),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // RECUPERAR (una línea de pedido)
    // ─────────────────────────────────────────────────────────────
    public function recupera($id = null)
    {
        $linea = (new Modelo_productopedidos())->find($id);

        if (! $linea) {
            return redirect()->to('lista_p_pedido')->with('error', 'Registro no encontrado');
        }

        return view('modifica_p_pedido', [
            'p_pedidos' => $linea,
            'pedidos'   => (new Modelo_pedido())->findAll(),
            'productos' => (new Modelo_producto())->findAll(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // ELIMINAR UNA LÍNEA (POST)
    // ─────────────────────────────────────────────────────────────
    public function eliminar_datos($id = null)
    {
        try {
            (new Modelo_productopedidos())->delete($id);
        } catch (Throwable $e) {
            return redirect()->to('lista_p_pedido')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo eliminar el registro.'));
        }

        return redirect()->to('lista_p_pedido')->with('mensaje', 'Registro eliminado');
    }

    // ─────────────────────────────────────────────────────────────
    // BORRAR PEDIDO COMPLETO (POST)
    // ─────────────────────────────────────────────────────────────
    public function borra_pedido_completo($id = null)
    {
        try {
            (new Modelo_pedido())->eliminarPedidoCompleto($id);
        } catch (DomainException $e) {
            // Pedido ya en tránsito, entregado o pagado
            return redirect()->to('lista_p_pedido')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to('lista_p_pedido')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo eliminar el pedido.'));
        }

        return redirect()->to('lista_p_pedido')->with('mensaje', 'Pedido eliminado correctamente');
    }

    // ─────────────────────────────────────────────────────────────
    // MODIFICAR UNA LÍNEA SIMPLE
    // ─────────────────────────────────────────────────────────────
    public function modifica()
    {
        $reglas = [
            'id'          => 'required|is_natural_no_zero',
            'cant'        => 'required|decimal|greater_than[0]',
            'p_venta'     => 'required|decimal|greater_than_equal_to[0]',
            'id_pedido'   => 'required|is_natural_no_zero',
            'id_producto' => 'required|is_natural_no_zero',
        ];

        if (! $this->validate($reglas)) {
            return redirect()->to('lista_p_pedido')->with('error', $this->primerError());
        }

        try {
            $unidad = Modelo_productopedidos::normalizarUnidad($this->post('u_venta'));
            $cant   = round((float) $this->post('cant'), 2);
            $precio = round((float) $this->post('p_venta'), 2);

            (new Modelo_productopedidos())->update($this->post('id'), [
                'cant'         => $cant,
                'precio_venta' => $precio,
                'unidad_venta' => $unidad,
                'total'        => round($cant * $precio, 2), // el total se recalcula aquí
                'id_pedido'    => (int) $this->request->getPost('id_pedido'),
                'id_producto'  => (int) $this->request->getPost('id_producto'),
            ]);
        } catch (DomainException $e) {
            return redirect()->to('lista_p_pedido')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to('lista_p_pedido')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo modificar el registro.'));
        }

        return redirect()->to('lista_p_pedido')->with('mensaje', 'Registro modificado');
    }

    // ─────────────────────────────────────────────────────────────
    // GUARDAR LÍNEAS SUELTAS (formulario antiguo crea_p_pedido)
    // ─────────────────────────────────────────────────────────────
    public function guarda_p_pedido()
    {
        $m_p_pedido = new Modelo_productopedidos();

        try {
            $items = $this->itemsDelFormulario();

            // El formulario antiguo manda el id_pedido dentro de cada línea.
            $json = json_decode((string) $this->request->getPost('items'), true);

            $m_p_pedido->transaccion(function () use ($m_p_pedido, $items, $json) {
                foreach ($items as $i => $item) {
                    $idPedido = $json[$i]['id_pedido'] ?? null;

                    if (! is_numeric($idPedido) || (int) $idPedido < 1) {
                        throw new DomainException('Falta el pedido de una de las líneas.');
                    }

                    $m_p_pedido->insert([
                        'cant'         => $item['cant'],
                        'precio_venta' => $item['precio_venta'],
                        'unidad_venta' => $item['unidad_venta'],
                        'total'        => $item['total'],
                        'id_pedido'    => (int) $idPedido,
                        'id_producto'  => $item['id_producto'],
                    ]);
                }
            });
        } catch (DomainException $e) {
            return redirect()->to('lista_p_pedido')->with('mensaje', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to('lista_p_pedido')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudieron guardar los registros.'));
        }

        return redirect()->to('lista_p_pedido')->with('mensaje', 'Registro guardado');
    }
}
