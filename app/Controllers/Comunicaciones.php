<?php

namespace App\Controllers;

use App\Libraries\Correo;
use App\Models\Modelo_consentimiento;
use App\Models\Modelo_pedido;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Comunicaciones voluntarias (Aviso de Consentimiento, finalidades V1–V3). Solo personal.
 *
 *  - V1 promociones · V2 recordatorios de recompra · V3 encuestas de satisfacción.
 *  - La lista sale SIEMPRE del estado vigente de `consentimiento`: quien no autorizó la
 *    finalidad, o la revocó, no aparece. Cada persona aparece solo por los canales que eligió.
 *  - Todo mensaje lleva cómo dejar de recibirlos (Mis preferencias de privacidad).
 *  - V4 (recomendaciones) no se envía: se muestra dentro de la tienda (fila "Para ti").
 *
 * WhatsApp y teléfono: el personal abre el enlace y envía/llama desde su teléfono.
 * Correo: se envía desde el servidor, un correo por persona (nunca con copia a otros).
 */
class Comunicaciones extends BaseController
{
    private const FINALIDADES = ['v1', 'v2', 'v3'];
    private const MENSAJE_MAX = 500;
    private const ASUNTO_MAX  = 120;
    private const MAX_CORREOS = 200; // por envío

    private const PLANTILLAS = [
        'v1' => 'Hola {nombre}, esta semana tenemos ofertas de temporada en FRUVER. Haz tu pedido en {tienda}',
        'v2' => 'Hola {nombre}, ¿ya se te acabó {productos}? Vuelve a pedirlo en {tienda}',
        'v3' => 'Hola {nombre}, ¿cómo te fue con tu {pedido}? Responde del 1 al 5 qué tan satisfecho quedaste. ¡Gracias!',
    ];

    public function index()
    {
        $cfg       = config('Privacidad');
        $modelo    = new Modelo_consentimiento();
        $finalidad = $this->finalidad($this->request->getGet('finalidad'));
        $mensaje   = $this->texto($this->request->getGet('mensaje'), self::MENSAJE_MAX);
        $mensaje   = $mensaje !== '' ? $mensaje : self::PLANTILLAS[$finalidad];

        if (! $modelo->tablaExiste()) {
            return redirect()->to(site_url('/'))->with('error', 'Falta ejecutar la migración de privacidad (php spark migrate).');
        }

        helper('whatsapp');

        $filas = [];
        foreach ($this->destinatarios($modelo, $finalidad) as $d) {
            $texto   = $this->personalizar($mensaje, $d);
            $tel     = $d['telefono_contacto'] !== null ? whatsapp_telefono((string) $d['telefono_contacto']) : null;
            $filas[] = $d + [
                'texto'      => $texto,
                'reemplazos' => $this->reemplazos($d),
                'tel_wa'     => $tel,
                'whatsapp'   => (int) $d['canal_mensajeria'] === 1 && $tel !== null ? whatsapp_url($tel, $texto) : null,
                'llamar'    => (int) $d['canal_telefono'] === 1 ? (string) $d['telefono_contacto'] : null,
                'por_correo' => (int) $d['canal_correo'] === 1 && ! empty($d['correo']),
            ];
        }

        return view('comunicaciones', [
            'cfg'          => $cfg,
            'finalidad'    => $finalidad,
            'finalidades'  => array_intersect_key($cfg->finalidades, array_flip(self::FINALIDADES)),
            'conteos'      => $modelo->conteoFinalidades(),
            'mensaje'      => $mensaje,
            'filas'        => $filas,
            'porCorreo'    => count(array_filter($filas, static fn (array $f): bool => $f['por_correo'])),
            'correoListo'  => Correo::configurado(),
            'asunto'       => 'FRUVER · ' . $cfg->finalidades[$finalidad]['titulo'],
            'pie'          => $this->pie(),
        ]);
    }

    public function enviarCorreo(): RedirectResponse
    {
        $finalidad = $this->finalidad($this->request->getPost('finalidad'));
        $mensaje   = $this->texto($this->request->getPost('mensaje'), self::MENSAJE_MAX);
        $asunto    = $this->texto($this->request->getPost('asunto'), self::ASUNTO_MAX);
        $volver    = redirect()->to(site_url('comunicaciones') . '?' . http_build_query(['finalidad' => $finalidad, 'mensaje' => $mensaje]));

        if (! Correo::configurado()) {
            return $volver->with('error', 'No hay servidor de correo configurado (ver docs/PRIVACIDAD.md).');
        }
        if (mb_strlen($mensaje) < 10 || mb_strlen($asunto) < 3) {
            return $volver->with('error', 'Escribe un asunto y un mensaje (al menos 10 caracteres).');
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

    // ─────────────────────────────────────────────────────────────
    // APOYO
    // ─────────────────────────────────────────────────────────────

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

    /** Texto fijo que se agrega al final de todo mensaje (cómo dejar de recibirlos). */
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

    private function finalidad(mixed $valor): string
    {
        return is_string($valor) && in_array($valor, self::FINALIDADES, true) ? $valor : 'v1';
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
