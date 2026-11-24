<?php

namespace App\Controllers;

use App\Models\Modelo_repartidor;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\IncomingRequest;
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
        $this->helpers = ['form', 'url', 'seguridad', 'rutas', 'ui'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        $this->session = service('session');

        $this->normalizarTelefonos();
    }

    /** Campos de teléfono que llegan de los formularios (clientes, repartidores, tienda). */
    private const CAMPOS_TELEFONO = ['tel', 'telefono', 'tel_contacto'];

    /**
     * No castigar a la persona por el formato: «222 123-45-67», «(222) 123 4567» o
     * «+52 222 123 4567» se guardan como «2221234567». Solo se quitan espacios, guiones,
     * puntos, paréntesis y la lada +52; si el texto trae letras se deja igual para que la
     * validación diga qué está mal.
     */
    private function normalizarTelefonos(): void
    {
        if (! $this->request instanceof IncomingRequest || strtoupper($this->request->getMethod()) !== 'POST') {
            return;
        }

        $post = $this->request->getPost();
        if (! is_array($post)) {
            return;
        }

        $cambio = false;
        foreach (self::CAMPOS_TELEFONO as $campo) {
            $valor = $post[$campo] ?? null;
            if (! is_string($valor) || preg_match('/^[\d\s\-.()+]+$/', $valor) !== 1 || preg_match('/\D/', $valor) !== 1) {
                continue;
            }
            $digitos = preg_replace('/\D+/', '', $valor);
            if (str_starts_with(ltrim($valor), '+52') && strlen($digitos) === 12) {
                $digitos = substr($digitos, 2);
            }
            $post[$campo] = $digitos;
            $cambio       = true;
        }

        if ($cambio) {
            $this->request->setGlobal('post', $post);
        }
    }

    /** ¿La persona con sesión iniciada es personal del negocio (admin/superadmin/developer)? */
    /**
     * Repartidores para listas y selectores. Antes de leerlos da de alta como repartidor a
     * las cuentas nuevas del grupo "repartidor" (Modelo_repartidor::sincronizarCuentas).
     *
     * @return list<array<string,mixed>>
     */
    /**
     * Repartidores para los selectores. Por omisión solo los activos: a uno dado de baja ya no
     * se le asigna nada. Con $incluirBajas también vienen ellos (para mostrar el nombre en el
     * historial); la vista los marca y los oculta del selector.
     */
    protected function repartidores(bool $incluirBajas = false): array
    {
        $m = new Modelo_repartidor();

        try {
            $m->sincronizarCuentas();
        } catch (\Throwable $e) {
            log_message('error', 'No se pudieron sincronizar las cuentas de repartidor: ' . $e->getMessage());
        }

        return $incluirBajas ? $m->findAll() : $m->activos();
    }

    protected function esPersonal(): bool
    {
        $usuario = auth()->user();

        return $usuario !== null && $usuario->inGroup(...self::GRUPOS_PERSONAL);
    }

    /**
     * Texto del buscador (?buscar=...) sin espacios sobrantes y con tope de longitud.
     * Si llega un arreglo (?buscar[]=x) u otro tipo, se trata como búsqueda vacía
     * (convertirlo a string provocaba un error 500).
     */
    protected function textoBusqueda(string $campo = 'buscar'): string
    {
        $valor = $this->request->getGet($campo);

        if (! is_string($valor)) {
            return '';
        }

        return mb_substr(trim($valor), 0, 100);
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
