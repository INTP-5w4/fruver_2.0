<?php

namespace App\Libraries;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexión única (singleton) a MySQL usando PDO.
 *
 * Lee las credenciales de Config\Database (grupo "default"), por lo que sigue
 * funcionando con los valores del archivo .env (database.default.*).
 *
 * Decisiones de seguridad:
 *  - EMULATE_PREPARES = false  -> las sentencias preparadas las ejecuta el propio
 *    servidor MySQL: los valores NUNCA se mezclan con el SQL (anti inyección SQL).
 *  - ERRMODE_EXCEPTION         -> cualquier error de SQL lanza PDOException.
 *  - MULTI_STATEMENTS = false  -> impide encadenar "; DROP TABLE ..." en una consulta.
 *  - STRINGIFY_FETCHES = true  -> devuelve los valores como cadenas, igual que antes
 *    (Query Builder de CI + MySQLi), para no alterar las vistas existentes.
 */
final class ConexionPdo
{
    private static ?PDO $instancia = null;

    private function __construct()
    {
    }

    public static function obtener(): PDO
    {
        if (self::$instancia instanceof PDO) {
            return self::$instancia;
        }

        $cfg = config('Database')->{config('Database')->defaultGroup};

        $host    = (string) ($cfg['hostname'] ?? 'localhost');
        $puerto  = (int) ($cfg['port'] ?? 3306);
        $bd      = (string) ($cfg['database'] ?? '');
        // Se limpian por si algún día vienen de una fuente externa (se usan en SET NAMES).
        $charset = preg_replace('/[^a-z0-9_]/i', '', (string) ($cfg['charset'] ?? 'utf8mb4'));
        $collat  = preg_replace('/[^a-z0-9_]/i', '', (string) ($cfg['DBCollat'] ?? 'utf8mb4_general_ci'));

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $puerto, $bd, $charset);

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => true,
        ];

        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $opciones[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
        }

        try {
            $pdo = new PDO($dsn, (string) ($cfg['username'] ?? ''), (string) ($cfg['password'] ?? ''), $opciones);
            $pdo->exec("SET NAMES '{$charset}' COLLATE '{$collat}'");
        } catch (PDOException $e) {
            // Nunca se propaga el mensaje original: podría incluir host/usuario.
            log_message('critical', 'No se pudo conectar a la base de datos (PDO): ' . $e->getMessage());

            throw new RuntimeException('No se pudo conectar a la base de datos.');
        }

        return self::$instancia = $pdo;
    }

    /** Solo para pruebas: permite reiniciar la conexión. */
    public static function reiniciar(): void
    {
        self::$instancia = null;
    }
}
