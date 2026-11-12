<?php

namespace App\Libraries\Privacidad;

use Config\Privacidad;

/**
 * Lee y valida el formato de consentimiento (sección 9 del Aviso de Consentimiento)
 * que llega por POST desde el registro, la aceptación del aviso o "Mis preferencias".
 *
 * Reglas:
 *  - Las finalidades voluntarias (V1–V4) solo cuentan si la casilla llegó marcada:
 *    nada viene marcado de antemano y no marcar equivale a negarse.
 *  - Los canales y el teléfono de contacto solo se guardan si se aceptó alguna finalidad
 *    que los usa (V1–V3). Si no, se descartan aunque lleguen (minimización de datos).
 *  - Si se pide la declaración (registro o aceptación del aviso), "leí el aviso" y
 *    "soy mayor de edad" son obligatorias.
 */
final class FormularioConsentimiento
{
    /**
     * @param array<string,mixed> $post
     *
     * @return array{datos: array<string, int|string|null>, errores: array<string,string>}
     */
    public static function leer(array $post, Privacidad $cfg, bool $pideDeclaracion): array
    {
        $errores = [];
        $marcada = static fn (string $campo): bool => isset($post[$campo]) && $post[$campo] === '1';

        $datos = [
            'acepta_aviso' => $marcada('aviso_leido') ? 1 : 0,
            'mayor_edad'   => $marcada('mayor_edad') ? 1 : 0,
        ];

        if ($pideDeclaracion && $datos['acepta_aviso'] === 0) {
            $errores['aviso_leido'] = 'Para continuar debes declarar que leíste y comprendiste el Aviso de Privacidad.';
        }
        if ($pideDeclaracion && $datos['mayor_edad'] === 0) {
            $errores['mayor_edad'] = 'Debes declarar que eres mayor de edad y que tus datos son verdaderos. FRUVER no está dirigido a menores de edad.';
        }

        $usaCanal = false;
        foreach ($cfg->finalidades as $clave => $finalidad) {
            $datos[$clave] = $marcada($clave) ? 1 : 0;
            $usaCanal      = $usaCanal || ($datos[$clave] === 1 && $finalidad['usaCanal']);
        }

        foreach (array_keys($cfg->canales) as $canal) {
            $datos[$canal] = $usaCanal && $marcada($canal) ? 1 : 0;
        }
        $datos['telefono_contacto'] = null;

        if (! $usaCanal) {
            return ['datos' => $datos, 'errores' => $errores];
        }

        $algunCanal = false;
        foreach (array_keys($cfg->canales) as $canal) {
            $algunCanal = $algunCanal || $datos[$canal] === 1;
        }
        if (! $algunCanal) {
            $errores['canales'] = 'Elige al menos un canal para recibir las comunicaciones que autorizaste.';
        }

        if ($datos['canal_telefono'] === 1 || $datos['canal_mensajeria'] === 1) {
            $telefono = is_string($post['tel_contacto'] ?? null) ? preg_replace('/\D+/', '', $post['tel_contacto']) : '';

            if (preg_match('/^\d{10}$/', (string) $telefono) === 1) {
                $datos['telefono_contacto'] = $telefono;
            } else {
                $errores['tel_contacto'] = 'Escribe un teléfono de 10 dígitos para recibir llamadas o mensajes.';
            }
        }

        return ['datos' => $datos, 'errores' => $errores];
    }

    /**
     * Valores para volver a mostrar el formulario: lo que se envió (si hubo error) o el
     * estado vigente de la cuenta.
     *
     * @param array<string,mixed>|null $vigente fila más reciente de `consentimiento`
     * @param array<string,mixed>|null $enviado POST anterior (old input)
     *
     * @return array<string, bool|string>
     */
    public static function valoresFormulario(Privacidad $cfg, ?array $vigente, ?array $enviado): array
    {
        $campos  = [...array_keys($cfg->finalidades), ...array_keys($cfg->canales)];
        $valores = [];

        foreach ($campos as $campo) {
            $valores[$campo] = $enviado !== null
                ? ($enviado[$campo] ?? null) === '1'
                : (int) ($vigente[$campo] ?? 0) === 1;
        }

        // Canal por omisión: WhatsApp, mientras la persona no haya elegido ninguno. Solo cuenta
        // si además acepta alguna finalidad (esas casillas nunca vienen marcadas).
        $algunCanal = false;
        foreach (array_keys($cfg->canales) as $canal) {
            $algunCanal = $algunCanal || $valores[$canal];
        }
        if ($enviado === null && ! $algunCanal && isset($valores['canal_mensajeria'])) {
            $valores['canal_mensajeria'] = true;
        }

        $tel = $enviado !== null ? ($enviado['tel_contacto'] ?? '') : ($vigente['telefono_contacto'] ?? '');
        $valores['tel_contacto'] = is_string($tel) ? $tel : '';

        $valores['aviso_leido'] = $enviado !== null && ($enviado['aviso_leido'] ?? null) === '1';
        $valores['mayor_edad']  = $enviado !== null && ($enviado['mayor_edad'] ?? null) === '1';

        return $valores;
    }
}
