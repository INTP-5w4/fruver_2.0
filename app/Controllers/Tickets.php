<?php

namespace App\Controllers;

use App\Libraries\Termica\ImpresoraException;
use App\Libraries\Termica\ServicioTicket;
use App\Models\Modelo_pedido;
use App\Models\PdoModelo;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Tickets del pedido.
 *
 *  - ver()           Ticket (resumen) para enviar al cliente por WhatsApp.
 *  - vista()         Ticket imprimible de 58/80 mm (alternativa universal por navegador).
 *  - imprimir()      Imprime directo en la impresora térmica (ESC/POS) y responde JSON.
 *  - configuracion() Diagnóstico y pruebas de la impresora.
 *  - prueba()/conexion()  Hoja de prueba y verificación de conexión (JSON).
 *
 * Solo personal (la ruta ya exige el grupo; aquí se repite la verificación).
 * El filtro CSRF es global: el token viaja en la cabecera X-CSRF-TOKEN (ver public/js/impresion_ticket.js).
 */
class Tickets extends BaseController
{
    // ─────────────────────────────────────────────────────────────
    // WHATSAPP
    // ─────────────────────────────────────────────────────────────
    public function ver($id = null)
    {
        if (! $this->esPersonal()) {
            return redirect()->to(site_url('/'))->with('error', 'No tienes permiso para ver tickets.');
        }

        if (! ctype_digit((string) $id) || (int) $id < 1) {
            return redirect()->to('lista_p_pedido')->with('error', 'Pedido no válido.');
        }

        helper('whatsapp');

        try {
            $ticket = (new Modelo_pedido())->ticket((int) $id);
        } catch (Throwable $e) {
            return redirect()->to('lista_p_pedido')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo cargar el ticket del pedido.'));
        }

        if ($ticket === null) {
            return redirect()->to('lista_p_pedido')->with('error', 'Pedido no encontrado.');
        }

        $mensaje  = whatsapp_mensaje_pedido($ticket);
        $telefono = whatsapp_telefono($ticket['telefono']);

        return view('ticket_pedido', [
            'ticket'          => $ticket,
            'mensaje'         => $mensaje,
            'telefono'        => $telefono,                          // 52XXXXXXXXXX o null
            'urlWhatsapp'     => whatsapp_url($telefono, $mensaje),  // sin contacto si el teléfono no es válido
            'telefonoValido'  => $telefono !== null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // VISTA IMPRIMIBLE (fallback universal por navegador)
    // ─────────────────────────────────────────────────────────────
    public function vista($id = null)
    {
        if (! $this->esPersonal()) {
            return redirect()->to(site_url('/'))->with('error', 'No tienes permiso para imprimir tickets.');
        }

        if (! ctype_digit((string) $id) || (int) $id < 1) {
            return redirect()->to('lista_p_pedido')->with('error', 'Pedido no válido.');
        }

        $servicio = new ServicioTicket();

        try {
            $pedido = $servicio->cargarPedido((int) $id);
        } catch (ImpresoraException $e) {
            return redirect()->to('lista_p_pedido')->with('error', $e->getMessage());
        }

        if ($pedido === null) {
            return redirect()->to('lista_p_pedido')->with('error', 'Pedido no encontrado.');
        }

        $papel = (int) ($this->request->getGet('papel') ?? $servicio->config()->papelMm);

        return view('ticket_html', [
            'pedido'   => $pedido,
            'servicio' => $servicio,
            'cfg'      => $servicio->config(),
            'papel'    => $papel <= 58 ? 58 : 80,
            'auto'     => $this->request->getGet('auto') === '1',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // IMPRIMIR (JSON)
    // ─────────────────────────────────────────────────────────────
    public function imprimir($id = null): ResponseInterface
    {
        if (! $this->esPersonal()) {
            return $this->json(['ok' => false, 'mensaje' => 'No tienes permiso para imprimir tickets.'], 403);
        }

        if (! ctype_digit((string) $id) || (int) $id < 1) {
            return $this->json(['ok' => false, 'tipo' => 'no_existe', 'mensaje' => 'Pedido no válido.'], 422);
        }

        $id       = (int) $id;
        $servicio = new ServicioTicket();

        // Sin impresora directa configurada: se abre la vista imprimible del navegador.
        if ($servicio->usaNavegador()) {
            return $this->json([
                'ok'      => true,
                'modo'    => 'navegador',
                'mensaje' => 'Abriendo el ticket para imprimir.',
                'url'     => base_url("ticket_impreso/{$id}?auto=1"),
            ]);
        }

        try {
            return $this->json($servicio->imprimir($id));
        } catch (ImpresoraException $e) {
            return $this->errorJson($e, $id);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DIAGNÓSTICO
    // ─────────────────────────────────────────────────────────────
    public function configuracion()
    {
        if (! $this->esPersonal()) {
            return redirect()->to(site_url('/'))->with('error', 'No tienes permiso para ver la configuración de la impresora.');
        }

        $servicio = new ServicioTicket();

        return view('ticket_config', [
            'resumen'   => $servicio->resumen(),
            'navegador' => $servicio->usaNavegador(),
        ]);
    }

    public function prueba(): ResponseInterface
    {
        return $this->accionDiagnostico(static fn (ServicioTicket $s) => $s->imprimirPrueba());
    }

    public function conexion(): ResponseInterface
    {
        return $this->accionDiagnostico(static fn (ServicioTicket $s) => $s->probarConexion());
    }

    // ─────────────────────────────────────────────────────────────
    // Auxiliares
    // ─────────────────────────────────────────────────────────────
    private function accionDiagnostico(callable $accion): ResponseInterface
    {
        if (! $this->esPersonal()) {
            return $this->json(['ok' => false, 'mensaje' => 'No tienes permiso para esta acción.'], 403);
        }

        $servicio = new ServicioTicket();

        if ($servicio->usaNavegador()) {
            return $this->json([
                'ok'         => false,
                'tipo'       => 'no_configurada',
                'mensaje'    => 'La conexión está en modo "navegador": no hay impresora directa que probar.',
                'sugerencia' => 'Configura impresora.conexion en el archivo .env (red, windows, puerto o cups).',
            ], 422);
        }

        try {
            return $this->json($accion($servicio));
        } catch (ImpresoraException $e) {
            return $this->errorJson($e);
        }
    }

    private function errorJson(ImpresoraException $e, ?int $idPedido = null): ResponseInterface
    {
        $tipo = $e->tipo();

        $estado = match ($tipo) {
            'no_existe'                                   => 404,
            'sin_productos', 'config_invalida',
            'no_configurada'                              => 422,
            'ocupada'                                     => 409,
            default                                       => 503,
        };

        $datos = [
            'ok'         => false,
            'tipo'       => $tipo,
            'mensaje'    => $e->getMessage(),
            'sugerencia' => $e->sugerencia(),
        ];

        // Si el pedido es válido, se ofrece imprimirlo desde el navegador como salida de emergencia.
        if ($idPedido !== null && ! in_array($tipo, ['no_existe', 'sin_productos'], true)) {
            $datos['alternativa_url'] = base_url("ticket_impreso/{$idPedido}?auto=1");
        }

        return $this->json($datos, $estado);
    }

    /** Respuesta JSON con el token CSRF vigente (por si se regenera en Config\Security). */
    private function json(array $datos, int $estado = 200): ResponseInterface
    {
        helper('security');
        $datos['csrf'] = csrf_hash();

        return $this->response->setStatusCode($estado)->setJSON($datos);
    }
}
