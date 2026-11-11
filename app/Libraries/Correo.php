<?php

namespace App\Libraries;

use Throwable;

/**
 * Envío de correo con la configuración de Config\Email (se llena desde .env, ver docs/PRIVACIDAD.md).
 *
 * Si no hay servidor configurado, configurado() es false y enviar() no hace nada: las
 * funciones que dependen del correo (recuperar acceso, confirmación de pedido,
 * comunicaciones por correo) se ocultan o se omiten en lugar de fallar.
 */
final class Correo
{
    public static function configurado(): bool
    {
        $cfg = config('Email');

        if (trim($cfg->fromEmail) === '' || ! filter_var($cfg->fromEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return match ($cfg->protocol) {
            'smtp'     => trim($cfg->SMTPHost) !== '',
            'sendmail' => trim($cfg->mailPath) !== '',
            default    => true, // mail(): usa la configuración de PHP
        };
    }

    /**
     * Envía un correo HTML a UN destinatario (nunca se exponen direcciones de otros).
     * Devuelve false (y lo deja en el log) si no se pudo enviar.
     */
    public static function enviar(string $para, string $asunto, string $html): bool
    {
        if (! self::configurado() || ! filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $email = service('email', null, false); // instancia nueva por envío
            $email->setMailType('html');
            $email->setFrom(config('Email')->fromEmail, config('Email')->fromName ?: 'FRUVER');
            $email->setTo($para);
            $email->setSubject($asunto);
            $email->setMessage($html);

            if ($email->send(false)) {
                return true;
            }

            // Sin la dirección del destinatario: el log no debe acumular datos personales.
            log_message('error', 'Correo no enviado: ' . mb_substr(strip_tags($email->printDebugger([])), 0, 500));
        } catch (Throwable $e) {
            log_message('error', 'Correo no enviado: ' . $e->getMessage());
        }

        return false;
    }
}
