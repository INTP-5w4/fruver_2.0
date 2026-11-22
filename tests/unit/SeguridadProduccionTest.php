<?php

use App\Libraries\ArchivoEnv;
use App\Libraries\PrivilegiosBd;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Cuenta de base de datos de privilegios mínimos, grupo de administración y aviso de
 * privacidad 1.2 (sin base de datos).
 *
 * @internal
 */
final class SeguridadProduccionTest extends CIUnitTestCase
{
    public function testPermisosMinimosPorTabla(): void
    {
        $p = PrivilegiosBd::permisos(['pedido', 'cliente', 'auditoria', 'pedido_bitacora', 'migrations', 'rol_bitacora', 'consentimiento', 'envio_unico']);

        $this->assertSame(['SELECT', 'INSERT', 'UPDATE', 'DELETE'], $p['pedido']);
        $this->assertSame(['SELECT', 'INSERT', 'UPDATE', 'DELETE'], $p['envio_unico']);
        $this->assertSame(['SELECT'], $p['auditoria']);
        $this->assertSame(['SELECT'], $p['pedido_bitacora']);
        $this->assertSame(['SELECT'], $p['migrations']);
        $this->assertSame(['SELECT', 'INSERT'], $p['rol_bitacora']);
        $this->assertSame(['SELECT', 'INSERT'], $p['consentimiento']);

        $todos = array_merge(...array_values($p));
        foreach (['CREATE', 'ALTER', 'DROP', 'TRIGGER', 'FILE', 'GRANT OPTION', 'ALL PRIVILEGES'] as $peligroso) {
            $this->assertNotContains($peligroso, $todos);
        }
    }

    public function testElGrupoAdminUsaLasCredencialesDeDefaultSiNoSeConfiguran(): void
    {
        $db = new \Config\Database();

        $this->assertSame($db->default['database'], $db->admin['database']);
        $this->assertSame($db->default['hostname'], $db->admin['hostname']);
        if (! $db->adminSeparado()) {
            $this->assertSame($db->default['username'], $db->admin['username']);
        }
    }

    public function testArchivoEnvReemplazaAgregaYConservaElResto(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($ruta, "# comentario\r\nCI_ENVIRONMENT = development\r\ndatabase.default.username = root\r\n# database.default.password = \r\n");

        ArchivoEnv::fijar($ruta, [
            'database.default.username' => 'fruver_app',
            'database.default.password' => 'abc123',
            'database.admin.username'   => 'root',
        ], 'Base de datos');

        $texto = file_get_contents($ruta);
        unlink($ruta);

        $this->assertStringContainsString("# comentario\r\nCI_ENVIRONMENT = development\r\n", $texto);
        $this->assertStringContainsString('database.default.username = "fruver_app"', $texto);
        $this->assertStringContainsString('database.default.password = "abc123"', $texto);
        $this->assertStringContainsString("# Base de datos\r\ndatabase.admin.username = \"root\"", $texto);
        $this->assertSame(1, substr_count($texto, 'database.default.username'));
    }

    public function testArchivoEnvRechazaValoresQueRompenElArchivo(): void
    {
        $this->expectException(RuntimeException::class);
        ArchivoEnv::fijar(tempnam(sys_get_temp_dir(), 'env'), ['database.default.password' => "a\nCI_ENVIRONMENT = development"]);
    }

    public function testAvisoDePrivacidad12(): void
    {
        helper(['url']);
        $cfg = config('Privacidad');

        $this->assertSame('1.2', $cfg->version);
        $this->assertNotContains('Fecha de actualización', $cfg->pendientes());

        $html = view('privacidad/aviso', ['cfg' => $cfg]);
        foreach (['Auditoría del registro del cliente', 'Historial de cambios de rol', '30 minutos sin actividad',
            'Baja del repartidor', 'no se pueden modificar ni borrar', 'privilegios mínimos', '7 días'] as $frase) {
            $this->assertStringContainsString($frase, $html, $frase);
        }
        $this->assertStringNotContainsString('"recordarme") y de acceso', $html);
    }
}
