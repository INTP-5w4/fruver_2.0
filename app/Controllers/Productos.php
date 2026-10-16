<?php

namespace App\Controllers;

use App\Models\Modelo_cliente;
use App\Models\Modelo_entrada;
use App\Models\Modelo_existencia;
use App\Models\Modelo_merma;
use App\Models\Modelo_pedido;
use App\Models\Modelo_producto;
use App\Models\Modelo_repartidor;
use App\Models\PdoModelo;
use Throwable;

class Productos extends BaseController
{
    /** Reglas de los campos de texto (crear y modificar). */
    private const REGLAS_TEXTO = [
        'nom'  => 'required|max_length[200]',
        'desc' => 'required|max_length[300]',
        'cat'  => 'required|in_list[frutas,verduras,hierbas]',
    ];

    /** Reglas de la imagen: tipo real (MIME), extensión y tamaño máximo (1 MB). */
    private const REGLAS_IMAGEN = 'is_image[img]|mime_in[img,image/jpeg,image/png]|ext_in[img,jpg,jpeg,png]|max_size[img,1024]';

    private const MENSAJES = [
        'nom' => [
            'required'   => 'El nombre es obligatorio.',
            'max_length' => 'El nombre admite máximo 200 caracteres.',
        ],
        'desc' => [
            'required'   => 'La descripción es obligatoria.',
            'max_length' => 'La descripción admite máximo 300 caracteres.',
        ],
        'cat' => [
            'required' => 'La categoría es obligatoria.',
            'in_list'  => 'Elige una categoría válida: frutas, verduras o hierbas.',
        ],
    ];

    // ─────────────────────────────────────────────────────────────
    // DASHBOARD (solo personal). Los demás van a la landing de la tienda.
    // ─────────────────────────────────────────────────────────────
    public function main_page()
    {
        if (auth()->loggedIn() && auth()->user()->inGroup('repartidor')) {
            return redirect()->to('repartidor/entregas');
        }

        if (! $this->esPersonal()) {
            return redirect()->to('main_page_4');
        }

        $m_producto   = new Modelo_producto();
        $m_cliente    = new Modelo_cliente();
        $m_repartidor = new Modelo_repartidor();
        $m_pedido     = new Modelo_pedido();
        $m_entrada    = new Modelo_entrada();
        $m_existencia = new Modelo_existencia();
        $m_merma      = new Modelo_merma();

        // Última entrada de cada producto: autocompleta precio y unidad de venta
        $uVentaSugerida = [];
        $precioSugerido = [];
        foreach ($m_entrada->ultimaEntradaPorProducto() as $id => $datos_ent) {
            $uVentaSugerida[$id] = $datos_ent['u_venta'];
            $precioSugerido[$id] = $datos_ent['precio_venta_u'];
        }

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
            'uVentaSugeridaPorProducto' => $uVentaSugerida,
            'precioSugeridoPorProducto' => $precioSugerido,
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

    public function guarda_producto()
    {
        $reglas        = self::REGLAS_TEXTO;
        $reglas['img'] = [
            'label' => 'Archivo de imagen',
            'rules' => 'uploaded[img]|' . self::REGLAS_IMAGEN,
        ];

        // Se valida TODO antes de guardar el archivo: así no quedan imágenes huérfanas.
        if (! $this->validate($reglas, self::MENSAJES)) {
            return $this->volverConErrores('error');
        }

        $file       = $this->request->getFile('img');
        $nombre_img = $file->getRandomName(); // nombre aleatorio: nunca se usa el del cliente
        $file->move(ROOTPATH . 'public/uploads', $nombre_img);

        try {
            (new Modelo_producto())->insert([
                'nombre'      => $this->post('nom'),
                'descripcion' => $this->post('desc'),
                'categoria'   => $this->post('cat'),
                'img'         => $nombre_img,
            ]);
        } catch (Throwable $e) {
            $this->borrarImagen($nombre_img);

            // El trigger no_duplica_producto avisa con un "Error: ..." legible.
            return redirect()->back()->withInput()
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo registrar el producto.'));
        }

        return redirect()->to($this->destino('lista_producto'))->with('mensaje', 'Producto registrado');
    }

    // ─────────────────────────────────────────────────────────────
    // LISTA PRODUCTOS
    // ─────────────────────────────────────────────────────────────
    public function lista_producto()
    {
        $buscar     = $this->textoBusqueda();
        $m_producto = new Modelo_producto();

        $datos = [
            'productos' => $m_producto->listar($buscar),
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
        $id = $this->request->getPost('id');

        if (! ctype_digit((string) $id)) {
            return redirect()->to('lista_producto')->with('error', 'ID inválido');
        }

        $m_producto = new Modelo_producto();
        $producto   = $m_producto->find($id);

        if (! $producto) {
            return redirect()->to('lista_producto')->with('error', 'Producto no encontrado');
        }

        $reglas = self::REGLAS_TEXTO;

        $file        = $this->request->getFile('img');
        $hayImagenNueva = $file !== null && $file->isValid() && ! $file->hasMoved();

        if ($hayImagenNueva) {
            $reglas['img'] = ['label' => 'Archivo de imagen', 'rules' => self::REGLAS_IMAGEN];
        }

        if (! $this->validate($reglas, self::MENSAJES)) {
            return redirect()->to('lista_producto')->with('error', $this->primerError());
        }

        $nombre_img = (string) $producto['img'];

        if ($hayImagenNueva) {
            $nombre_img = $file->getRandomName();
            $file->move(ROOTPATH . 'public/uploads', $nombre_img);
        }

        try {
            $m_producto->update($id, [
                'nombre'      => $this->post('nom'),
                'descripcion' => $this->post('desc'),
                'categoria'   => $this->post('cat'),
                'img'         => $nombre_img,
            ]);
        } catch (Throwable $e) {
            if ($hayImagenNueva) {
                $this->borrarImagen($nombre_img);
            }

            return redirect()->to('lista_producto')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo actualizar el producto.'));
        }

        // La imagen anterior se borra solo cuando el UPDATE ya salió bien.
        if ($hayImagenNueva && $producto['img'] !== '') {
            $this->borrarImagen((string) $producto['img']);
        }

        return redirect()->to('lista_producto')->with('mensaje', 'Producto actualizado correctamente');
    }

    // ─────────────────────────────────────────────────────────────
    // RECUPERAR PRODUCTO
    // ─────────────────────────────────────────────────────────────
    public function recupera($id = null)
    {
        $producto = (new Modelo_producto())->find($id);

        if (! $producto) {
            return redirect()->to('/lista_producto');
        }

        return view('modifica_producto', ['productos' => $producto]);
    }

    // ─────────────────────────────────────────────────────────────
    // ELIMINAR PRODUCTO (POST)
    // ─────────────────────────────────────────────────────────────
    public function eliminar_datos($id = null)
    {
        $m_producto = new Modelo_producto();
        $producto   = $m_producto->find($id);

        if (! $producto) {
            return redirect()->to('/lista_producto');
        }

        try {
            $m_producto->delete($id);
        } catch (Throwable $e) {
            $mensaje = PdoModelo::esErrorForaneo($e)
                ? 'No se puede eliminar este producto porque tiene registros relacionados.'
                : PdoModelo::mensajeSeguro($e, 'No se pudo eliminar el producto.');

            return redirect()->to('lista_producto')->with('error', $mensaje);
        }

        if ((string) $producto['img'] !== '') {
            $this->borrarImagen((string) $producto['img']);
        }

        return redirect()->to('lista_producto')->with('mensaje', 'Producto eliminado correctamente.');
    }

    /** Borra un archivo de public/uploads. basename() impide salirse de esa carpeta. */
    private function borrarImagen(string $nombre): void
    {
        $ruta = ROOTPATH . 'public/uploads/' . basename($nombre);

        if ($nombre !== '' && is_file($ruta)) {
            @unlink($ruta);
        }
    }
}
