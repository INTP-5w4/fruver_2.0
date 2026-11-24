<?php

declare(strict_types=1);

namespace App\Validation;

use CodeIgniter\Shield\Authentication\Passwords\BaseValidator;
use CodeIgniter\Shield\Authentication\Passwords\ValidatorInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Result;

/**
 * Composición mínima de la contraseña (Config\Auth::$passwordValidators).
 *
 * Además del largo mínimo que revisa CompositionValidator de Shield, exige los cuatro
 * tipos de carácter: minúscula, mayúscula, número y símbolo. Es una de las medidas de
 * seguridad técnicas que la LFPDPPP pide para las cuentas que acceden a datos personales.
 *
 * Las mismas reglas las muestra el formulario de registro mientras se escribe
 * (public/js/reglas_campo.js, atributo data-contrasena-segura). El servidor manda.
 */
class ComposicionContrasena extends BaseValidator implements ValidatorInterface
{
    /**
     * Tipos de carácter obligatorios: patrón (Unicode) => texto para el usuario.
     * "Símbolo" es cualquier cosa que no sea letra ni número (incluye el espacio).
     */
    public const REGLAS = [
        '/\p{Ll}/u'         => 'una letra minúscula',
        '/\p{Lu}/u'         => 'una letra mayúscula',
        '/\p{Nd}/u'         => 'un número',
        '/[^\p{L}\p{Nd}]/u' => 'un símbolo (por ejemplo ! # $ % & * ? -)',
    ];

    public function check(string $password, ?User $user = null): Result
    {
        $faltan = self::faltantes($password);

        if ($faltan === []) {
            return new Result(['success' => true]);
        }

        return new Result([
            'success'   => false,
            'reason'    => lang('Auth.errorPasswordComposicion', [self::enumerar($faltan)]),
            'extraInfo' => lang('Auth.suggestPasswordComposicion'),
        ]);
    }

    /**
     * Tipos de carácter que le faltan a la contraseña.
     *
     * @return list<string>
     */
    public static function faltantes(string $password): array
    {
        $faltan = [];

        foreach (self::REGLAS as $patron => $texto) {
            if (preg_match($patron, $password) !== 1) {
                $faltan[] = $texto;
            }
        }

        return $faltan;
    }

    /** ["a", "b", "c"] → "a, b y c" */
    private static function enumerar(array $partes): string
    {
        $ultimo = array_pop($partes);

        return $partes === [] ? (string) $ultimo : implode(', ', $partes) . ' y ' . $ultimo;
    }
}
