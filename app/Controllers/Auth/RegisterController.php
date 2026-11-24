<?php

namespace App\Controllers\Auth;

use App\Libraries\Privacidad\FormularioConsentimiento;
use App\Models\Modelo_consentimiento;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Controllers\RegisterController as RegistroShield;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/**
 * Registro de Shield + Aviso de Privacidad (LFPDPPP).
 *
 * Antes de crear la cuenta:
 *  - se exige la declaración "leí el aviso" y "soy mayor de edad" (validada aquí, en el
 *    servidor; el "required" del formulario solo es ayuda visual);
 *  - se leen las finalidades voluntarias V1–V4, que van desmarcadas por defecto.
 *
 * La constancia se guarda en el evento "register" de Shield, que se dispara justo después
 * de crear al usuario, así queda ligada al id de la cuenta nueva. Si la validación de Shield
 * falla (correo repetido, contraseña débil...) el evento no ocurre y no se guarda nada.
 */
class RegisterController extends RegistroShield
{
    public function registerAction(): RedirectResponse
    {
        // Sesión iniciada o registro deshabilitado: Shield responde como siempre.
        if (auth()->loggedIn() || ! setting('Auth.allowRegistration')) {
            return parent::registerAction();
        }

        $cfg     = config('Privacidad');
        $lectura = FormularioConsentimiento::leer($this->request->getPost() ?? [], $cfg, true);

        if ($lectura['errores'] !== []) {
            return redirect()->back()->withInput()->with('errors', $lectura['errores']);
        }

        $eleccion  = $lectura['datos'];
        $ip        = $this->request->getIPAddress();
        $userAgent = (string) $this->request->getUserAgent()->getAgentString();

        Events::on('register', static function (User $usuario) use ($eleccion, $cfg, $ip, $userAgent): void {
            $modelo = new Modelo_consentimiento();

            if (! $modelo->tablaExiste()) {
                log_message('warning', 'Registro sin constancia de privacidad: falta la tabla consentimiento (php spark migrate).');

                return;
            }

            try {
                $modelo->registrar(
                    (int) $usuario->id,
                    $eleccion,
                    'registro',
                    Modelo_consentimiento::tipoRelacion($usuario),
                    $cfg->version,
                    $ip,
                    $userAgent
                );
            } catch (Throwable $e) {
                // La cuenta ya existe; el filtro "consentimiento" le pedirá aceptar el aviso
                // en su siguiente pantalla, así que no se queda sin constancia.
                log_message('error', 'No se pudo guardar la constancia de privacidad del registro: ' . $e->getMessage());
            }
        });

        return parent::registerAction();
    }
}
