<?php

namespace App\Filters;

use App\Models\Modelo_consentimiento;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Exige que toda cuenta con sesión haya aceptado la versión vigente del Aviso de Privacidad.
 *
 * Cubre a quien no pasó por el formulario de registro: cuentas creadas antes de este aviso,
 * personal dado de alta desde la consola (`php spark shield:user create`) y a todos cuando
 * sube Config\Privacidad::$version (sección 14 del aviso).
 *
 * El resultado se recuerda en la sesión (id de usuario + versión) para no consultar la base
 * en cada petición. Las rutas del aviso, login, registro y logout quedan fuera (ver Config\Filters).
 */
class ConsentimientoGuard implements FilterInterface
{
    public const LLAVE_SESION  = 'privacidad_ok';
    public const LLAVE_DESTINO = 'privacidad_destino';

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest || ! auth()->loggedIn()) {
            return null;
        }

        $idUsuario = (int) auth()->id();
        $version   = config('Privacidad')->version;
        $marca     = $idUsuario . '|' . $version;
        $sesion    = session();

        if ($sesion->get(self::LLAVE_SESION) === $marca) {
            return null;
        }

        try {
            $modelo = new Modelo_consentimiento();

            // Sin migración no se bloquea el sistema completo (mismo criterio que el resto del proyecto).
            if (! $modelo->tablaExiste() || $modelo->aceptoVersion($idUsuario, $version)) {
                $sesion->set(self::LLAVE_SESION, $marca);

                return null;
            }
        } catch (Throwable $e) {
            log_message('error', 'ConsentimientoGuard: ' . $e->getMessage());

            return null;
        }

        $sesion->remove(self::LLAVE_SESION);
        $mensaje = 'Antes de continuar, lee y acepta el Aviso de Privacidad.';

        if ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['ok' => false, 'mensaje' => $mensaje, 'redirigir' => site_url('privacidad/aceptar')]);
        }

        // Al aceptar se regresa a la página que se pidió (solo GET y solo rutas de este sitio).
        if (strtoupper($request->getMethod()) === 'GET') {
            $sesion->set(self::LLAVE_DESTINO, trim($request->getPath(), '/'));
        }

        return redirect()->to(site_url('privacidad/aceptar'))->with('aviso_privacidad', $mensaje);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
