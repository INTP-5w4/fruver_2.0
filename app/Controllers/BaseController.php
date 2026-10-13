<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\RedirectResponse;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Grupos con acceso al panel de administración (ver Config\AuthGroups).
     * Los clientes (grupo "user") solo usan la tienda.
     */
    protected const GRUPOS_PERSONAL = ['superadmin', 'admin', 'developer'];

    protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        $this->helpers = ['form', 'url', 'seguridad'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        $this->session = service('session');
    }

    /** ¿La persona con sesión iniciada es personal del negocio (admin/superadmin/developer)? */
    protected function esPersonal(): bool
    {
        $usuario = auth()->user();

        return $usuario !== null && $usuario->inGroup(...self::GRUPOS_PERSONAL);
    }

    /** Texto del buscador (?buscar=...) sin espacios sobrantes y con tope de longitud. */
    protected function textoBusqueda(string $campo = 'buscar'): string
    {
        $texto = trim((string) $this->request->getGet($campo));

        return mb_substr($texto, 0, 100);
    }

    /** Valor de un campo POST como texto recortado (nunca null). */
    protected function post(string $campo): string
    {
        return trim((string) $this->request->getPost($campo));
    }

    /**
     * Destino tras guardar: las tarjetas del dashboard mandan origen=main_page
     * y regresan a "/". Solo se compara contra un valor fijo (sin redirecciones abiertas).
     */
    protected function destino(string $porDefecto): string
    {
        return $this->request->getPost('origen') === 'main_page' ? '/' : $porDefecto;
    }

    /** Primer mensaje de error de la última validación. */
    protected function primerError(): string
    {
        $errores = $this->validator ? $this->validator->getErrors() : [];

        return $errores !== [] ? (string) reset($errores) : 'Datos no válidos.';
    }

    /** Regresa al formulario conservando lo escrito y mostrando el primer error de validación. */
    protected function volverConErrores(string $clave = 'error'): RedirectResponse
    {
        return redirect()->back()->withInput()->with($clave, $this->primerError());
    }

    /** Convierte "2026-10-10T14:30" (datetime-local) o "2026-10-10 14:30:00" a "Y-m-d H:i:s"; null si no es válida. */
    protected function fechaHoraSql(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        $ts = strtotime(str_replace('T', ' ', $valor));

        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }
}
