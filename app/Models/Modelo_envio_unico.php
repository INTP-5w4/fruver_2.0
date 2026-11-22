<?php

namespace App\Models;

use PDOException;
use Throwable;

/**
 * Envíos únicos (idempotencia) — tabla `envio_unico`, migración CreateEnvioUnico.
 *
 * Cada formulario que crea un pedido manda un token_envio aleatorio (helper
 * campo_envio_unico()). unaVez() lo registra en la MISMA transacción que crea el
 * pedido: si el mismo token vuelve a llegar (reenvío tras un corte de red, doble clic,
 * «Reenviar formulario» del navegador) la llave primaria lo rechaza y se lanza
 * EnvioRepetido con el id del pedido que ya existía.
 *
 * Si la primera petición sigue en curso, la segunda espera a que termine (bloqueo de la
 * llave en InnoDB): si la primera se guardó, la segunda es un repetido; si falló y se
 * deshizo, el token queda libre y la segunda se procesa normalmente.
 */
class Modelo_envio_unico extends PdoModelo
{
    protected string $tabla         = 'envio_unico';
    protected string $llavePrimaria = 'token';

    /** Días que se conserva un token (bastan para cualquier reenvío). */
    public const DIAS_CONSERVACION = 7;

    private static ?bool $hayTabla = null;

    /** Token bien formado (32 hexadecimales) o null. */
    public static function tokenValido(mixed $token): ?string
    {
        return is_string($token) && preg_match('/^[a-f0-9]{32}$/', $token) === 1 ? $token : null;
    }

    public static function nuevoToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function tablaExiste(): bool
    {
        if (self::$hayTabla === null) {
            try {
                $this->filas('SELECT token FROM envio_unico LIMIT 0');
                self::$hayTabla = true;
            } catch (Throwable) {
                self::$hayTabla = false;
            }
        }

        return self::$hayTabla;
    }

    /**
     * Ejecuta $crear una sola vez por token. $crear devuelve el id del registro creado.
     * Debe llamarse como PRIMER paso dentro de la transacción que crea el registro.
     *
     * Sin token (formulario antiguo) o sin la tabla (migración pendiente) se comporta
     * como antes: crea siempre.
     *
     * @param callable():int $crear
     *
     * @throws EnvioRepetido
     */
    public function unaVez(?string $token, string $recurso, callable $crear): int
    {
        if ($token === null || ! $this->tablaExiste()) {
            return $crear();
        }

        try {
            $this->afectar(
                'INSERT INTO envio_unico (token, recurso, id_user) VALUES (?, ?, @fruver_id_user)',
                [$token, $recurso]
            );
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }

            $previo = $this->valor('SELECT id_recurso FROM envio_unico WHERE token = ?', [$token]);

            throw new EnvioRepetido($recurso, $previo !== null ? (int) $previo : null);
        }

        $id = $crear();
        $this->afectar('UPDATE envio_unico SET id_recurso = ? WHERE token = ?', [$id, $token]);

        return $id;
    }

    /** Borra los tokens de más de DIAS_CONSERVACION días. Devuelve cuántos borró. */
    public function depurar(int $dias = self::DIAS_CONSERVACION): int
    {
        if (! $this->tablaExiste()) {
            return 0;
        }

        return $this->afectar('DELETE FROM envio_unico WHERE fecha < NOW() - INTERVAL ? DAY', [max(1, $dias)]);
    }
}
