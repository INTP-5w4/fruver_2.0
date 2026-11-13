<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Catálogo de roles (grupos de Shield) y su ENUM en la base de datos.
 *
 * La fuente de verdad es Config\AuthGroups::$groups. La columna auth_groups_users.group es
 * un ENUM con esas mismas claves, así que en phpMyAdmin solo se puede elegir un rol que
 * existe (antes era texto libre y alguien escribió "Super Admin", que Shield no reconoce:
 * esa cuenta se quedaba sin permisos).
 *
 * Lo usan: la migración RolesComoEnum, el comando `php spark roles:sincronizar` y la
 * pestaña Roles (que avisa si la configuración y el ENUM no coinciden).
 */
final class Roles
{
    public const TABLA   = 'auth_groups_users';
    public const COLUMNA = 'group';

    /** Orden en que se muestran (de más a menos permisos). */
    private const ORDEN = ['superadmin', 'admin', 'developer', 'repartidor', 'user'];

    /** Nombres viejos (títulos en inglés de Shield) que pudieron escribirse a mano. */
    private const ALIAS = [
        'superadmin'      => 'superadmin',
        'superadministrador' => 'superadmin',
        'admin'           => 'admin',
        'administrador'   => 'admin',
        'developer'       => 'developer',
        'desarrollador'   => 'developer',
        'user'            => 'user',
        'usuario'         => 'user',
        'cliente'         => 'user',
        'repartidor'      => 'repartidor',
    ];

    /**
     * Roles configurados: clave => [title, description], en el orden de ORDEN.
     *
     * @return array<string, array{title:string, description:string}>
     */
    public static function catalogo(): array
    {
        $grupos = config('AuthGroups')->groups;
        $orden  = array_flip(self::ORDEN);

        uksort($grupos, static fn (string $a, string $b): int => ($orden[$a] ?? 99) <=> ($orden[$b] ?? 99) ?: strcmp($a, $b));

        return $grupos;
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::catalogo());
    }

    public static function existe(string $clave): bool
    {
        return array_key_exists($clave, config('AuthGroups')->groups);
    }

    public static function titulo(string $clave): string
    {
        return (string) (config('AuthGroups')->groups[$clave]['title'] ?? $clave);
    }

    /**
     * Convierte lo que haya en la columna (clave, título o nombre viejo, con cualquier
     * mayúscula o espacio) a una clave válida; null si no se reconoce.
     */
    public static function normalizar(string $valor): ?string
    {
        $plano = self::plano($valor);

        foreach (self::catalogo() as $clave => $g) {
            if ($plano === self::plano($clave) || $plano === self::plano((string) ($g['title'] ?? ''))) {
                return $clave;
            }
        }

        $alias = self::ALIAS[$plano] ?? null;

        return $alias !== null && self::existe($alias) ? $alias : null;
    }

    /**
     * Valores del ENUM actual de la columna, o null si la columna todavía es texto.
     *
     * @return list<string>|null
     */
    public static function enumActual(BaseConnection $db): ?array
    {
        $tipo = $db->query(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [self::TABLA, self::COLUMNA]
        )->getRowArray()['COLUMN_TYPE'] ?? '';

        if (! str_starts_with(strtolower((string) $tipo), 'enum(')) {
            return null;
        }

        preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'/", (string) $tipo, $m);

        return array_map(static fn (string $v): string => str_replace("''", "'", $v), $m[1]);
    }

    /** ¿El ENUM de la base coincide con los roles configurados? */
    public static function enumAlDia(BaseConnection $db): bool
    {
        $actual = self::enumActual($db);
        if ($actual === null) {
            return false;
        }

        $config = self::claves();
        sort($actual);
        sort($config);

        return $actual === $config;
    }

    /**
     * Corrige los valores que no son un rol válido y convierte la columna en ENUM con los
     * roles configurados. Los que no se reconocen pasan al rol por omisión (cliente).
     *
     * @return list<string> cambios hechos, para mostrarlos
     */
    public static function sincronizarEnum(BaseConnection $db): array
    {
        $cambios = [];
        $porDefecto = config('AuthGroups')->defaultGroup;

        // Mientras se corrigen los valores, la columna vuelve a ser texto (por si el ENUM
        // anterior no incluye un rol nuevo de la configuración).
        $db->query('ALTER TABLE `' . self::TABLA . '` MODIFY `' . self::COLUMNA . '` VARCHAR(255) NOT NULL');

        foreach ($db->query('SELECT id, user_id, `' . self::COLUMNA . '` AS g FROM `' . self::TABLA . '`')->getResultArray() as $fila) {
            $valor = (string) $fila['g'];
            $clave = self::normalizar($valor);

            if ($clave === $valor) {
                continue;
            }

            $clave ??= $porDefecto;
            $db->query('UPDATE `' . self::TABLA . '` SET `' . self::COLUMNA . '` = ? WHERE id = ?', [$clave, (int) $fila['id']]);
            $cambios[] = 'Cuenta ' . (int) $fila['user_id'] . ': "' . $valor . '" → ' . $clave;
        }

        // Un mismo rol repetido en una cuenta (puede salir al corregir): se deja uno.
        $borrados = $db->query(
            'DELETE g1 FROM `' . self::TABLA . '` g1
               JOIN `' . self::TABLA . '` g2
                 ON g2.user_id = g1.user_id AND g2.`' . self::COLUMNA . '` = g1.`' . self::COLUMNA . '` AND g2.id < g1.id'
        );
        if ($borrados && $db->affectedRows() > 0) {
            $cambios[] = 'Roles repetidos eliminados: ' . $db->affectedRows();
        }

        $lista = implode(', ', array_map(static fn (string $c): string => $db->escape($c), self::claves()));
        $db->query(
            'ALTER TABLE `' . self::TABLA . '` MODIFY `' . self::COLUMNA . '` ENUM(' . $lista . ') NOT NULL DEFAULT ' . $db->escape($porDefecto)
        );

        return $cambios;
    }

    /** Minúsculas, sin acentos, espacios, guiones ni guiones bajos. */
    private static function plano(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return preg_replace('/[\s_\-.]+/', '', $texto) ?? $texto;
    }
}
