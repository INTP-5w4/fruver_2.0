<?php

namespace App\Controllers;

use App\Models\Modelo_pedido;
use App\Models\PdoModelo;
use Throwable;

/**
 * Ticket (resumen) del pedido para enviarlo al cliente por WhatsApp.
 * Solo personal (la ruta ya exige el grupo; aquí se repite la verificación).
 */
class Tickets extends BaseController
{
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
}
