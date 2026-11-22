<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Session as SesionConfig;

/**
 * Protección de la sesión iniciada. Hace dos cosas en cada petición:
 *
 * 1. Cierre por inactividad. Si pasaron más de Config\Session::$expiration segundos
 *    (30 minutos) desde la última petición, se cierra la sesión y se pide entrar de
 *    nuevo. La cookie y el recolector de sesiones de PHP no bastan: se renuevan con la
 *    regeneración del ID y no garantizan el corte exacto.
 *
 * 2. Anti secuestro de sesión (session hijacking). Al iniciar sesión se guarda una
 *    "huella" del navegador (hash del User-Agent). Si alguien roba la cookie y la usa
 *    desde otro navegador, la huella no coincide y la sesión se destruye.
 *
 * Se complementa con lo que ya hace CodeIgniter/Shield:
 *  - cookie de sesión HttpOnly + SameSite=Lax (+ Secure en producción),
 *  - regeneración del ID de sesión al iniciar sesión y cada Session::$timeToUpdate segundos,
 *  - Session::$regenerateDestroy = true (se borra la sesión vieja al regenerar),
 *  - "Recordarme" apagado (Config\Auth::$sessionConfig), para que nada reabra la sesión.
 *
 * No se liga a la IP a propósito: en celulares la IP cambia a mitad de la sesión.
 */
class SesionGuard implements FilterInterface
{
    private const LLAVE = 'huella_navegador';

    /** Marca de tiempo (Unix) de la última petición con sesión iniciada. */
    public const ACTIVIDAD = 'ultima_actividad';

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest || ! auth()->loggedIn()) {
            return null;
        }

        $sesion = session();
        $ahora  = time();
        $limite = max(60, config(SesionConfig::class)->expiration);
        $ultima = $sesion->get(self::ACTIVIDAD);

        if (is_int($ultima) && $ahora - $ultima > $limite) {
            return $this->cerrar($request, lang('Auth.sesionInactiva', [intdiv($limite, 60)]));
        }

        $huella   = hash('sha256', (string) $request->getUserAgent()->getAgentString());
        $guardada = $sesion->get(self::LLAVE);

        if ($guardada !== null && ! hash_equals((string) $guardada, $huella)) {
            log_message('warning', 'Sesión descartada: la huella del navegador no coincide (IP {ip}).', [
                'ip' => $request->getIPAddress(),
            ]);

            return $this->cerrar($request, null);
        }

        $sesion->set([self::LLAVE => $huella, self::ACTIVIDAD => $ahora]);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * Cierra la sesión. Las peticiones con fetch reciben 401 en JSON (no una página de
     * login dentro de la respuesta); las demás van al login con el motivo.
     */
    private function cerrar(IncomingRequest $request, ?string $motivo)
    {
        // Shield borra todos los datos de la sesión y regenera el ID (destruye la anterior);
        // la sesión nueva, vacía, solo sirve para llevar el aviso al login.
        auth()->logout();

        if ($request->isAJAX()) {
            return service('response')->setStatusCode(401)->setJSON([
                'ok'      => false,
                'mensaje' => $motivo ?? 'Tu sesión se cerró. Vuelve a iniciar sesión.',
                'login'   => site_url('login'),
            ]);
        }

        $destino = redirect()->to(site_url('login'));

        return $motivo !== null ? $destino->with('error', $motivo) : $destino;
    }
}
