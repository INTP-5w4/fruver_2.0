<?php

namespace App\Models;

/**
 * Lectura de cuentas y sus roles para la pestaña Roles (solo superadmin).
 *
 * Los cambios de rol NO se hacen aquí sino con la API de Shield ($user->syncGroups()),
 * que valida el rol contra Config\AuthGroups y mantiene su caché al día.
 */
class Modelo_roles extends PdoModelo
{
    protected string $tabla         = 'users';
    protected string $llavePrimaria = 'id';

    /**
     * Cuentas con su correo, roles y ficha de repartidor. Filtro opcional por texto y por rol.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(string $buscar = '', string $rol = '', int $porPagina = 25): array
    {
        $where  = ['u.deleted_at IS NULL'];
        $params = [];
        $buscar = trim($buscar);

        if ($buscar !== '') {
            $like  = $this->patronLike($buscar);
            $parte = ['u.username LIKE ?', 'ai.secret LIKE ?'];
            array_push($params, $like, $like);

            if (ctype_digit($buscar)) {
                $parte[]  = 'u.id = ?';
                $params[] = (int) $buscar;
            }

            $where[] = '(' . implode(' OR ', $parte) . ')';
        }

        if ($rol !== '') {
            $where[]  = 'EXISTS (SELECT 1 FROM auth_groups_users gx WHERE gx.user_id = u.id AND gx.`group` = ?)';
            $params[] = $rol;
        }

        return $this->paginarSql(
            "SELECT u.id, u.username, u.active, u.created_at, u.last_active,
                    ai.secret AS correo,
                    (SELECT GROUP_CONCAT(g.`group` ORDER BY g.id SEPARATOR ',')
                       FROM auth_groups_users g WHERE g.user_id = u.id) AS roles,
                    r.id AS id_repartidor, r.nombre AS rep_nombre, r.ape_pat AS rep_ape_pat,
                    r.telefono AS rep_telefono, r.direccion AS rep_direccion
               FROM users u
          LEFT JOIN auth_identities ai ON ai.user_id = u.id AND ai.type = 'email_password'
          LEFT JOIN repartidor r ON r.id_user = u.id
              WHERE " . implode(' AND ', $where) . '
           ORDER BY u.id DESC',
            $params,
            $porPagina
        );
    }

    /**
     * Cuántas cuentas activas tiene cada rol.
     *
     * @return array<string,int>
     */
    public function conteo(): array
    {
        $filas = $this->filas(
            'SELECT g.`group` AS rol, COUNT(DISTINCT g.user_id) AS n
               FROM auth_groups_users g
               JOIN users u ON u.id = g.user_id AND u.deleted_at IS NULL
           GROUP BY g.`group`'
        );

        return array_map('intval', array_column($filas, 'n', 'rol'));
    }

    /** Cuentas (no borradas) con el rol dado. */
    public function cuantosCon(string $rol): int
    {
        return (int) $this->valor(
            'SELECT COUNT(DISTINCT g.user_id) FROM auth_groups_users g
               JOIN users u ON u.id = g.user_id AND u.deleted_at IS NULL
              WHERE g.`group` = ?',
            [$rol]
        );
    }
}
