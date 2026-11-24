<?php

namespace App\Commands;

use App\Libraries\ArchivoEnv;
use App\Libraries\PrivilegiosBd;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use mysqli;
use mysqli_sql_exception;
use Throwable;

/**
 * Pasa la aplicación a una cuenta de base de datos con privilegios mínimos.
 *
 *   php spark bd:asegurar            crea (o rota la contraseña de) la cuenta fruver_app,
 *                                    le da solo los permisos necesarios, comprueba que
 *                                    funcione y guarda sus credenciales en .env
 *   php spark bd:asegurar --host 10.0.0.5   además de localhost, permite ese host
 *                                    (cuando la base vive en otro servidor)
 *
 * Se corre UNA vez con la cuenta de administración (la que hoy está en .env, p. ej. root).
 * Esa cuenta queda como "admin" para migraciones y respaldos; la web deja de usarla.
 * Ver docs/PRODUCCION.md.
 */
class AsegurarBd extends BaseCommand
{
    protected $group       = 'Fruver';
    protected $name        = 'bd:asegurar';
    protected $description = 'Crea la cuenta de base de datos de privilegios mínimos para la aplicación y la configura en .env.';
    protected $usage       = 'bd:asegurar [--host <ip o nombre>]';
    protected $options     = ['--host' => 'Host adicional desde el que se conectará la aplicación.'];

    public function run(array $params)
    {
        $config  = config('Database');
        $default = $config->default;
        $admin   = $config->admin;

        if ($default['username'] === PrivilegiosBd::USUARIO && ! $config->adminSeparado()) {
            CLI::error('La aplicación ya usa ' . PrivilegiosBd::USUARIO . ', pero falta la cuenta de administración '
                . '(database.admin.username / database.admin.password).');

            return EXIT_ERROR;
        }

        $hosts = PrivilegiosBd::HOSTS;
        $extra = CLI::getOption('host');
        if (is_string($extra) && $extra !== '') {
            $hosts[] = $extra;
        }

        $clave = bin2hex(random_bytes(24)); // 48 caracteres hexadecimales

        try {
            $conexion = db_connect('admin');
            $priv     = new PrivilegiosBd($conexion, (string) $default['database']);
            $hosts    = array_values(array_unique([...$hosts, ...$priv->hostsDe(PrivilegiosBd::USUARIO)]));
            $priv->crearOCambiarClave(PrivilegiosBd::USUARIO, $clave, $hosts);
            $permisos = $priv->aplicar(PrivilegiosBd::USUARIO, $hosts);
        } catch (Throwable $e) {
            CLI::error('No se pudo crear la cuenta: ' . $e->getMessage());
            CLI::write('¿La cuenta de administración (' . $admin['username'] . ') tiene permiso de crear usuarios (CREATE USER, GRANT OPTION)?');

            return EXIT_ERROR;
        }

        // Antes de tocar .env se comprueba que la cuenta nueva entra y que NO puede cambiar la estructura.
        $prueba = $this->probar((string) $default['hostname'], (int) ($default['port'] ?? 3306), (string) $default['database'], $clave);
        if ($prueba !== null) {
            CLI::error('La cuenta se creó, pero la prueba falló: ' . $prueba . ' No se modificó .env.');

            return EXIT_ERROR;
        }

        $env = ROOTPATH . '.env';
        $valores = [
            'database.default.username' => PrivilegiosBd::USUARIO,
            'database.default.password' => $clave,
        ];
        // La cuenta de administración se anota solo si no tiene contraseña (XAMPP local).
        // Con contraseña NO se escribe en .env: se pasa como variable de entorno (docs/PRODUCCION.md).
        if (! $config->adminSeparado()) {
            $valores['database.admin.username'] = (string) $admin['username'];
            if ((string) $admin['password'] === '') {
                $valores['database.admin.password'] = '';
            }
        }

        try {
            $this->respaldarEnv($env);
            ArchivoEnv::fijar($env, $valores, 'Base de datos: cuenta de la aplicación (privilegios mínimos) y de administración');
        } catch (Throwable $e) {
            CLI::error('La cuenta funciona pero no se pudo actualizar .env: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('Cuenta ' . PrivilegiosBd::USUARIO . ' lista en: ' . implode(', ', $hosts), 'green');
        $lectura = array_keys(array_filter($permisos, static fn (array $p): bool => $p === ['SELECT']));
        $insercion = array_keys(array_filter($permisos, static fn (array $p): bool => $p === ['SELECT', 'INSERT']));
        CLI::write('  Tablas: ' . count($permisos) . ' · solo lectura: ' . implode(', ', $lectura) . ' · solo inserción: ' . implode(', ', $insercion));
        CLI::write('  Sin CREATE, ALTER, DROP, TRUNCATE, TRIGGER, FILE ni GRANT.');
        CLI::write('.env actualizado (copia anterior en writable/respaldos/).', 'green');

        if ((string) $admin['password'] !== '' && ! $config->adminSeparado()) {
            CLI::write('La contraseña de administración NO se guardó en .env. Para migrar o respaldar, defínela en la sesión:', 'yellow');
            CLI::write('  set database_admin_username=' . $admin['username'] . '   y   set database_admin_password=…', 'yellow');
        } elseif ((string) $admin['password'] === '') {
            CLI::write('AVISO: la cuenta de administración (' . $admin['username'] . ') no tiene contraseña. En un servidor real ponle una.', 'yellow');
        }
        CLI::write('Desde ahora las migraciones se corren con: php spark bd:migrar');

        return EXIT_SUCCESS;
    }

    /** null si todo bien; si no, el motivo. */
    private function probar(string $host, int $puerto, string $bd, string $clave): ?string
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $m = new mysqli($host, PrivilegiosBd::USUARIO, $clave, $bd, $puerto);
            $m->query('SELECT COUNT(*) FROM pedido');
        } catch (mysqli_sql_exception $e) {
            return 'no pudo entrar o leer (' . $e->getMessage() . ').';
        }

        foreach (['CREATE TABLE zz_prueba_privilegios (id INT)', 'DROP TRIGGER IF EXISTS aud_cliente_ai', 'DELETE FROM pedido_bitacora WHERE 1 = 0'] as $sql) {
            try {
                $m->query($sql);
                $m->query('DROP TABLE IF EXISTS zz_prueba_privilegios');

                return 'la cuenta pudo ejecutar «' . $sql . '», y no debería.';
            } catch (mysqli_sql_exception) {
                // Esperado: permiso denegado.
            }
        }
        $m->close();

        return null;
    }

    private function respaldarEnv(string $env): void
    {
        if (! is_file($env)) {
            return;
        }
        $carpeta = WRITEPATH . 'respaldos';
        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0750, true);
        }
        copy($env, $carpeta . DIRECTORY_SEPARATOR . 'env_' . date('Ymd_His') . '.txt');
    }
}
