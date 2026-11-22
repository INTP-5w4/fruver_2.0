<?php

namespace App\Commands;

use App\Libraries\PrivilegiosBd;
use App\Validation\ComposicionContrasena;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Lista de verificación de seguridad antes de publicar FRUVER (y periódicamente después).
 *
 *   php spark seguridad:revisar
 *
 * Cada punto sale como OK, AVISO o FALLA. Termina con código 1 si hay alguna FALLA, para
 * poder usarlo en un despliegue automático. En una instalación local (localhost) los puntos
 * que solo importan en un servidor público salen como AVISO en lugar de FALLA.
 *
 * Responde al deber de seguridad de la LFPDPPP (medidas administrativas, técnicas y físicas)
 * y a la sección 10 del Aviso de Privacidad. Ver docs/PRODUCCION.md.
 */
class RevisarSeguridad extends BaseCommand
{
    protected $group       = 'Fruver';
    protected $name        = 'seguridad:revisar';
    protected $description = 'Revisa la configuración de seguridad (entorno, HTTPS, base de datos, sesión, bitácoras, archivos).';
    protected $usage       = 'seguridad:revisar';

    /** @var list<array{string,string,string}> */
    private array $resultados = [];

    private bool $publico = false;

    public function run(array $params)
    {
        $app  = config('App');
        $host = (string) parse_url($app->baseURL, PHP_URL_HOST);
        $this->publico = ! in_array($host, ['localhost', '127.0.0.1', '::1', ''], true) && ! str_ends_with($host, '.local');

        CLI::write('Instalación: ' . $app->baseURL . ($this->publico ? ' (servidor público)' : ' (local)'), 'yellow');
        CLI::newLine();

        $this->entorno($app);
        $this->baseDeDatos();
        $this->sesionYContrasenas();
        $this->archivos();
        $this->privacidad();

        $fallas = 0;
        foreach ($this->resultados as [$nivel, $tema, $texto]) {
            $color = ['OK' => 'green', 'AVISO' => 'yellow', 'FALLA' => 'red'][$nivel];
            CLI::write(CLI::color(str_pad($nivel, 6), $color) . $tema . str_repeat(' ', max(1, 14 - mb_strlen($tema))) . $texto);
            $fallas += $nivel === 'FALLA' ? 1 : 0;
        }

        CLI::newLine();
        $avisos = count(array_filter($this->resultados, static fn (array $r): bool => $r[0] === 'AVISO'));
        CLI::write("Fallas: {$fallas} · Avisos: {$avisos}", $fallas > 0 ? 'red' : ($avisos > 0 ? 'yellow' : 'green'));

        return $fallas > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }

    private function ok(string $tema, string $texto): void
    {
        $this->resultados[] = ['OK', $tema, $texto];
    }

    private function aviso(string $tema, string $texto): void
    {
        $this->resultados[] = ['AVISO', $tema, $texto];
    }

    /** FALLA en un servidor público; AVISO en local. */
    private function falla(string $tema, string $texto, bool $siempre = false): void
    {
        $this->resultados[] = [$siempre || $this->publico ? 'FALLA' : 'AVISO', $tema, $texto];
    }

    // ── Entorno y transporte ───────────────────────────────────────────
    private function entorno(\Config\App $app): void
    {
        ENVIRONMENT === 'production'
            ? $this->ok('Entorno', 'CI_ENVIRONMENT = production (no se muestran errores internos).')
            : $this->falla('Entorno', 'CI_ENVIRONMENT = ' . ENVIRONMENT . ': los errores muestran rutas y código. En el servidor usa production.');

        str_starts_with($app->baseURL, 'https://')
            ? $this->ok('HTTPS', 'La dirección del sitio usa https.')
            : $this->falla('HTTPS', 'app.baseURL no usa https: contraseñas y datos viajarían sin cifrar.');

        $app->forceGlobalSecureRequests
            ? $this->ok('HTTPS', 'Se redirige a https y se envía HSTS.')
            : $this->falla('HTTPS', 'forceGlobalSecureRequests está apagado (se enciende solo en production).');

        config('Cookie')->secure
            ? $this->ok('Cookies', 'Cookies con Secure, HttpOnly y SameSite=' . config('Cookie')->samesite . '.')
            : $this->falla('Cookies', 'Las cookies no llevan Secure (se enciende solo en production).');

        $app->CSPEnabled
            ? $this->ok('Cabeceras', 'Content-Security-Policy activa.')
            : $this->falla('Cabeceras', 'Content-Security-Policy desactivada.', true);

        config('Toolbar')->activa && ENVIRONMENT !== 'production'
            ? $this->falla('Depuración', 'La barra de depuración está activa (toolbar.activa).')
            : $this->ok('Depuración', 'Barra de depuración apagada.');
    }

    // ── Base de datos ──────────────────────────────────────────────────
    private function baseDeDatos(): void
    {
        $config = config('Database');
        $usuario = (string) $config->default['username'];

        try {
            $db = db_connect();
            $permisos = array_map(static fn (array $f): string => (string) reset($f), $db->query('SHOW GRANTS FOR CURRENT_USER()')->getResultArray());
        } catch (Throwable $e) {
            $this->falla('Base', 'No se pudo conectar con la cuenta de la aplicación: ' . $e->getMessage(), true);

            return;
        }

        $texto = strtoupper(implode("\n", $permisos));
        $peligrosos = array_values(array_filter(
            ['ALL PRIVILEGES', 'SUPER', 'FILE', 'GRANT OPTION', 'CREATE', 'DROP', 'ALTER', 'TRIGGER', 'PROCESS', 'SHUTDOWN', 'CREATE USER'],
            static fn (string $p): bool => preg_match('/\b' . preg_quote($p, '/') . '\b/', $texto) === 1
        ));

        if ($usuario === 'root' || $peligrosos !== []) {
            $this->falla('Base', 'La aplicación se conecta como «' . $usuario . '» con permisos de más ('
                . implode(', ', $peligrosos ?: ['root']) . '). Corre: php spark bd:asegurar');
        } else {
            $this->ok('Base', 'La aplicación usa «' . $usuario . '» con privilegios mínimos (sin DDL, TRIGGER, FILE ni GRANT).');
        }

        try {
            // La cuenta de la aplicación no puede ver los triggers (no tiene TRIGGER): se leen
            // con la de administración si está disponible.
            $lector   = $config->adminSeparado() ? db_connect('admin') : $db;
            $triggers = array_column($lector->query(
                'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'
            )->getResultArray(), 'TRIGGER_NAME');
            $inmutables = count(preg_grep('/^inm_/', $triggers));
            $auditoria  = count(preg_grep('/^(aud_|bit_)/', $triggers));
            $inmutables >= 6 && $auditoria >= 15
                ? $this->ok('Bitácoras', "Auditoría activa ({$auditoria} triggers) y bitácoras inalterables ({$inmutables}).")
                : $this->falla('Bitácoras', "Faltan triggers de auditoría ({$auditoria}) o de inalterabilidad ({$inmutables}). Corre: php spark bd:migrar", true);
        } catch (Throwable) {
            $this->aviso('Bitácoras', 'No se pudieron leer los triggers (define database_admin_password para revisarlos).');
        }

        if (! $config->adminSeparado()) {
            $this->aviso('Base', 'No hay cuenta de administración separada (database.admin.*).');
        } elseif ((string) $config->admin['password'] === '') {
            $this->falla('Base', 'La cuenta de administración «' . $config->admin['username'] . '» no tiene contraseña.');
        } elseif ($this->enEnv('database.admin.password')) {
            $this->falla('Base', 'La contraseña de administración está escrita en .env; pásala como variable de entorno solo al migrar o respaldar.');
        } else {
            $this->ok('Base', 'La contraseña de administración no está en .env.');
        }

        if ($usuario !== PrivilegiosBd::USUARIO && $usuario !== 'root') {
            $this->aviso('Base', 'La cuenta de la aplicación no es ' . PrivilegiosBd::USUARIO . '; revisa sus permisos a mano.');
        }
    }

    // ── Sesión y contraseñas ───────────────────────────────────────────
    private function sesionYContrasenas(): void
    {
        $exp = config('Session')->expiration;
        $exp > 0 && $exp <= 1800
            ? $this->ok('Sesión', 'Se cierra tras ' . intdiv($exp, 60) . ' min sin actividad.')
            : $this->falla('Sesión', 'La sesión dura ' . $exp . ' s; el máximo acordado es 1800 (30 min).', true);

        config('Auth')->sessionConfig['allowRemembering']
            ? $this->falla('Sesión', '«Recordarme» está activo: reabre la sesión sin contraseña.', true)
            : $this->ok('Sesión', '«Recordarme» apagado.');

        $auth = config('Auth');
        in_array(ComposicionContrasena::class, $auth->passwordValidators, true) && $auth->minimumPasswordLength >= 8
            ? $this->ok('Contraseñas', 'Mínimo ' . $auth->minimumPasswordLength . ' caracteres con minúscula, mayúscula, número y símbolo.')
            : $this->falla('Contraseñas', 'La política de contraseñas no exige composición o largo mínimo de 8.', true);

        in_array('login*', config('Filters')->filters['auth-rates']['before'] ?? [], true)
            ? $this->ok('Acceso', 'Límite de intentos de inicio de sesión por IP activo.')
            : $this->falla('Acceso', 'Sin límite de intentos de inicio de sesión (filtro auth-rates).', true);
    }

    // ── Archivos ───────────────────────────────────────────────────────
    private function archivos(): void
    {
        is_file(ROOTPATH . '.htaccess') && is_file(FCPATH . '.htaccess')
            ? $this->ok('Archivos', 'La raíz del proyecto bloquea todo lo que no sea public/ (.htaccess).')
            : $this->falla('Archivos', 'Falta .htaccess en la raíz o en public/: .env y los respaldos podrían descargarse.', true);

        foreach (['.env', 'composer.json', 'spark'] as $archivo) {
            if (is_file(FCPATH . $archivo)) {
                $this->falla('Archivos', 'public/' . $archivo . ' no debe existir: es descargable.', true);
            }
        }

        $volcados = array_map('basename', array_merge(glob(ROOTPATH . '*.sql') ?: [], glob(ROOTPATH . '*.sql.gz') ?: []));
        $volcados === []
            ? $this->ok('Archivos', 'No hay volcados .sql en la raíz del proyecto.')
            : $this->aviso('Archivos', 'Volcados en la raíz (' . implode(', ', $volcados) . '): si traen datos reales, sácalos del proyecto y del repositorio.');

        foreach (['cache', 'session', 'logs', 'uploads'] as $carpeta) {
            if (! is_writable(WRITEPATH . $carpeta)) {
                $this->falla('Archivos', 'writable/' . $carpeta . ' no tiene permiso de escritura.', true);
            }
        }

        $respaldos = glob(config('Respaldo')->rutaCarpeta() . '*.sql.gz') ?: [];
        $ultimo    = $respaldos === [] ? 0 : max(array_map('filemtime', $respaldos));
        $ultimo > time() - 2 * DAY
            ? $this->ok('Respaldos', 'Último respaldo: ' . date('d/m/Y H:i', $ultimo) . '.')
            : $this->aviso('Respaldos', 'No hay respaldo de los últimos 2 días; programa php spark respaldo:crear a diario.');
    }

    // ── Aviso de privacidad ────────────────────────────────────────────
    private function privacidad(): void
    {
        $faltan = config('Privacidad')->pendientes();
        $faltan === []
            ? $this->ok('Privacidad', 'El aviso de privacidad tiene todos los datos del responsable.')
            : $this->falla('Privacidad', 'Faltan datos en el aviso (app/Config/Privacidad.php): ' . implode(', ', $faltan) . '.');
    }

    private function enEnv(string $llave): bool
    {
        $env = ROOTPATH . '.env';

        return is_file($env) && preg_match('/^[ \t]*' . preg_quote($llave, '/') . '[ \t]*=[ \t]*\S+/m', (string) file_get_contents($env)) === 1;
    }
}
