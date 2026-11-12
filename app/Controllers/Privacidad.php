<?php

namespace App\Controllers;

use App\Filters\ConsentimientoGuard;
use App\Libraries\Privacidad\FormularioConsentimiento;
use App\Models\Modelo_cliente;
use App\Models\Modelo_consentimiento;
use App\Models\PdoModelo;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Aviso de Privacidad (LFPDPPP) y consentimiento de finalidades voluntarias.
 *
 *  - aviso / consentimiento ... públicas: texto íntegro de ambos documentos.
 *  - aceptar .................. con sesión: la cuenta acepta la versión vigente del aviso
 *                               (a esta pantalla manda el filtro "consentimiento").
 *  - preferencias ............. con sesión, cualquier rol: ver y cambiar V1–V4, canales e
 *                               historial; retirar todos los consentimientos.
 *
 * Cada cambio se guarda como una fila nueva en `consentimiento` (fecha, versión, IP y
 * navegador); nunca se edita una constancia anterior.
 */
class Privacidad extends BaseController
{
    // ─────────────────────────────────────────────────────────────
    // DOCUMENTOS (públicos)
    // ─────────────────────────────────────────────────────────────
    // ?modal=1: solo el documento, sin encabezado ni pie, para mostrarlo dentro de la
    // ventana modal que abre public/js/aviso_modal.js desde cualquier enlace al aviso.
    public function aviso()
    {
        return view('privacidad/aviso', [
            'cfg'    => config('Privacidad'),
            'volver' => $this->inicio(),
            'modal'  => $this->request->getGet('modal') === '1',
        ]);
    }

    public function consentimiento()
    {
        return view('privacidad/consentimiento', [
            'cfg'    => config('Privacidad'),
            'volver' => $this->inicio(),
            'modal'  => $this->request->getGet('modal') === '1',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // ACEPTAR EL AVISO VIGENTE
    // ─────────────────────────────────────────────────────────────
    public function aceptar()
    {
        $cfg     = config('Privacidad');
        $usuario = auth()->user();
        $modelo  = new Modelo_consentimiento();

        if (! $modelo->tablaExiste()) {
            return redirect()->to(site_url($this->inicio()))
                ->with('error', mensaje_tecnico('Falta ejecutar la migración de privacidad (php spark migrate).', 'Esta sección aún no está activada. Avisa al administrador del sistema.'));
        }

        if ($modelo->aceptoVersion((int) $usuario->id, $cfg->version)) {
            return redirect()->to(site_url($this->inicio()));
        }

        $vigente = $modelo->vigente((int) $usuario->id);

        return view('privacidad/aceptar', [
            'cfg'            => $cfg,
            'sinVolver'      => true,
            'esActualizacion' => $vigente !== null,
            'versionAnterior' => $vigente['version_aviso'] ?? null,
            'correo'         => (string) $usuario->email,
            'valores'        => $this->valores($cfg, $vigente),
            'errores'        => session()->getFlashdata('errores') ?? [],
        ]);
    }

    public function guardaAceptacion(): RedirectResponse
    {
        $cfg     = config('Privacidad');
        $usuario = auth()->user();
        $lectura = FormularioConsentimiento::leer($this->request->getPost() ?? [], $cfg, true);

        if ($lectura['errores'] !== []) {
            return redirect()->to(site_url('privacidad/aceptar'))
                ->with('errores', $lectura['errores'])
                ->with('enviado', $this->enviado());
        }

        if (! $this->guardar($lectura['datos'], 'aceptacion')) {
            return redirect()->to(site_url('privacidad/aceptar'))
                ->with('error', 'No se pudo guardar tu aceptación. Intenta de nuevo.')
                ->with('enviado', $this->enviado());
        }

        session()->set(ConsentimientoGuard::LLAVE_SESION, $usuario->id . '|' . $cfg->version);
        $destino = session()->get(ConsentimientoGuard::LLAVE_DESTINO);
        session()->remove(ConsentimientoGuard::LLAVE_DESTINO);

        $destino = is_string($destino) && $destino !== '' && ! str_starts_with($destino, 'privacidad')
            ? $destino
            : $this->inicio();

        return redirect()->to(site_url($destino))->with('mensaje', 'Gracias. Registramos tu aceptación del Aviso de Privacidad.');
    }

    // ─────────────────────────────────────────────────────────────
    // MIS PREFERENCIAS DE PRIVACIDAD (cualquier rol)
    // ─────────────────────────────────────────────────────────────
    public function preferencias()
    {
        $cfg     = config('Privacidad');
        $usuario = auth()->user();
        $modelo  = new Modelo_consentimiento();

        if (! $modelo->tablaExiste()) {
            return redirect()->to(site_url($this->inicio()))
                ->with('error', mensaje_tecnico('Falta ejecutar la migración de privacidad (php spark migrate).', 'Esta sección aún no está activada. Avisa al administrador del sistema.'));
        }

        $vigente = $modelo->vigente((int) $usuario->id);
        if ($vigente === null) {
            return redirect()->to(site_url('privacidad/aceptar'));
        }

        return view('privacidad/preferencias', [
            'cfg'        => $cfg,
            'volver'     => $this->inicio(),
            'vigente'    => $vigente,
            'historial'  => $modelo->historial((int) $usuario->id),
            'correo'     => (string) $usuario->email,
            'valores'    => $this->valores($cfg, $vigente),
            'errores'    => session()->getFlashdata('errores') ?? [],
            'pendientes' => $this->esPersonal() ? $cfg->pendientes() : [],
        ]);
    }

    public function guardaPreferencias(): RedirectResponse
    {
        $cfg     = config('Privacidad');
        $usuario = auth()->user();
        $vigente = (new Modelo_consentimiento())->vigente((int) $usuario->id);

        if ($vigente === null) {
            return redirect()->to(site_url('privacidad/aceptar'));
        }

        $lectura = FormularioConsentimiento::leer($this->request->getPost() ?? [], $cfg, false);

        if ($lectura['errores'] !== []) {
            return redirect()->to(site_url('privacidad/preferencias'))
                ->with('errores', $lectura['errores'])
                ->with('enviado', $this->enviado());
        }

        // La declaración del aviso no se toca desde aquí: se conserva la ya aceptada.
        $datos = [
            ...$lectura['datos'],
            'acepta_aviso' => (int) $vigente['acepta_aviso'],
            'mayor_edad'   => (int) $vigente['mayor_edad'],
        ];

        if (! $this->hayCambios($vigente, $datos)) {
            return redirect()->to(site_url('privacidad/preferencias'))->with('mensaje', 'No hubo cambios en tus preferencias.');
        }

        if (! $this->guardar($datos, 'preferencias')) {
            return redirect()->to(site_url('privacidad/preferencias'))
                ->with('error', 'No se pudieron guardar tus preferencias. Intenta de nuevo.')
                ->with('enviado', $this->enviado());
        }

        return redirect()->to(site_url('privacidad/preferencias'))->with('mensaje', 'Tus preferencias de privacidad se guardaron.');
    }

    /** Retira de una vez el consentimiento de todas las finalidades voluntarias. */
    public function revocar(): RedirectResponse
    {
        $cfg     = config('Privacidad');
        $usuario = auth()->user();
        $vigente = (new Modelo_consentimiento())->vigente((int) $usuario->id);

        if ($vigente === null) {
            return redirect()->to(site_url('privacidad/aceptar'));
        }

        $datos = [
            'acepta_aviso'      => (int) $vigente['acepta_aviso'],
            'mayor_edad'        => (int) $vigente['mayor_edad'],
            'telefono_contacto' => null,
        ];
        foreach ([...array_keys($cfg->finalidades), ...array_keys($cfg->canales)] as $campo) {
            $datos[$campo] = 0;
        }

        if (! $this->hayCambios($vigente, $datos)) {
            return redirect()->to(site_url('privacidad/preferencias'))->with('mensaje', 'No tenías ninguna finalidad voluntaria autorizada.');
        }

        if (! $this->guardar($datos, 'revocacion')) {
            return redirect()->to(site_url('privacidad/preferencias'))->with('error', 'No se pudo registrar la revocación. Intenta de nuevo.');
        }

        return redirect()->to(site_url('privacidad/preferencias'))
            ->with('mensaje', 'Retiramos tu consentimiento para todas las finalidades voluntarias. Ya no recibirás promociones, recordatorios, encuestas ni recomendaciones personalizadas.');
    }

    // ─────────────────────────────────────────────────────────────
    // APOYO
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, int|string|null> $datos */
    private function guardar(array $datos, string $origen): bool
    {
        $usuario = auth()->user();

        try {
            (new Modelo_consentimiento())->registrar(
                (int) $usuario->id,
                $datos,
                $origen,
                Modelo_consentimiento::tipoRelacion($usuario),
                config('Privacidad')->version,
                $this->request->getIPAddress(),
                (string) $this->request->getUserAgent()->getAgentString()
            );

            return true;
        } catch (Throwable $e) {
            PdoModelo::mensajeSeguro($e); // deja el detalle en el log

            return false;
        }
    }

    /** @param array<string, int|string|null> $nuevos */
    private function hayCambios(array $vigente, array $nuevos): bool
    {
        foreach ($nuevos as $campo => $valor) {
            $antes = $vigente[$campo] ?? null;

            if ($campo === 'telefono_contacto') {
                if ((string) $antes !== (string) $valor) {
                    return true;
                }
            } elseif ((int) $antes !== (int) $valor) {
                return true;
            }
        }

        return (string) $vigente['version_aviso'] !== config('Privacidad')->version;
    }

    /**
     * Valores para el formulario: lo enviado (si hubo error), si no el estado vigente.
     * Si no hay teléfono guardado se propone el del perfil de cliente.
     *
     * @return array<string, bool|string>
     */
    private function valores(\Config\Privacidad $cfg, ?array $vigente): array
    {
        $enviado = session()->getFlashdata('enviado');
        $valores = FormularioConsentimiento::valoresFormulario($cfg, $vigente, is_array($enviado) ? $enviado : null);

        if ($valores['tel_contacto'] === '') {
            try {
                $perfil = (new Modelo_cliente())->buscarPorUsuario((int) auth()->id());
                $valores['tel_contacto'] = (string) ($perfil['telefono'] ?? '');
            } catch (Throwable) {
                // Sin perfil de cliente el campo queda vacío.
            }
        }

        return $valores;
    }

    /** Solo los campos del formato (cadenas), para volver a llenar el formulario tras un error. */
    private function enviado(): array
    {
        $campos = ['aviso_leido', 'mayor_edad', 'tel_contacto', 'v1', 'v2', 'v3', 'v4', 'canal_telefono', 'canal_mensajeria', 'canal_correo'];
        $salida = [];

        foreach ($campos as $campo) {
            $valor = $this->request->getPost($campo);
            if (is_string($valor)) {
                $salida[$campo] = mb_substr($valor, 0, 20);
            }
        }

        return $salida;
    }

    /** Pantalla de inicio de cada rol. */
    private function inicio(): string
    {
        $usuario = auth()->user();

        if ($this->esPersonal()) {
            return '/';
        }

        if ($usuario !== null && $usuario->inGroup('repartidor')) {
            return 'repartidor/entregas';
        }

        return 'main_page_4';
    }
}
