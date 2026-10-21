<?php

namespace App\Controllers;

use App\Models\Modelo_sugerencia;
use App\Models\PdoModelo;
use Throwable;

/**
 * Buzón de sugerencias: cualquier usuario con sesión (de cualquier rol) puede sugerir
 * mejoras para el software.
 *
 * El usuario y el rol se toman SIEMPRE de la sesión de Shield, nunca del formulario,
 * para que nadie pueda enviar una sugerencia a nombre de otra persona u otro rol.
 * El CSRF lo exige el filtro global (Config\Filters), por eso el formulario lleva csrf_field().
 */
class Sugerencias extends BaseController
{
    // Anti-spam: máximo de sugerencias válidas por usuario dentro de la ventana de tiempo.
    private const LIMITE_ENVIOS    = 5;
    private const VENTANA_SEGUNDOS = 600; // 10 minutos

    // ─────────────────────────────────────────────────────────────
    // FORMULARIO
    // ─────────────────────────────────────────────────────────────
    public function crea_sugerencia()
    {
        return view('crea_sugerencia', [
            'valoraciones' => Modelo_sugerencia::VALORACIONES,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GUARDAR
    // ─────────────────────────────────────────────────────────────
    public function guarda_sugerencia()
    {
        $usuario = auth()->user();
        if ($usuario === null) {
            return redirect()->to(site_url('login'));
        }

        $roles = $usuario->getGroups();
        sort($roles); // orden estable: "admin, user" sin depender del orden en la BD

        $datos = [
            'titulo'      => $this->limpiaTexto($this->textoPost('titulo'), false),
            'descripcion' => $this->limpiaTexto($this->textoPost('descripcion'), true),
            'valoracion'  => $this->textoPost('valoracion'),
            'id_usuario'  => (int) $usuario->id,
            'usuario'     => mb_substr((string) ($usuario->username ?: $usuario->email), 0, 100),
            'rol'         => mb_substr($roles !== [] ? implode(', ', $roles) : 'sin rol', 0, 100),
        ];

        // Lo que se devuelve al formulario si algo falla. Son los datos ya saneados (no el
        // POST crudo con withInput()), así que un arreglo enviado a mano nunca llega a la sesión.
        $viejo = [
            'titulo'      => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'valoracion'  => in_array($datos['valoracion'], Modelo_sugerencia::VALORACIONES, true)
                ? $datos['valoracion']
                : '',
        ];

        // 1) Validación en servidor (longitudes y valoración dentro de la lista blanca).
        $validacion = service('validation');
        $validacion->setRules([
            'titulo'      => 'required|min_length[3]|max_length[' . Modelo_sugerencia::TITULO_MAX . ']',
            'descripcion' => 'required|min_length[10]|max_length[' . Modelo_sugerencia::DESCRIPCION_MAX . ']',
            'valoracion'  => 'required|in_list[' . implode(',', Modelo_sugerencia::VALORACIONES) . ']',
        ], [
            'titulo' => [
                'required'   => 'El título es obligatorio.',
                'min_length' => 'El título debe tener al menos 3 caracteres.',
                'max_length' => 'El título no puede superar los ' . Modelo_sugerencia::TITULO_MAX . ' caracteres.',
            ],
            'descripcion' => [
                'required'   => 'La descripción es obligatoria.',
                'min_length' => 'La descripción debe tener al menos 10 caracteres.',
                'max_length' => 'La descripción no puede superar los ' . Modelo_sugerencia::DESCRIPCION_MAX . ' caracteres.',
            ],
            'valoracion' => [
                'required' => 'Selecciona qué tan importante es tu sugerencia.',
                'in_list'  => 'La valoración seleccionada no es válida.',
            ],
        ]);

        if (! $validacion->run($datos)) {
            return redirect()->to(site_url('crea_sugerencia'))
                ->with('viejo', $viejo)
                ->with('errores', $validacion->getErrors());
        }

        // 2) Límite de envíos por usuario (solo cuenta las sugerencias válidas).
        $throttler = service('throttler');
        if (! $throttler->check('sugerencia_' . $usuario->id, self::LIMITE_ENVIOS, self::VENTANA_SEGUNDOS)) {
            $minutos = max(1, (int) ceil($throttler->getTokenTime() / 60));

            return redirect()->to(site_url('crea_sugerencia'))
                ->with('viejo', $viejo)
                ->with('error', "Has enviado muchas sugerencias seguidas. Intenta de nuevo en {$minutos} minuto(s).");
        }

        // 3) Inserción (sentencia preparada de PDO dentro del modelo).
        try {
            (new Modelo_sugerencia())->guardar($datos);
        } catch (Throwable $e) {
            // mensajeSeguro registra el detalle técnico en el log y devuelve un texto genérico.
            return redirect()->to(site_url('crea_sugerencia'))
                ->with('viejo', $viejo)
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo guardar tu sugerencia. Inténtalo de nuevo más tarde.'));
        }

        return redirect()->to(site_url('crea_sugerencia'))
            ->with('mensaje', '¡Gracias! Tu sugerencia fue enviada correctamente.');
    }

    // ─────────────────────────────────────────────────────────────
    // UTILIDADES PRIVADAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve el valor POST solo si es texto. Si alguien envía "titulo[]=x"
     * (un arreglo) se trata como vacío y la validación lo rechaza.
     */
    private function textoPost(string $campo): string
    {
        $valor = $this->request->getPost($campo);

        return is_string($valor) ? $valor : '';
    }

    /**
     * Normaliza el texto: quita caracteres de control, caracteres que invierten el sentido
     * de lectura (suplantación visual) y espacios sobrantes. NO elimina etiquetas HTML:
     * la protección contra XSS se hace escapando la salida con esc() en las vistas.
     */
    private function limpiaTexto(string $texto, bool $multilinea): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);

        if (! $multilinea) {
            $texto = str_replace(["\n", "\t"], ' ', $texto);
        }

        $patron = $multilinea
            ? '/(?![\n\t])\p{Cc}|[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u'
            : '/\p{Cc}|[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

        $texto = preg_replace($patron, '', $texto);

        return trim($texto ?? '');
    }
}
