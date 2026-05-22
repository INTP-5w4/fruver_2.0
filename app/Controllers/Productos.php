<?php

namespace App\Controllers;

use App\Models\Modelo_producto;
use App\Models\Modelo_entrada;
use App\Models\Modelo_pedido;
use App\Models\Modelo_repartidor;
use App\Models\Modelo_cliente;
use App\Models\Modelo_existencia;

class Productos extends BaseController
{

    // ─────────────────────────────────────────────────────────────
    // MAIN PAGE
    // ─────────────────────────────────────────────────────────────
    public function main_page()
    {

        $m_producto   = new Modelo_producto();
        $m_cliente    = new Modelo_cliente();
        $m_repartidor = new Modelo_repartidor();
        $m_pedido     = new Modelo_pedido();
        $m_entrada    = new Modelo_entrada();
        $m_existencia = new Modelo_existencia();
        $m_merma      = new \App\Models\Modelo_merma();

        $datos = [

            // Datos generales
            'productosLowStock' => $m_producto->productosLowStock(),
            'productos'         => $m_producto->findAll(),
            'clientes'          => $m_cliente->findAll(),
            'repartidores'      => $m_repartidor->findAll(),
            'pedidos'           => $m_pedido->findAll(),
            'entradas'          => $m_entrada->getEntradasConProducto(),

            // Datos gráficos
            'pedidosPorMes' => $m_pedido->pedidosPorMes(),
            'ventasPorMes'  => $m_pedido->totalVentasPorMes(),
            'topProductos'  => $m_producto->topProductos(),
            'perdidasMerma' => $m_merma->perdidasPorMes(),

            // Inventario
            'precioSugeridoPorProducto' => $m_entrada->precioMaximoPorProducto(),
            'stockPorProducto'          => $m_existencia->stockDisponiblePorProducto(),
        ];

        return view('main_page3', $datos);
    }

    // ─────────────────────────────────────────────────────────────
    // CREAR PRODUCTO
    // ─────────────────────────────────────────────────────────────
    public function crea_producto()
    {
        return view('crea_producto');
    }

    // ─────────────────────────────────────────────────────────────
    // GUARDAR PRODUCTO
    // ─────────────────────────────────────────────────────────────
    public function guarda_producto()
    {

        $m_producto = new Modelo_producto();

        $nombre_img = null;

        if ($this->request->getMethod() === 'POST') {

            $file = $this->request->getFile('img');

            $reglas_validacion = [
                'img' => [
                    'label' => 'Archivo de Imagen',
                    'rules' =>
                        'uploaded[img]' .
                        '|is_image[img]' .
                        '|mime_in[img,image/jpg,image/png,image/jpeg]' .
                        '|max_size[img,1024]',
                ]
            ];

            if (!$this->validate($reglas_validacion)) {

                return redirect()->back()
                    ->with('errors', $this->validator->getErrors());
            }

            $nombre_img = $file->getRandomName();

            $file->move(
                ROOTPATH . 'public/uploads',
                $nombre_img
            );
        }

        $datos = [
            'nombre'     => $this->request->getPost('nom'),
            'descripcion'=> $this->request->getPost('desc'),
            'categoria'  => $this->request->getPost('cat'),
            'img'        => $nombre_img,
        ];

        // VALIDAR CAMPOS
        if (
            empty($datos['nombre']) ||
            empty($datos['descripcion']) ||
            empty($datos['categoria']) ||
            empty($datos['img'])
        ) {

            return view('crea_producto');
        }

        try {

            $m_producto->insert($datos);

            if ($this->request->getPost('origen') === 'main_page') {

                return redirect()->to('/')
                    ->with('mensaje', 'Producto registrado');
            }

            return redirect()->to('lista_producto')
                ->with('mensaje', 'Producto registrado');

        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {

            $mensaje = $e->getMessage();

            if (str_contains($mensaje, 'Error:')) {

                $mensaje = substr(
                    $mensaje,
                    strpos($mensaje, 'Error:')
                );
            }

            return redirect()->to('lista_producto')
                ->with('error', $mensaje);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // LISTA PRODUCTOS
    // ─────────────────────────────────────────────────────────────
    public function lista_producto()
    {

        $buscar = $this->request->getGet('buscar') ?? '';

        $m_producto = new Modelo_producto();

        // BUSCADOR
        if (!empty($buscar)) {

            $productos = $m_producto
                ->aplicarBusqueda($buscar)
                ->orderBy('id', 'DESC')
                ->paginate(20, 'default');

        } else {

            $productos = $m_producto
                ->orderBy('id', 'DESC')
                ->paginate(20, 'default');
        }

        $datos = [
            'productos' => $productos,
            'pager'     => $m_producto->pager,
            'buscar'    => $buscar,
        ];

        return view('lista_producto', $datos);
    }

    // ─────────────────────────────────────────────────────────────
    // MODIFICAR PRODUCTO
    // ─────────────────────────────────────────────────────────────
    public function modifica()
    {

        $m_producto = new Modelo_producto();

        if ($this->request->getMethod() === 'POST') {

            $id = $this->request->getPost('id');

            $producto = $m_producto->find($id);

            if (!$producto) {

                return redirect()->to('lista_producto')
                    ->with('error', 'Producto no encontrado');
            }

            // Mantener imagen actual
            $nombre_img = $producto['img'];

            // Revisar si subieron nueva imagen
            $file = $this->request->getFile('img');

            if (
                $file &&
                $file->isValid() &&
                !$file->hasMoved()
            ) {

                $reglas_validacion = [
                    'img' => [
                        'label' => 'Archivo de Imagen',
                        'rules' =>
                            'is_image[img]' .
                            '|mime_in[img,image/jpg,image/png,image/jpeg]' .
                            '|max_size[img,1024]',
                    ]
                ];

                if (!$this->validate($reglas_validacion)) {

                    return redirect()->back()
                        ->with('errors', $this->validator->getErrors());
                }

                // Eliminar imagen anterior
                if (
                    !empty($producto['img']) &&
                    file_exists(
                        ROOTPATH .
                        'public/uploads/' .
                        $producto['img']
                    )
                ) {

                    unlink(
                        ROOTPATH .
                        'public/uploads/' .
                        $producto['img']
                    );
                }

                // Guardar nueva imagen
                $nombre_img = $file->getRandomName();

                $file->move(
                    ROOTPATH . 'public/uploads',
                    $nombre_img
                );
            }

            $datos_de_producto = [
                'nombre'      => $this->request->getPost('nom'),
                'descripcion' => $this->request->getPost('desc'),
                'categoria'   => $this->request->getPost('cat'),
                'img'         => $nombre_img,
            ];

            // ACTUALIZAR
            if ($m_producto->update($id, $datos_de_producto)) {

                return redirect()->to('lista_producto')
                    ->with('mensaje', 'Producto actualizado correctamente');
            }

            return redirect()->back()
                ->with('error', 'No se pudo actualizar el producto');
        }

        return redirect()->to('lista_producto');
    }

    // ─────────────────────────────────────────────────────────────
    // RECUPERAR PRODUCTO
    // ─────────────────────────────────────────────────────────────
    public function recupera($id = null)
    {

        $m_producto = new Modelo_producto();

        $datos['productos'] = $m_producto->find($id);

        if (!$datos['productos']) {

            return redirect()->to('/lista_producto');
        }

        return view('modifica_producto', $datos);
    }

    // ─────────────────────────────────────────────────────────────
    // ELIMINAR PRODUCTO
    // ─────────────────────────────────────────────────────────────
    public function eliminar_datos($id = null)
    {

        $m_producto = new Modelo_producto();

        if (!$m_producto->find($id)) {

            return redirect()->to('/lista_producto');
        }

        try {

            $m_producto->delete($id);

            return redirect()->to('lista_producto')
                ->with(
                    'mensaje',
                    'Producto eliminado correctamente.'
                );

        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {

            return redirect()->to('lista_producto')
                ->with(
                    'error',
                    'No se puede eliminar este producto porque tiene registros relacionados.'
                );
        }
    }
}