<?php

namespace App\Controllers;

use App\Models\Modelo_sugerencia;
use CodeIgniter\Controller;

class Sugerencias extends Controller
{
    protected $helpers = ['form', 'url'];

    // Anti-spam: máximo de sugerencias válidas por usuario dentro de la ventana de tiempo.
    private const LIMITE_ENVIOS    = 5;
    private const VENTANA_SEGUNDOS = 600; // 10 minutos

    // ─────────────────────────────────────────────────────────────
    // FORMULARIO
    // ─────────────────────────────────────────────────────────────
    public function crea_sugerencia()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('login');
        }

        return view('crea_sugerencia', [
            'valoraciones' => Modelo_sugerencia::VALORACIONES,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GUARDAR
    // ─────────────────────────────────────────────────────────────
    public function guarda_sugerencia()
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('login');
        }

        $m_sugerencia = new Modelo_sugerencia();

        // Usuario y rol se toman SIEMPRE de la sesión, nunca del formulario,
        // para que nadie pueda suplantar a otro usuario ni a otro rol.
        $roles = $user->getGroups();
        sort($roles); // orden estable: "admin, user" y no depende del orden en la BD
        $datos = [
            'titulo'      => $this->limpiaTexto($this->textoPost('titulo'), false),
            'descripcion' => $this->limpiaTexto($this->textoPost('descripcion'), true),
            'valoracion'  => $this->textoPost('valoracion'),
            'id_usuario'  => (int) $user->id,
            'usuario'     => mb_substr((string) ($user->username ?: $user->email), 0, 100),
            'rol'         => mb_substr($roles ? implode(', ', $roles) : 'sin rol', 0, 100),
        ];

        // Valores que se devuelven al formulario si algo falla. Se usan los datos ya saneados
        // (NO el POST crudo con withInput()), así un arreglo o un UTF-8 inválido enviado a mano
        // nunca llega a la sesión ni rompe la siguiente carga del formulario.
        $viejo = [
            'titulo'      => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'valoracion'  => in_array($datos['valoracion'], Modelo_sugerencia::VALORACIONES, true)
                ? $datos['valoracion']
                : '',
        ];

        // 1) Validación en servidor (longitudes y valoración dentro de la lista blanca).
        if (! $m_sugerencia->validate($datos)) {
            return redirect()->to('crea_sugerencia')
                ->with('viejo', $viejo)
                ->with('errores', $m_sugerencia->errors());
        }

        // 2) Límite de envíos por usuario (solo cuenta las sugerencias válidas).
        $throttler = service('throttler');
        if (! $throttler->check('sugerencia_' . $user->id, self::LIMITE_ENVIOS, self::VENTANA_SEGUNDOS)) {
            $minutos = max(1, (int) ceil($throttler->getTokenTime() / 60));

            return redirect()->to('crea_sugerencia')
                ->with('viejo', $viejo)
                ->with('error', "Has enviado muchas sugerencias seguidas. Intenta de nuevo en {$minutos} minuto(s).");
        }

        // 3) Inserción (consultas parametrizadas por el Query Builder del modelo).
        try {
            $guardado = $m_sugerencia->insert($datos);
        } catch (\Throwable $e) {
            // El detalle técnico va al log; al usuario solo un mensaje genérico.
            log_message('error', 'Buzón de sugerencias: no se pudo guardar. ' . $e->getMessage());
            $guardado = false;
        }

        if ($guardado === false) {
            return redirect()->to('crea_sugerencia')
                ->with('viejo', $viejo)
                ->with('error', 'No se pudo guardar tu sugerencia. Inténtalo de nuevo más tarde.');
        }

        return redirect()->to('crea_sugerencia')
            ->with('mensaje', '¡Gracias! Tu sugerencia fue enviada correctamente.');
    }

    // ─────────────────────────────────────────────────────────────
    // UTILIDADES PRIVADAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve el valor POST solo si es texto. Si alguien envía "titulo[]=x"
     * (un arreglo) se trata como vacío, así no se rompe la validación ni el modelo.
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
     * Si el texto no es UTF-8 válido, devuelve cadena vacía y la validación lo rechaza.
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
