<?php

namespace App\Models;

use Throwable;

/**
 * Historial de cambios de rol (tabla `rol_bitacora`, migración AddBajaRepartidor).
 *
 * Un renglón por cambio: a qué cuenta, de qué rol a cuál, quién lo hizo, por qué y qué pasó
 * con su ficha de repartidor. Guarda los nombres como texto para que el registro siga
 * siendo legible aunque después se borre o renombre la cuenta.
 */
class Modelo_bitacora_rol extends PdoModelo
{
    protected string $tabla = 'rol_bitacora';

    protected array $camposPermitidos = [
        'id_user', 'cuenta', 'rol_antes', 'rol_despues', 'motivo', 'detalle', 'por_id_user', 'por_usuario',
    ];

    private static ?bool $hayTabla = null;

    public function tablaExiste(): bool
    {
        if (self::$hayTabla === null) {
            try {
                $this->filas('SELECT id FROM rol_bitacora LIMIT 0');
                self::$hayTabla = true;
            } catch (Throwable) {
                self::$hayTabla = false;
            }
        }

        return self::$hayTabla;
    }

    /** Anota un cambio. Si la tabla aún no existe no hace nada (queda el log de archivos). */
    public function registrar(int $idUser, string $cuenta, string $rolAntes, string $rolDespues, ?string $motivo, ?string $detalle, ?int $porIdUser, string $porUsuario): void
    {
        if (! $this->tablaExiste()) {
            return;
        }

        $this->insert([
            'id_user'     => $idUser,
            'cuenta'      => mb_substr($cuenta, 0, 255),
            'rol_antes'   => $rolAntes !== '' ? mb_substr($rolAntes, 0, 100) : null,
            'rol_despues' => mb_substr($rolDespues, 0, 30),
            'motivo'      => $motivo !== null && $motivo !== '' ? mb_substr($motivo, 0, 255) : null,
            'detalle'     => $detalle !== null && $detalle !== '' ? mb_substr($detalle, 0, 255) : null,
            'por_id_user' => $porIdUser,
            'por_usuario' => mb_substr($porUsuario, 0, 255),
        ]);
    }

    /**
     * Últimos cambios, del más reciente al más antiguo.
     *
     * @return list<array<string,mixed>>
     */
    public function recientes(int $limite = 10): array
    {
        if (! $this->tablaExiste()) {
            return [];
        }

        return $this->filas(
            'SELECT * FROM rol_bitacora ORDER BY id DESC LIMIT ' . max(1, min(100, $limite))
        );
    }
}
