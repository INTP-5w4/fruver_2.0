<?php

/**
 * Ayudas para enviar el ticket (resumen) de un pedido por WhatsApp.
 *
 * Se usa el enlace "clic para chatear" de WhatsApp (https://wa.me/NUMERO?text=MENSAJE):
 * abre WhatsApp (app o web) con el chat del cliente y el mensaje ya escrito; la persona
 * solo presiona "Enviar". No requiere cuenta de WhatsApp Business ni claves de API.
 *
 * Se carga con helper('whatsapp').
 */

if (! defined('WHATSAPP_PAIS')) {
    /** Código de país que se antepone a los teléfonos de 10 dígitos (México). */
    define('WHATSAPP_PAIS', '52');
}

if (! defined('WHATSAPP_MAX_LINEAS')) {
    /** Máximo de productos que se listan en el mensaje (el enlace tiene un largo máximo). */
    define('WHATSAPP_MAX_LINEAS', 30);
}

if (! function_exists('whatsapp_telefono')) {
    /**
     * Normaliza un teléfono mexicano al formato internacional que pide wa.me:
     * solo dígitos, sin "+" (por ejemplo 5215512345678 -> 525512345678).
     * Devuelve null si el número no es válido.
     *
     * Acepta: 10 dígitos (5512345678), 12 con lada (525512345678) y el formato antiguo
     * de celular con 1 (5215512345678). Ignora espacios, guiones, paréntesis y "+".
     */
    function whatsapp_telefono(?string $telefono): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $telefono) ?? '';

        if (strlen($d) === 13 && str_starts_with($d, WHATSAPP_PAIS . '1')) {
            $d = WHATSAPP_PAIS . substr($d, 3); // 521XXXXXXXXXX -> 52XXXXXXXXXX
        }

        if (strlen($d) === 12 && str_starts_with($d, WHATSAPP_PAIS)) {
            $d = substr($d, 2);
        }

        // Número nacional: 10 dígitos y la lada nunca empieza con 0 ni con 1.
        if (preg_match('/^[2-9]\d{9}$/', $d) !== 1) {
            return null;
        }

        return WHATSAPP_PAIS . $d;
    }
}

if (! function_exists('whatsapp_texto')) {
    /**
     * Deja un texto en una sola línea y sin caracteres de control, para que un nombre
     * (de cliente o de producto) no pueda "romper" la estructura del mensaje.
     */
    function whatsapp_texto(?string $texto): string
    {
        $limpio = preg_replace('/[\p{C}\s]+/u', ' ', (string) $texto);

        return trim($limpio ?? '');
    }
}

if (! function_exists('whatsapp_dinero')) {
    function whatsapp_dinero(float|int|string|null $valor): string
    {
        return '$' . number_format((float) $valor, 2, '.', ',');
    }
}

if (! function_exists('whatsapp_cantidad')) {
    /** 2.00 -> "2", 1.50 -> "1.5" */
    function whatsapp_cantidad(float|int|string|null $valor): string
    {
        $texto = number_format((float) $valor, 2, '.', '');

        return rtrim(rtrim($texto, '0'), '.');
    }
}

if (! function_exists('whatsapp_unidad')) {
    /** Nombre corto de la unidad de venta. */
    function whatsapp_unidad(?string $unidad): string
    {
        $unidad = whatsapp_texto($unidad);

        return match (mb_strtolower($unidad)) {
            'kilogramo' => 'kg',
            'litro'     => 'L',
            'pieza'     => 'pza',
            'caja'      => 'caja',
            'domo'      => 'domo',
            'ramo', 'ramos' => 'ramo',
            default     => mb_strtolower($unidad),
        };
    }
}

if (! function_exists('whatsapp_mensaje_pedido')) {
    /**
     * Arma el texto del ticket. Usa el formato de WhatsApp (*negritas*).
     *
     * @param array{id:int|string,fecha:?string,cliente:string,estado:?string,total:float|int|string,
     *              items:list<array<string,mixed>>} $ticket
     */
    function whatsapp_mensaje_pedido(array $ticket): string
    {
        $fecha = (string) ($ticket['fecha'] ?? '');
        $dt    = DateTime::createFromFormat('Y-m-d', $fecha);
        $fecha = $dt instanceof DateTime ? $dt->format('d/m/Y') : whatsapp_texto($fecha);

        $estado = whatsapp_texto(str_replace('_', ' ', (string) ($ticket['estado'] ?? '')));
        $estado = $estado === '' ? '' : mb_strtoupper(mb_substr($estado, 0, 1)) . mb_substr($estado, 1);

        $lineas = [
            '*FRUVER* - Resumen de tu pedido',
            '',
            'Pedido *#' . (int) $ticket['id'] . '*',
        ];

        if ($fecha !== '') {
            $lineas[] = 'Fecha: ' . $fecha;
        }

        $cliente = whatsapp_texto($ticket['cliente'] ?? '');
        if ($cliente !== '') {
            $lineas[] = 'Cliente: ' . $cliente;
        }

        $lineas[] = '';
        $lineas[] = '*Productos*';

        $items = array_values($ticket['items'] ?? []);

        foreach (array_slice($items, 0, WHATSAPP_MAX_LINEAS) as $item) {
            $cant   = (float) ($item['cant'] ?? 0);
            $precio = (float) ($item['precio_venta'] ?? 0);
            $total  = ($item['total'] ?? null) !== null ? (float) $item['total'] : round($cant * $precio, 2);

            $lineas[] = sprintf(
                '- %s %s %s x %s = %s',
                whatsapp_cantidad($cant),
                whatsapp_unidad($item['unidad_venta'] ?? ''),
                whatsapp_texto($item['nombre_producto'] ?? ''),
                whatsapp_dinero($precio),
                whatsapp_dinero($total)
            );
        }

        $restantes = count($items) - WHATSAPP_MAX_LINEAS;
        if ($restantes > 0) {
            $lineas[] = '... y ' . $restantes . ' producto(s) más';
        }

        $lineas[] = '';
        $lineas[] = '*Total: ' . whatsapp_dinero($ticket['total'] ?? 0) . '*';

        if ($estado !== '') {
            $lineas[] = 'Estado: ' . $estado;
        }

        $lineas[] = '';
        $lineas[] = 'Gracias por tu preferencia.';

        return implode("\n", $lineas);
    }
}

if (! function_exists('whatsapp_url')) {
    /**
     * Enlace de WhatsApp con el mensaje ya escrito. Si el teléfono no es válido (null),
     * devuelve el enlace sin contacto: WhatsApp deja elegir a quién enviarlo.
     * La parte variable va siempre codificada (solo dígitos en el número, rawurlencode en el texto).
     */
    function whatsapp_url(?string $telefonoNormalizado, string $mensaje): string
    {
        $destino = ($telefonoNormalizado !== null && ctype_digit($telefonoNormalizado))
            ? $telefonoNormalizado
            : '';

        return 'https://wa.me/' . $destino . '?text=' . rawurlencode($mensaje);
    }
}
