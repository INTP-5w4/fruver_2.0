<?php

namespace App\Controllers;

use App\Libraries\Carrito;
use App\Libraries\Correo;
use App\Libraries\Rutas\UbicadorDirecciones;
use App\Models\Modelo_cliente;
use App\Models\Modelo_consentimiento;
use App\Models\Modelo_direccion;
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

    /** Fila de recomendaciones personalizadas (finalidad voluntaria V4). */
    public const PARA_TI = 'para_ti';

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

        $titulos = self::TITULOS;
        $filas   = (new Modelo_producto())->catalogoAgrupado($buscar);

        // V4 (voluntaria): "Para ti" con lo que más pide, solo si la cuenta lo autorizó.
        if ($perfil !== null && $buscar === '' && $this->autorizaRecomendaciones((int) $usuario->id)) {
            $paraTi = (new Modelo_producto())->recomendadosParaCliente((int) $perfil['id']);

            if ($paraTi !== []) {
                $titulos = [self::PARA_TI => 'Para ti'] + $titulos;
                $filas[self::PARA_TI] = $paraTi;
            }
        }

        $datos = [
            'titulos'    => $titulos,
            'filas'      => $filas,
            'buscar'     => $buscar,
            'conSesion'  => $usuario !== null,
            'esPersonal' => $this->esPersonal(),
            'saludo'     => $perfil['nombre'] ?? ($usuario->username ?? ''),
            'tienePerfil' => $perfil !== null,
            'carrito'    => $usuario !== null ? $this->instantanea() : null,
            'pedidosAnteriores' => $perfil !== null
                ? (new Modelo_pedido())->recientesDeCliente((int) $perfil['id'])
                : [],
            // Rutas de reparto: a dónde llevar el pedido.
            'pideDireccion'     => rutas_listas(),
            'misDirecciones'    => $perfil !== null && rutas_listas()
                ? (new Modelo_direccion())->deCliente((int) $perfil['id'])
                : [],
            'estadosMx'         => Modelo_direccion::ESTADOS_MX,
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
                'nom'     => 'required|max_length[100]|sin_groserias',
                'ape_pat' => 'required|max_length[100]|sin_groserias',
                'ape_mat' => 'permit_empty|max_length[100]|sin_groserias',
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

        $idDireccion = null;
        if (rutas_listas()) {
            $idDireccion = $this->direccionDeEntrega((int) $perfil['id']);
            if (is_string($idDireccion)) {
                return $this->volverATienda()->withInput()->with('error', $idDireccion);
            }
        }

        try {
            $pedido = (new Modelo_pedido())->crearPedidoDeCliente((int) $perfil['id'], $lineas, $idDireccion);
        } catch (DomainException $e) {
            return $this->volverATienda()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->volverATienda()
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo registrar tu pedido. Intenta de nuevo.'));
        }

        $carrito->vaciar();
        $this->enviarComprobante((int) $pedido['id'], (string) $usuario->email);

        return redirect()->to('main_page_4')->with('pedido_ok', $pedido);
    }

    /** Comprobante del pedido al correo de la cuenta (solo si hay servidor de correo configurado). */
    private function enviarComprobante(int $idPedido, string $correo): void
    {
        if (! Correo::configurado()) {
            return;
        }

        $ticket = (new Modelo_pedido())->ticket($idPedido);

        if ($ticket !== null) {
            Correo::enviar($correo, 'FRUVER · Recibimos tu pedido #' . $idPedido, view('correos/pedido_confirmado', ['ticket' => $ticket]));
        }
    }
        // ─────────────────────────────────────────────────────────────
    // VOLVER A PEDIR (carga un pedido anterior en el carrito)
    // ─────────────────────────────────────────────────────────────
    public function recargar($idPedido = null)
    {
        $usuario = auth()->user();
        $perfil  = (new Modelo_cliente())->buscarPorUsuario((int) $usuario->id);

        if ($perfil === null) {
            return $this->volverATienda()->with('error', 'Todavía no tienes pedidos anteriores.');
        }

        try {
            // Se pasa el id del cliente: solo puede recargar SUS pedidos
            $datos = (new Modelo_pedido())->prepararRecarga((int) $idPedido, (int) $perfil['id']);
        } catch (DomainException $e) {
            return $this->volverATienda()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->volverATienda()->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo cargar el pedido.'));
        }

        $carrito = new Carrito();
        $avisos  = $datos['avisos'];

        foreach ($datos['items'] as $item) {
            $cantidad = (int) floor((float) $item['cant']);

            if ($cantidad < 1) {
                $avisos[] = 'Un producto no se incluyó porque su cantidad es menor a 1.';
                continue;
            }

            try {
                $carrito->agregar((int) $item['id_producto'], $cantidad);
            } catch (DomainException $e) {
                $avisos[] = $e->getMessage();
            }
        }

        return $this->volverATienda()->with('aviso_recarga', array_values(array_unique($avisos)));
    }

    // ─────────────────────────────────────────────────────────────
    // APOYO
    // ─────────────────────────────────────────────────────────────

    /** Vuelve a la landing con el panel del carrito abierto (para ver el error). */
    /**
     * Dirección de entrega del pedido de la tienda:
     *  - una de las direcciones del propio cliente (validado en el servidor), o
     *  - una nueva capturada en el formulario (se guarda y se ubica en el mapa).
     *
     * @return int|string id de la dirección, o el mensaje de error para el cliente
     */
    private function direccionDeEntrega(int $idCliente): int|string
    {
        $m      = new Modelo_direccion();
        $mias   = $m->deCliente($idCliente);
        $elegida = $this->post('id_direccion');

        if ($elegida !== 'nueva' && $mias !== []) {
            $id = $m->resolverParaPedido($idCliente, ctype_digit($elegida) ? (int) $elegida : null);

            return $id ?? 'Elige una de tus direcciones de entrega.';
        }

        if (count($mias) >= Modelo_direccion::MAX_POR_CLIENTE_TIENDA) {
            return 'Ya tienes muchas direcciones registradas; elige una de ellas o pide ayuda al personal.';
        }

        $reglas = [
            'dir_calle' => 'required|max_length[200]|sin_groserias',
            'dir_num'   => 'required|max_length[50]|sin_groserias',
            'dir_col'   => 'required|max_length[200]|sin_groserias',
            'dir_mun'   => 'required|max_length[200]|sin_groserias',
            'dir_edo'   => 'required|in_list[' . implode(',', Modelo_direccion::ESTADOS_MX) . ']',
            'dir_cp'    => 'permit_empty|alpha_numeric|max_length[10]',
            'dir_ref'   => 'permit_empty|max_length[300]|sin_groserias',
        ];
        $mensajes = [
            'dir_calle' => ['required' => 'Escribe la calle de entrega.'],
            'dir_num'   => ['required' => 'Escribe el número exterior (o "S/N").'],
            'dir_col'   => ['required' => 'Escribe la colonia.'],
            'dir_mun'   => ['required' => 'Escribe el municipio.'],
            'dir_edo'   => ['required' => 'Elige el estado.', 'in_list' => 'Elige un estado de la lista.'],
            'dir_cp'    => ['alpha_numeric' => 'El código postal solo admite números.'],
        ];

        if (! $this->validate($reglas, $mensajes)) {
            return $this->primerError();
        }

        $datos = [
            'calle'         => $this->post('dir_calle'),
            'numero'        => $this->post('dir_num'),
            'colonia'       => $this->post('dir_col'),
            'municipio'     => $this->post('dir_mun'),
            'estado'        => $this->post('dir_edo'),
            'codigo_postal' => $this->post('dir_cp') !== '' ? $this->post('dir_cp') : null,
            'referencias'   => $this->post('dir_ref') !== '' ? $this->post('dir_ref') : null,
            'id_cliente'    => $idCliente,
        ];

        try {
            $id = $m->insert($datos);
        } catch (Throwable $e) {
            return PdoModelo::mensajeSeguro($e, 'No se pudo guardar tu dirección.');
        }

        // Se ubica en el mapa con Google (si está configurado); si falla, el personal lo verá.
        UbicadorDirecciones::crear($m)->ubicar($id, $datos, null, true);
        if ($mias === [] || $this->request->getPost('dir_principal') === '1') {
            $m->marcarPrincipal($id);
        }

        return $id;
    }

    private function autorizaRecomendaciones(int $idUsuario): bool
    {
        try {
            $modelo = new Modelo_consentimiento();

            return $modelo->tablaExiste() && $modelo->autoriza($idUsuario, 'v4');
        } catch (Throwable $e) {
            log_message('error', 'Tienda: no se pudo leer el consentimiento V4: ' . $e->getMessage());

            return false; // ante la duda, sin personalización
        }
    }

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

        $csp->addScriptSrc(['self', 'nonce-' . $csp->getScriptNonce()]);
        $csp->addStyleSrc(['self', 'https://fonts.googleapis.com', 'nonce-' . $csp->getStyleNonce()]);
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
