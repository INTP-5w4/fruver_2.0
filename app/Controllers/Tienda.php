<?php

namespace App\Controllers;

use App\Libraries\Carrito;
use App\Models\Modelo_cliente;
use App\Models\Modelo_pedido;
use App\Models\Modelo_producto;
use App\Models\PdoModelo;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Tienda en línea (landing main_page_4).
 *
 *  - index ........ pública: catálogo en tres filas (Frutas, Hierbas, Verduras).
 *  - carrito ...... JSON: estado actual del carrito (solo con sesión).
 *  - agregar / cambiar / quitar / vaciar ... JSON (POST + token CSRF, solo con sesión).
 *  - confirmar .... POST: convierte el carrito en un pedido.
 *
 * El controlador no escribe SQL: lee y escribe a través de los modelos (PDO).
 */
class Tienda extends BaseController
{
    private const TITULOS = [
        'frutas'   => 'Frutas',
        'hierbas'  => 'Hierbas',
        'verduras' => 'Verduras',
    ];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        helper('tienda');
    }

    // ─────────────────────────────────────────────────────────────
    // LANDING (pública)
    // ─────────────────────────────────────────────────────────────
    public function index()
    {
        $buscar  = $this->textoBusqueda('q');
        $usuario = auth()->user();
        $perfil  = $usuario !== null ? (new Modelo_cliente())->buscarPorUsuario((int) $usuario->id) : null;

        $this->cspEstricta();

        $datos = [
            'titulos'    => self::TITULOS,
            'filas'      => (new Modelo_producto())->catalogoAgrupado($buscar),
            'buscar'     => $buscar,
            'conSesion'  => $usuario !== null,
            'esPersonal' => $this->esPersonal(),
            'saludo'     => $perfil['nombre'] ?? ($usuario->username ?? ''),
            'tienePerfil' => $perfil !== null,
            'carrito'    => $usuario !== null ? $this->instantanea() : null,
        ];

        return view('main_page_4', $datos);
    }

    // ─────────────────────────────────────────────────────────────
    // CARRITO (JSON)
    // ─────────────────────────────────────────────────────────────
    public function carrito()
    {
        return $this->responder(true);
    }

    public function agregar()
    {
        $id       = (int) $this->request->getPost('id_producto');
        $cantidad = $this->request->getPost('cantidad') === null ? 1 : (int) $this->request->getPost('cantidad');

        if ($id < 1 || $cantidad < 1 || $cantidad > Carrito::MAX_CANTIDAD) {
            return $this->responder(false, 'Cantidad no válida.', 422);
        }

        $producto = (new Modelo_producto())->catalogoPorIds([$id])[$id] ?? null;

        if ($producto === null) {
            return $this->responder(false, 'Ese producto ya no está disponible.', 404);
        }

        $stock   = (int) floor((float) $producto['stock']);
        $carrito = new Carrito();
        $ya      = $carrito->cantidadDe($id);

        if ($stock < 1) {
            return $this->responder(false, '"' . $producto['nombre'] . '" está agotado.', 409);
        }

        if ($ya + $cantidad > $stock) {
            $msg = $ya > 0
                ? 'Solo hay ' . $stock . ' disponibles de "' . $producto['nombre'] . '" y ya tienes ' . $ya . ' en tu carrito.'
                : 'Solo hay ' . $stock . ' disponibles de "' . $producto['nombre'] . '".';

            return $this->responder(false, $msg, 409);
        }

        try {
            $carrito->agregar($id, $cantidad);
        } catch (DomainException $e) {
            return $this->responder(false, $e->getMessage(), 422);
        }

        return $this->responder(true, 'Agregaste "' . $producto['nombre'] . '" al carrito.');
    }

    public function cambiar()
    {
        $id       = (int) $this->request->getPost('id_producto');
        $cantidad = (int) $this->request->getPost('cantidad');

        if ($id < 1 || $cantidad < 0 || $cantidad > Carrito::MAX_CANTIDAD) {
            return $this->responder(false, 'Cantidad no válida.', 422);
        }

        $carrito = new Carrito();

        if ($cantidad > 0) {
            $producto = (new Modelo_producto())->catalogoPorIds([$id])[$id] ?? null;

            if ($producto === null) {
                $carrito->quitar($id);

                return $this->responder(false, 'Ese producto ya no está disponible.', 404);
            }

            $stock = (int) floor((float) $producto['stock']);

            if ($cantidad > $stock) {
                return $this->responder(false, 'Solo hay ' . $stock . ' disponibles de "' . $producto['nombre'] . '".', 409);
            }
        }

        try {
            $carrito->fijar($id, $cantidad);
        } catch (DomainException $e) {
            return $this->responder(false, $e->getMessage(), 422);
        }

        return $this->responder(true);
    }

    public function quitar()
    {
        (new Carrito())->quitar((int) $this->request->getPost('id_producto'));

        return $this->responder(true);
    }

    public function vaciar()
    {
        (new Carrito())->vaciar();

        return $this->responder(true);
    }

    // ─────────────────────────────────────────────────────────────
    // CONFIRMAR PEDIDO
    // ─────────────────────────────────────────────────────────────
    public function confirmar()
    {
        $usuario = auth()->user();
        $carrito = new Carrito();
        $lineas  = $carrito->lineas();

        if ($lineas === []) {
            return $this->volverATienda()->with('error', 'Tu carrito está vacío.');
        }

        $m_cliente = new Modelo_cliente();
        $perfil    = $m_cliente->buscarPorUsuario((int) $usuario->id);

        // Primer pedido: se piden los datos de contacto y se guardan como perfil de cliente.
        if ($perfil === null) {
            $reglas = [
                'nom'     => 'required|max_length[100]',
                'ape_pat' => 'required|max_length[100]',
                'ape_mat' => 'permit_empty|max_length[100]',
                'tel'     => 'required|numeric|min_length[7]|max_length[12]',
            ];
            $mensajes = [
                'nom'     => ['required' => 'Escribe tu nombre.', 'max_length' => 'El nombre admite máximo 100 caracteres.'],
                'ape_pat' => ['required' => 'Escribe tu apellido paterno.', 'max_length' => 'El apellido admite máximo 100 caracteres.'],
                'ape_mat' => ['max_length' => 'El apellido admite máximo 100 caracteres.'],
                'tel'     => [
                    'required'   => 'Escribe un teléfono de contacto.',
                    'numeric'    => 'El teléfono debe tener solo números.',
                    'min_length' => 'El teléfono debe tener al menos 7 dígitos.',
                    'max_length' => 'El teléfono admite máximo 12 dígitos.',
                ],
            ];

            if (! $this->validate($reglas, $mensajes)) {
                return $this->volverATienda()->withInput()->with('error', $this->primerError());
            }

            try {
                $idCliente = $m_cliente->crearParaUsuario(
                    (int) $usuario->id,
                    $this->post('nom'),
                    $this->post('ape_pat'),
                    $this->post('ape_mat'),
                    $this->post('tel')
                );
            } catch (Throwable $e) {
                // El trigger no_duplica_cliente avisa con un "Error: ..." legible.
                return $this->volverATienda()->withInput()
                    ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo guardar tu información de contacto.'));
            }

            $perfil = ['id' => $idCliente];
        }

        try {
            $pedido = (new Modelo_pedido())->crearPedidoDeCliente((int) $perfil['id'], $lineas);
        } catch (DomainException $e) {
            return $this->volverATienda()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->volverATienda()
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo registrar tu pedido. Intenta de nuevo.'));
        }

        $carrito->vaciar();

        return redirect()->to('main_page_4')->with('pedido_ok', $pedido);
    }

    // ─────────────────────────────────────────────────────────────
    // APOYO
    // ─────────────────────────────────────────────────────────────

    /** Vuelve a la landing con el panel del carrito abierto (para ver el error). */
    private function volverATienda()
    {
        return redirect()->to('main_page_4')->with('abrir_carrito', true);
    }

    /**
     * La landing no usa scripts ni estilos en línea, así que se le quita la excepción
     * "unsafe-inline" que el resto del sistema necesita (ver Config\ContentSecurityPolicy).
     * Los scripts en línea que sí lleven nonce ({csp-script-nonce}) siguen funcionando.
     */
    private function cspEstricta(): void
    {
        $csp = $this->response->getCSP();

        foreach (['script-src', 'script-src-elem', 'script-src-attr', 'style-src', 'style-src-elem', 'style-src-attr'] as $directiva) {
            $csp->clearDirective($directiva);
        }

        $csp->addScriptSrc('self');
        $csp->addStyleSrc(['self', 'https://fonts.googleapis.com']);
    }

    /** Respuesta JSON estándar: estado del carrito + mensaje + token CSRF vigente. */
    private function responder(bool $ok, string $mensaje = '', int $codigo = 200)
    {
        return $this->response
            ->setStatusCode($ok ? 200 : $codigo)
            ->setJSON([
                'ok'      => $ok,
                'mensaje' => $mensaje,
                'carrito' => $this->instantanea(),
                'csrf'    => csrf_hash(),
            ]);
    }

    /**
     * Carrito listo para mostrar. Precio, unidad y stock salen de la base de datos;
     * lo que ya no esté disponible se quita del carrito.
     *
     * @return array{cuenta:int,total:float,total_texto:string,hay_excedidos:bool,avisos:list<string>,lineas:list<array<string,mixed>>}
     */
    private function instantanea(): array
    {
        $carrito  = new Carrito();
        $lineas   = $carrito->lineas();
        $catalogo = $lineas === [] ? [] : (new Modelo_producto())->catalogoPorIds(array_keys($lineas));

        $items   = [];
        $total   = 0.0;
        $avisos  = [];
        $excedio = false;

        foreach ($lineas as $id => $cantidad) {
            $p = $catalogo[$id] ?? null;

            if ($p === null) {
                $carrito->quitar($id);
                $avisos[] = 'Quitamos del carrito un producto que ya no está disponible.';

                continue;
            }

            $stock    = (int) floor((float) $p['stock']);
            $precio   = (float) $p['precio'];
            $subtotal = round($cantidad * $precio, 2);
            $excede   = $cantidad > $stock;
            $total   += $subtotal;
            $excedio  = $excedio || $excede;

            $items[] = [
                'id'             => (int) $id,
                'nombre'         => (string) $p['nombre'],
                'img'            => tienda_img_url($p['img']),
                'unidad'         => tienda_unidad($p['unidad']),
                'precio'         => $precio,
                'precio_texto'   => tienda_dinero($precio),
                'cantidad'       => $cantidad,
                'subtotal_texto' => tienda_dinero($subtotal),
                'stock'          => $stock,
                'excede'         => $excede,
            ];
        }

        return [
            'cuenta'        => array_sum(array_column($items, 'cantidad')),
            'total'         => round($total, 2),
            'total_texto'   => tienda_dinero($total),
            'hay_excedidos' => $excedio,
            'avisos'        => array_values(array_unique($avisos)),
            'lineas'        => $items,
        ];
    }
}
