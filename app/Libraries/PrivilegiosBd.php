<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Cuenta de base de datos de la APLICACIÓN con privilegios mínimos (LFPDPPP, deber de
 * seguridad: medidas técnicas). La configuran los comandos bd:asegurar y bd:migrar.
 *
 * Qué puede hacer la aplicación web con esta cuenta:
 *  - Leer y escribir (SELECT, INSERT, UPDATE, DELETE) las tablas de operación.
 *  - Solo LEER las bitácoras que escriben los triggers (auditoria, pedido_bitacora)
 *    y la tabla de migraciones.
 *  - Solo AGREGAR (SELECT, INSERT) en rol_bitacora y consentimiento: son constancias.
 *
 * Qué NO puede hacer, aunque un atacante tome control de la aplicación (inyección SQL,
 * ejecución remota de código, robo de .env):
 *  - Crear, alterar, vaciar o borrar tablas (CREATE, ALTER, DROP, TRUNCATE).
 *  - Crear o borrar triggers (TRIGGER), con lo que no puede apagar la auditoría.
 *  - Leer o escribir archivos del servidor (FILE), ver otras bases ni otorgar permisos.
 *  - Conectarse desde otra máquina: la cuenta solo existe para los hosts indicados.
 *
 * Los permisos se dan tabla por tabla, así que una tabla nueva no queda accesible hasta
 * volver a aplicarlos: `php spark bd:migrar` lo hace después de cada migración.
 */
final class PrivilegiosBd
{
    public const USUARIO = 'fruver_app';

    /** Hosts desde los que se conecta la aplicación (servidor web y base en la misma máquina). */
    public const HOSTS = ['localhost', '127.0.0.1'];

    /** Tablas de solo lectura para la aplicación (las escriben los triggers o las migraciones). */
    public const SOLO_LECTURA = ['auditoria', 'pedido_bitacora', 'migrations'];

    /** Tablas de solo inserción: constancias que no se corrigen ni se borran. */
    public const SOLO_INSERCION = ['rol_bitacora', 'consentimiento'];

    private const OPERACION = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];

    /**
     * Privilegios que corresponden a cada tabla.
     *
     * @param list<string> $tablas
     *
     * @return array<string, list<string>> tabla => privilegios
     */
    public static function permisos(array $tablas): array
    {
        $salida = [];

        foreach ($tablas as $tabla) {
            $salida[$tabla] = match (true) {
                in_array($tabla, self::SOLO_LECTURA, true)   => ['SELECT'],
                in_array($tabla, self::SOLO_INSERCION, true) => ['SELECT', 'INSERT'],
                default                                      => self::OPERACION,
            };
        }

        return $salida;
    }

    public function __construct(private readonly BaseConnection $admin, private readonly string $baseDatos)
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $baseDatos) !== 1) {
            throw new RuntimeException('Nombre de base de datos no válido.');
        }
    }

    /** Crea la cuenta (o le cambia la contraseña) en cada host. */
    public function crearOCambiarClave(string $usuario, string $clave, array $hosts): void
    {
        foreach ($hosts as $host) {
            $cuenta = $this->cuenta($usuario, $host);
            $this->admin->query("CREATE USER IF NOT EXISTS {$cuenta} IDENTIFIED BY " . $this->admin->escape($clave));
            $this->admin->query("ALTER USER {$cuenta} IDENTIFIED BY " . $this->admin->escape($clave));
        }
    }

    /**
     * Quita todo lo que tenga la cuenta y le da exactamente los permisos de permisos().
     *
     * @return array<string, list<string>> lo que se otorgó
     */
    public function aplicar(string $usuario, array $hosts): array
    {
        $tablas = array_column(
            $this->admin->query(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?',
                [$this->baseDatos, 'BASE TABLE']
            )->getResultArray(),
            'TABLE_NAME'
        );
        $permisos = self::permisos($tablas);

        foreach ($hosts as $host) {
            $cuenta = $this->cuenta($usuario, $host);
            $this->admin->query("REVOKE ALL PRIVILEGES, GRANT OPTION FROM {$cuenta}");

            foreach ($permisos as $tabla => $lista) {
                $this->admin->query('GRANT ' . implode(', ', $lista) . " ON `{$this->baseDatos}`.`{$tabla}` TO {$cuenta}");
            }
        }

        $this->admin->query('FLUSH PRIVILEGES');

        return $permisos;
    }

    /**
     * Hosts en los que ya existe la cuenta (incluye los que se agregaron con --host).
     *
     * @return list<string>
     */
    public function hostsDe(string $usuario): array
    {
        return array_column($this->admin->query('SELECT Host FROM mysql.user WHERE User = ?', [$usuario])->getResultArray(), 'Host');
    }

    /** ¿Existe ya la cuenta en algún host? */
    public function existe(string $usuario): bool
    {
        return (int) $this->admin->query('SELECT COUNT(*) AS n FROM mysql.user WHERE User = ?', [$usuario])->getRow()->n > 0;
    }

    private function cuenta(string $usuario, string $host): string
    {
        if (preg_match('/^[A-Za-z0-9_]{1,32}$/', $usuario) !== 1 || preg_match('/^[A-Za-z0-9_.%:-]{1,255}$/', $host) !== 1) {
            throw new RuntimeException('Usuario o host no válido.');
        }

        return "'{$usuario}'@'{$host}'";
    }
}
