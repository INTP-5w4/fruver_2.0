<?php

namespace App\Controllers;

use App\Libraries\Correo;
use App\Libraries\FiltroGroserias;
use App\Models\Modelo_consentimiento;
use App\Models\Modelo_pedido;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Comunicaciones con clientes. Solo personal.
 *
 * Voluntarias (Aviso de Consentimiento, finalidades V1–V3):
 *  - V1 promociones · V2 recordatorios de recompra · V3 encuestas de satisfacción.
 *  - La lista sale SIEMPRE del estado vigente de `consentimiento`: quien no autorizó la
 *    finalidad, o la revocó, no aparece. Cada persona aparece solo por los canales que eligió.
 *  - Todo mensaje lleva cómo dejar de recibirlos (Mis preferencias de privacidad).
 *  - V4 (recomendaciones) no se envía: se muestra dentro de la tienda (fila "Para ti").
 *
 * Avisos de pedidos (pestaña "pedidos"): detalle, confirmación, en camino, entregado, pago…
 * Es finalidad PRIMARIA ("confirmar tu pedido, coordinar la entrega y avisarte de cambios",
 * Aviso de Consentimiento, sección 4): no requiere V1–V3 y no lleva el pie de baja. Se
 * manda al teléfono del cliente del pedido, solo por WhatsApp o llamada.
 *
 * Cada pestaña trae mensajes listos (PLANTILLAS) que se eligen con un clic y se pueden
 * editar antes de enviar. El texto entre [corchetes] es para que el personal lo complete.
 *
 * WhatsApp y teléfono: el personal abre el enlace y envía/llama desde su teléfono.
 * Correo: se envía desde el servidor, un correo por persona (nunca con copia a otros).
 */
class Comunicaciones extends BaseController
{
    private const FINALIDADES = ['v1', 'v2', 'v3'];
    private const PEDIDOS     = 'pedidos';
    private const MENSAJE_MAX = 500;
    private const ASUNTO_MAX  = 120;
    private const MAX_CORREOS = 200; // por envío
    private const DIAS_PEDIDOS = 15;

    /**
     * Mensajes listos por pestaña: clave => [titulo, texto]. El primero es el que se carga
     * al abrir la pestaña.
     *
     * @var array<string, array<string, array{0:string,1:string}>>
     */
    private const PLANTILLAS = [
        'v1' => [
            'ofertas'    => ['Ofertas de la semana', 'Hola {nombre}, esta semana tenemos ofertas de temporada en FRUVER. Haz tu pedido en {tienda}'],
            'producto'   => ['Producto en oferta', 'Hola {nombre}, ¡esta semana [PRODUCTO] está en oferta a $[PRECIO] el kilo en FRUVER! Aprovecha antes de que se acabe: {tienda}'],
            'temporada'  => ['Llegó la temporada', 'Hola {nombre}, ya llegó la temporada de [FRUTA O VERDURA]: fresca y a buen precio. Pídela en {tienda}'],
            'mayoreo'    => ['Descuento por mayoreo', 'Hola {nombre}, esta semana en compras de más de [CANTIDAD] kg te damos [DESCUENTO]% de descuento. Haz tu pedido en {tienda}'],
            'finsemana'  => ['Fin de semana', 'Hola {nombre}, este fin de semana tenemos precios especiales en frutas y verduras. Revisa el catálogo y pide en {tienda}'],
            'nuevo'      => ['Producto nuevo', 'Hola {nombre}, ya tenemos [PRODUCTO NUEVO] en FRUVER. Pruébalo en tu próximo pedido: {tienda}'],
        ],
        'v2' => [
            'recompra'   => ['¿Ya se te acabó?', 'Hola {nombre}, ¿ya se te acabó {productos}? Vuelve a pedirlo en {tienda}'],
            'semanal'    => ['Pedido de la semana', 'Hola {nombre}, ¿armamos tu pedido de la semana? Lo de siempre ({productos}) ya está disponible en {tienda}'],
            'llego'      => ['Ya llegó lo que te gusta', 'Hola {nombre}, nos acaba de llegar {productos} fresco. Si quieres que te lo apartemos, pide en {tienda}'],
            'recordar'   => ['Te extrañamos', 'Hola {nombre}, hace tiempo que no te vemos por FRUVER. Tus productos de siempre ({productos}) te esperan en {tienda}'],
        ],
        'v3' => [
            'calificar'  => ['Calificar del 1 al 5', 'Hola {nombre}, ¿cómo te fue con tu {pedido}? Responde del 1 al 5 qué tan satisfecho quedaste. ¡Gracias!'],
            'entrega'    => ['¿Llegó bien?', 'Hola {nombre}, ¿tu {pedido} llegó a tiempo y en buen estado? Contesta SÍ o NO y cuéntanos qué podemos mejorar.'],
            'frescura'   => ['Frescura del producto', 'Hola {nombre}, del 1 al 5, ¿qué calificación le das a la frescura de los productos de tu {pedido}? Tu opinión nos ayuda mucho.'],
            'recomienda' => ['¿Nos recomendarías?', 'Hola {nombre}, del 0 al 10, ¿qué tanto recomendarías FRUVER a un familiar o amigo? Gracias por ayudarnos a mejorar.'],
        ],
        self::PEDIDOS => [
            'detalle'    => ['Detalle del pedido', "Hola {nombre}, este es el detalle de tu {pedido}:\n{detalle}\nTotal: {total}\nEstado: {estado}.\n¡Gracias por tu compra en FRUVER!"],
            'confirmado' => ['Pedido confirmado', 'Hola {nombre}, tu {pedido} fue confirmado y ya lo estamos preparando. Total: {total}.'],
            'en_camino'  => ['Va en camino', 'Hola {nombre}, tu {pedido} va en camino. Llega {hora}. Ten listo el pago de {total}, por favor.'],
            'entregado'  => ['Entregado', 'Hola {nombre}, tu {pedido} fue entregado. ¡Gracias por comprar en FRUVER! Haz tu siguiente pedido en {tienda}'],
            'estado'     => ['Estado del pedido', 'Hola {nombre}, te informamos que tu {pedido} está: {estado}. Cualquier duda, responde a este mensaje.'],
            'retraso'    => ['Retraso', 'Hola {nombre}, tu {pedido} se va a retrasar un poco. Te avisamos en cuanto salga. Disculpa la molestia.'],
            'pago'       => ['Pago pendiente', 'Hola {nombre}, te recordamos que tu {pedido} por {total} está pendiente de pago. Puedes pagar en efectivo o por transferencia. ¡Gracias!'],
            'cancelado'  => ['Cancelado', 'Hola {nombre}, tu {pedido} fue cancelado. Si fue un error o quieres volver a pedir, entra a {tienda} o responde a este mensaje.'],
        ],
    ];

    /** Plantilla que conviene según el estado elegido en el filtro de la pestaña de pedidos. */
    private const PLANTILLA_POR_ESTADO = [
        'pedido_realizado'   => 'detalle',
        'pedido_confirmado'  => 'confirmado',
        'pedido_en_transito' => 'en_camino',
        'pedido_entregado'   => 'entregado',
        'pedido_a_credito'   => 'pago',
        'pedido_cancelado'   => 'cancelado',
    ];

    private const ESTADOS = [
        'pedido_realizado', 'pedido_confirmado', 'pedido_en_transito', 'pedido_entregado',
        'pedido_a_credito', 'pedido_pagado', 'pedido_cancelado', 'pedido_pendiente',
    ];

    public function index()
    {
        $cfg       = config('Privacidad');
        $modelo    = new Modelo_consentimiento();
        $finalidad = $this->pestana($this->request->getGet('finalidad'));
        $esPedidos = $finalidad === self::PEDIDOS;
        $estado    = $esPedidos ? $this->estado($this->request->getGet('estado')) : null;

        $plantillas = self::PLANTILLAS[$finalidad];
        $plantilla  = (string) $this->request->getGet('plantilla');
        if (! isset($plantillas[$plantilla])) {
            $plantilla = $estado !== null && isset(self::PLANTILLA_POR_ESTADO[$estado])
                ? self::PLANTILLA_POR_ESTADO[$estado]
                : (string) array_key_first($plantillas);
        }

        $mensaje   = $this->texto($this->request->getGet('mensaje'), self::MENSAJE_MAX);
        $mensaje   = $mensaje !== '' ? $mensaje : $plantillas[$plantilla][1];
        // Con groserías no se arma ningún enlace de WhatsApp (y el correo lo rechaza enviarCorreo()).
        $bloqueado = FiltroGroserias::crear()->contiene($mensaje);

        if (! $modelo->tablaExiste()) {
            return redirect()->to(site_url('/'))->with('error', mensaje_tecnico('Falta ejecutar la migración de privacidad (php spark migrate).', 'Esta sección aún no está activada. Avisa al administrador del sistema.'));
        }

        helper('whatsapp');

        $pie   = $esPedidos ? '' : $this->pie();
        $filas = $esPedidos
            ? $this->filasPedidos($mensaje, $estado, $bloqueado)
            : $this->filasConsentimiento($modelo, $finalidad, $mensaje, $bloqueado);

        return view('comunicaciones', [
            'cfg'          => $cfg,
            'finalidad'    => $finalidad,
            'esPedidos'    => $esPedidos,
            'finalidades'  => array_intersect_key($cfg->finalidades, array_flip(self::FINALIDADES)),
            'conteos'      => $modelo->conteoFinalidades(),
            'mensaje'      => $mensaje,
            'plantillas'   => $plantillas,
            'plantilla'    => $plantilla,
            'filas'        => $filas,
            'porCorreo'    => count(array_filter($filas, static fn (array $f): bool => $f['por_correo'])),
            'porWhatsapp'  => count(array_filter($filas, static fn (array $f): bool => $f['puede_wa'])),
            'bloqueado'    => $bloqueado,
            'correoListo'  => Correo::configurado(),
            'asunto'       => 'FRUVER · ' . ($esPedidos ? 'Tu pedido' : $cfg->finalidades[$finalidad]['titulo']),
            'pie'          => $pie,
            'estado'       => $estado,
            'estados'      => self::ESTADOS,
            'diasPedidos'  => self::DIAS_PEDIDOS,
        ]);
    }

    public function enviarCorreo(): RedirectResponse
    {
        $finalidad = $this->pestana($this->request->getPost('finalidad'));
        $mensaje   = $this->texto($this->request->getPost('mensaje'), self::MENSAJE_MAX);
        $asunto    = $this->texto($this->request->getPost('asunto'), self::ASUNTO_MAX);
        $volver    = redirect()->to(site_url('comunicaciones') . '?' . http_build_query(['finalidad' => $finalidad, 'mensaje' => $mensaje]));

        // Los avisos de pedidos van por WhatsApp o llamada (el cliente del pedido no siempre tiene correo).
        if ($finalidad === self::PEDIDOS) {
            return $volver->with('error', 'Los avisos de pedidos se envían por WhatsApp o llamada.');
        }
        if (! Correo::configurado()) {
            return $volver->with('error', mensaje_tecnico('No hay servidor de correo configurado (ver docs/PRIVACIDAD.md).', 'El envío por correo aún no está activado. Avisa al administrador del sistema.'));
        }
        if (mb_strlen($mensaje) < 10 || mb_strlen($asunto) < 3) {
            return $volver->with('error', 'Escribe un asunto y un mensaje (al menos 10 caracteres).');
        }
        if (FiltroGroserias::crear()->contiene($mensaje) || FiltroGroserias::crear()->contiene($asunto)) {
            return $volver->with('error', 'El mensaje o el asunto tienen palabras que no están permitidas. Corrígelo antes de enviar.');
        }

        // Un envío por minuto por persona del personal: evita dobles clics y envíos masivos accidentales.
        if (! service('throttler')->check('comunicaciones_' . auth()->id(), 1, MINUTE)) {
            return $volver->with('error', 'Espera un minuto antes de hacer otro envío.');
        }

        $enviados = 0;
        $fallidos = 0;
        $lista    = array_filter(
            $this->destinatarios(new Modelo_consentimiento(), $finalidad),
            static fn (array $d): bool => (int) $d['canal_correo'] === 1 && ! empty($d['correo'])
        );

        foreach (array_slice($lista, 0, self::MAX_CORREOS) as $d) {
            $html = view('correos/comunicacion', [
                'texto'       => $this->personalizar($mensaje, $d),
                'finalidad'   => config('Privacidad')->finalidades[$finalidad],
            ]);

            Correo::enviar((string) $d['correo'], $asunto, $html) ? $enviados++ : $fallidos++;
        }

        log_message('info', 'Comunicación {finalidad} por correo: {enviados} enviados, {fallidos} fallidos (usuario {usuario}).', [
            'finalidad' => strtoupper($finalidad),
            'enviados'  => $enviados,
            'fallidos'  => $fallidos,
            'usuario'   => auth()->id(),
        ]);

        $texto = "Se enviaron {$enviados} correo(s)." . ($fallidos > 0 ? " {$fallidos} no se pudieron enviar (ver el log)." : '');
        if (count($lista) > self::MAX_CORREOS) {
            $texto .= ' Solo se envían ' . self::MAX_CORREOS . ' por vez.';
        }

        return $volver->with('mensaje', $texto);
    }

    /**
     * GET comunicaciones/revisar?texto=… → {"ok": true|false}. Lo usa comunicaciones.js mientras
     * se escribe el mensaje: con groserías se desactivan los botones de envío. La lista de
     * palabras se queda en el servidor (no se manda al navegador).
     */
    public function revisar(): ResponseInterface
    {
        $texto = $this->texto($this->request->getGet('texto'), self::MENSAJE_MAX);

        return $this->response->setJSON(['ok' => ! FiltroGroserias::crear()->contiene($texto)]);
    }

    // ─────────────────────────────────────────────────────────────
    // FILAS DE CADA PESTAÑA
    // ─────────────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> personas que autorizaron V1–V3 */
    private function filasConsentimiento(Modelo_consentimiento $modelo, string $finalidad, string $mensaje, bool $bloqueado): array
    {
        $filas = [];
        foreach ($this->destinatarios($modelo, $finalidad) as $d) {
            $texto = $this->personalizar($mensaje, $d);

            // Teléfono de la persona: el que dio para contacto; si no, el de su perfil de cliente.
            $telefono = trim((string) ($d['telefono_contacto'] ?? ''));
            if (whatsapp_telefono($telefono) === null) {
                $telefono = trim((string) ($d['telefono_perfil'] ?? ''));
            }
            $tel        = whatsapp_telefono($telefono);
            $mensajeria = (int) $d['canal_mensajeria'] === 1;

            $filas[] = $d + [
                'clave'      => 'u' . (int) $d['id_user'],
                'detalle'    => 'desde ' . date('d/m/Y', strtotime((string) $d['autorizado_en'])),
                'texto'      => $texto,
                'reemplazos' => $this->reemplazos($d),
                'tel_wa'     => $tel,
                // WhatsApp solo a quien eligió mensajería (lo que declara el Aviso de Privacidad).
                'whatsapp'   => $mensajeria && $tel !== null && ! $bloqueado ? whatsapp_url($tel, $texto) : null,
                'puede_wa'   => $mensajeria && $tel !== null,
                'sin_wa'     => ! $mensajeria ? 'No eligió WhatsApp' : ($tel === null ? 'Sin teléfono válido' : null),
                'llamar'     => (int) $d['canal_telefono'] === 1 && $telefono !== '' ? $telefono : null,
                'por_correo' => (int) $d['canal_correo'] === 1 && ! empty($d['correo']),
            ];
        }

        return $filas;
    }

    /** @return list<array<string,mixed>> un renglón por pedido reciente */
    private function filasPedidos(string $mensaje, ?string $estado, bool $bloqueado): array
    {
        $filas = [];
        foreach ((new Modelo_pedido())->paraAvisos(self::DIAS_PEDIDOS, $estado) as $p) {
            $reemplazos = $this->reemplazosPedido($p);
            $texto      = strtr($mensaje, $reemplazos);
            $telefono   = trim((string) ($p['telefono'] ?? ''));
            $tel        = whatsapp_telefono($telefono);

            $filas[] = [
                'clave'          => 'p' . (int) $p['id'],
                'nombre_mostrar' => trim((string) $p['nombre'] . ' ' . (string) $p['ape_pat']),
                'detalle'        => '#' . (int) $p['id'] . ' · ' . estado_pedido_texto($p['estado']) . ' · $' . number_format((float) $p['total'], 2),
                'texto'          => $texto,
                'reemplazos'     => $reemplazos,
                'tel_wa'         => $tel,
                'whatsapp'       => $tel !== null && ! $bloqueado ? whatsapp_url($tel, $texto) : null,
                'puede_wa'       => $tel !== null,
                'sin_wa'         => $tel === null ? 'Sin teléfono válido' : null,
                'llamar'         => $telefono !== '' ? $telefono : null,
                'por_correo'     => false,
            ];
        }

        return $filas;
    }

    /**
     * Destinatarios con los datos que su finalidad necesita (V2: productos frecuentes;
     * V3: pedido a evaluar). Una consulta por cada dato extra, no una por persona.
     *
     * @return list<array<string,mixed>>
     */
    private function destinatarios(Modelo_consentimiento $modelo, string $finalidad): array
    {
        $lista = $modelo->destinatarios($finalidad);
        $ids   = array_filter(array_map('intval', array_column($lista, 'id_cliente')));

        $frecuentes = $finalidad === 'v2' ? (new Modelo_pedido())->productosFrecuentes($ids) : [];
        $ultimos    = $finalidad === 'v3' ? (new Modelo_pedido())->ultimosDeClientes($ids) : [];

        return array_map(static function (array $d) use ($frecuentes, $ultimos): array {
            $id = (int) ($d['id_cliente'] ?? 0);

            $d['nombre_mostrar'] = trim((string) ($d['nombre'] ?? '') . ' ' . (string) ($d['ape_pat'] ?? ''))
                ?: (string) $d['username'];
            $d['productos'] = $frecuentes[$id] ?? [];
            $d['pedido']    = $ultimos[$id] ?? null;

            return $d;
        }, $lista);
    }

    /**
     * Valores de las etiquetas {nombre}, {productos}, {pedido} y {tienda} para una persona.
     * Los usa personalizar() y también la vista (data-reemplazos), para que el mensaje se
     * actualice en el navegador mientras se escribe, con exactamente el mismo resultado.
     *
     * @return array<string,string>
     */
    private function reemplazos(array $d): array
    {
        return [
            '{nombre}'    => (string) ($d['nombre'] ?? '') !== '' ? (string) $d['nombre'] : (string) $d['username'],
            '{productos}' => $d['productos'] !== [] ? implode(', ', $d['productos']) : 'tus productos de siempre',
            '{pedido}'    => $d['pedido'] !== null
                ? 'pedido #' . $d['pedido']['id'] . ' del ' . date('d/m/Y', strtotime($d['pedido']['fecha']))
                : 'último pedido',
            '{tienda}'    => site_url('main_page_4'),
        ];
    }

    /**
     * Etiquetas de un aviso de pedido: {nombre}, {pedido}, {detalle}, {total}, {estado},
     * {fecha}, {hora} y {tienda}.
     *
     * @return array<string,string>
     */
    private function reemplazosPedido(array $p): array
    {
        $detalle = [];
        foreach ($p['lineas'] as $l) {
            $importe   = $l['total'] !== null ? (float) $l['total'] : (float) $l['cant'] * (float) $l['precio_venta'];
            $cantidad  = rtrim(rtrim(number_format((float) $l['cant'], 2, '.', ''), '0'), '.');
            $detalle[] = '• ' . $cantidad . ' ' . mb_strtolower((string) $l['unidad_venta']) . ' de ' . $l['nombre'] . ' — $' . number_format($importe, 2);
        }

        return [
            '{nombre}'  => (string) $p['nombre'],
            '{pedido}'  => 'pedido #' . (int) $p['id'],
            '{detalle}' => $detalle !== [] ? implode("\n", $detalle) : '(sin productos)',
            '{total}'   => '$' . number_format((float) $p['total'], 2),
            '{estado}'  => mb_strtolower(estado_pedido_texto($p['estado'])),
            '{fecha}'   => $p['fecha'] ? date('d/m/Y', strtotime((string) $p['fecha'])) : '',
            '{hora}'    => ! empty($p['eta']) ? 'aproximadamente a las ' . date('H:i', strtotime((string) $p['eta'])) : 'pronto',
            '{tienda}'  => site_url('main_page_4'),
        ];
    }

    /** Texto fijo que se agrega al final de todo mensaje voluntario (cómo dejar de recibirlos). */
    private function pie(): string
    {
        return "\n\nPara dejar de recibir estos mensajes entra a " . site_url('privacidad/preferencias')
            . ' o respóndenos BAJA.';
    }

    /**
     * Sustituye las etiquetas y agrega cómo darse de baja. strtr() es una sustitución de texto
     * simple, en una sola pasada: no ejecuta nada ni vuelve a revisar lo que insertó.
     */
    private function personalizar(string $plantilla, array $d): string
    {
        return strtr($plantilla, $this->reemplazos($d)) . $this->pie();
    }

    /** Pestaña: v1..v3 o "pedidos". */
    private function pestana(mixed $valor): string
    {
        return is_string($valor) && (in_array($valor, self::FINALIDADES, true) || $valor === self::PEDIDOS) ? $valor : 'v1';
    }

    private function estado(mixed $valor): ?string
    {
        return is_string($valor) && in_array($valor, self::ESTADOS, true) ? $valor : null;
    }

    private function texto(mixed $valor, int $max): string
    {
        if (! is_string($valor)) {
            return '';
        }

        // Sin caracteres de control (salvo saltos de línea) ni marcas de dirección de texto.
        $valor = preg_replace('/(?![\n])\p{Cc}|[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', str_replace("\r\n", "\n", $valor)) ?? '';

        return mb_substr(trim($valor), 0, $max);
    }
}
