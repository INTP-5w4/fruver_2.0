<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Anti secuestro de sesión (session hijacking).
 *
 * Al iniciar sesión se guarda una "huella" del navegador (hash del User-Agent) dentro
 * de la sesión del servidor. En cada petición se compara: si alguien roba la cookie de
 * sesión y la usa desde otro navegador/cliente, la huella no coincide, se destruye la
 * sesión y se le manda a iniciar sesión de nuevo.
 *
 * Se complementa con lo que ya hace CodeIgniter/Shield:
 *  - cookie de sesión HttpOnly + SameSite=Lax (+ Secure en producción),
 *  - regeneración del ID de sesión al iniciar sesión y cada Session::$timeToUpdate segundos,
 *  - Session::$regenerateDestroy = true (se borra la sesión vieja al regenerar).
 *
 * No se liga a la IP a propósito: en celulares la IP cambia a mitad de la sesión.
 */
class SesionGuard implements FilterInterface
{
    private const LLAVE = 'huella_navegador';

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! auth()->loggedIn()) {
            return null;
        }

        $sesion = session();
        $huella = hash('sha256', (string) $request->getUserAgent()->getAgentString());
        $guardada = $sesion->get(self::LLAVE);

        if ($guardada === null) {
            $sesion->set(self::LLAVE, $huella);

            return null;
        }

        if (! hash_equals((string) $guardada, $huella)) {
            log_message('warning', 'Sesión descartada: la huella del navegador no coincide (IP {ip}).', [
                'ip' => $request->getIPAddress(),
            ]);

            auth()->logout();
            $sesion->destroy();

            return redirect()->to(site_url('login'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
