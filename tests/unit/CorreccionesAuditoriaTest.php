<?php

use App\Models\EnvioRepetido;
use App\Models\Modelo_envio_unico;
use App\Validation\ComposicionContrasena;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresiones de los hallazgos de la revisión de calidad (sin base de datos):
 * sesión, contraseñas, carpetas de writable/, íconos, etiquetas de formulario,
 * envío único de pedidos y la ventana «Nuevo pedido».
 *
 * @internal
 */
final class CorreccionesAuditoriaTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url', 'seguridad', 'rutas', 'ui']);

        config('Settings')->handlers = ['array'];
        \Config\Services::resetSingle('settings');
    }

    // ── Sesión ──────────────────────────────────────────────────────────
    public function testLaSesionDuraTreintaMinutosYSinRecordarme(): void
    {
        $this->assertSame(1800, config('Session')->expiration);
        $this->assertFalse(config('Auth')->sessionConfig['allowRemembering']);
    }

    // ── Contraseñas ─────────────────────────────────────────────────────
    /** @return iterable<string, array{string, bool}> */
    public static function contrasenas(): iterable
    {
        yield 'solo minúsculas'          => ['fruverfresco', false];
        yield 'sin números ni símbolos'  => ['FruverFresco', false];
        yield 'sin símbolo'              => ['Fruver2026', false];
        yield 'sin número'               => ['Fruver-Fresco', false];
        yield 'sin mayúscula'            => ['fruver-2026', false];
        yield 'sin minúscula'            => ['FRUVER-2026', false];
        yield 'solo números'             => ['20262026', false];
        yield 'completa'                 => ['Mango-Rojo-2026!', true];
        yield 'con espacio como símbolo' => ['Limón verde 7A', true];
        yield 'con acentos y ñ'          => ['Piña#Dulce9', true];
    }

    /** @dataProvider contrasenas */
    public function testComposicionDeContrasena(string $clave, bool $valida): void
    {
        $resultado = (new ComposicionContrasena(config('Auth')))->check($clave);

        $this->assertSame($valida, $resultado->isOK(), $clave);
    }

    public function testElValidadorEstaRegistradoEnShield(): void
    {
        $this->assertContains(ComposicionContrasena::class, config('Auth')->passwordValidators);

        $res = service('passwords')->check('fruverfresco1', new \CodeIgniter\Shield\Entities\User(['username' => 'ana', 'email' => 'ana@x.mx']));
        $this->assertFalse($res->isOK());
        $this->assertStringContainsString('mayúscula', (string) $res->reason());
    }

    // ── writable/ ───────────────────────────────────────────────────────
    public function testLasCarpetasDeWritableVienenEnElRepositorio(): void
    {
        foreach (['cache', 'session', 'logs', 'uploads', 'debugbar'] as $carpeta) {
            $this->assertFileExists(ROOTPATH . 'writable/' . $carpeta . '/index.html', $carpeta);
        }

        $ignorado = file_get_contents(ROOTPATH . '.gitignore');
        $this->assertStringContainsString('!writable/cache/index.html', $ignorado);
        $this->assertStringContainsString('!writable/session/index.html', $ignorado);
    }

    // ── Íconos ──────────────────────────────────────────────────────────
    public function testExistenciasUsaElMismoIconoDeEliminar(): void
    {
        $fuente = file_get_contents(APPPATH . 'Views/lista_existencia.php');

        $this->assertStringContainsString('fa-trash-can', $fuente);
        $this->assertDoesNotMatchRegularExpression('/fa-trash"/', $fuente);
    }

    // ── Etiquetas de los campos (WCAG 1.3.1, 3.3.2, 4.1.2) ─────────────
    public function testTodosLosCamposTienenEtiquetaAsociada(): void
    {
        $sinEtiqueta = [];
        $total       = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APPPATH . 'Views')) as $archivo) {
            $ruta = str_replace('\\', '/', $archivo->getPathname());
            if (! str_ends_with($ruta, '.php') || preg_match('#/(errors|correos)/|main_page3\.php$#', $ruta)) {
                continue; // main_page3 quedó en desuso
            }

            foreach ($this->camposSinEtiqueta(file_get_contents($ruta), $total) as $campo) {
                $sinEtiqueta[] = basename($ruta) . ': ' . $campo;
            }
        }

        $this->assertGreaterThan(200, $total);
        $this->assertSame([], $sinEtiqueta);
    }

    /** @return list<string> */
    private function camposSinEtiqueta(string $fuente, int &$total): array
    {
        $html = preg_replace(['/<\?=.*?\?>/s', '/<\?php.*?(\?>|$)/s'], ['X', ''], $fuente);
        $dom  = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        $xp   = new DOMXPath($dom);
        $fors = [];
        foreach ($xp->query('//label[@for]') as $l) {
            $fors[$l->getAttribute('for')] = true;
        }

        $faltan = [];
        foreach ($xp->query('//input|//select|//textarea') as $el) {
            if (in_array(strtolower($el->getAttribute('type')), ['hidden', 'submit', 'button', 'reset', 'image'], true)) {
                continue;
            }
            $total++;
            $ok = $el->getAttribute('aria-label') !== '' || $el->getAttribute('aria-labelledby') !== ''
                || ($el->getAttribute('id') !== '' && isset($fors[$el->getAttribute('id')]));
            for ($p = $el->parentNode; ! $ok && $p instanceof DOMElement; $p = $p->parentNode) {
                $ok = $p->nodeName === 'label';
            }
            if (! $ok) {
                $faltan[] = $el->nodeName . '[name=' . $el->getAttribute('name') . ']';
            }
        }

        return $faltan;
    }

    // ── Envío único ─────────────────────────────────────────────────────
    public function testTokenDeEnvio(): void
    {
        $token = Modelo_envio_unico::nuevoToken();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
        $this->assertSame($token, Modelo_envio_unico::tokenValido($token));
        $this->assertNull(Modelo_envio_unico::tokenValido('x' . substr($token, 1)));
        $this->assertNull(Modelo_envio_unico::tokenValido(null));
        $this->assertNotSame($token, Modelo_envio_unico::nuevoToken());

        $campo = campo_envio_unico();
        $this->assertMatchesRegularExpression('/^<input type="hidden" name="token_envio" value="[a-f0-9]{32}">$/', $campo);
    }

    public function testMensajeDeEnvioRepetido(): void
    {
        $this->assertSame('Este pedido ya se había guardado (#12); no se creó otro.', (new EnvioRepetido('pedido', 12))->getMessage());
        $this->assertInstanceOf(DomainException::class, new EnvioRepetido('pedido', null));
    }

    // ── Ventana «Nuevo pedido» ──────────────────────────────────────────
    public function testNuevoPedidoEnUnaSolaPantallaConSelectorAccesible(): void
    {
        $html = view('partials/modal_pedido', [
            'modo'              => 'crear',
            'clientes'          => [['id' => 3, 'nombre' => 'Ana', 'ape_pat' => 'Luna', 'ape_mat' => '<b>']],
            'repartidores'      => [['id' => 2, 'nombre' => 'Beto', 'ape_pat' => 'Ruiz', 'ape_mat' => '']],
            'idsPreferenciales' => [3],
        ]);

        // Sin pasos: un solo formulario con su botón de guardar
        $this->assertStringNotContainsString('Siguiente', $html);
        $this->assertSame(1, substr_count($html, 'type="submit"'));
        $this->assertStringContainsString('name="token_envio"', $html);
        $this->assertStringContainsString('name="estado" value="pedido_realizado"', $html);
        $this->assertStringContainsString('value="' . date('Y-m-d') . '"', $html);

        // Combobox ARIA para elegir el producto con el teclado
        $this->assertMatchesRegularExpression('/role="combobox"[^>]*aria-controls="c-producto-lista"/', $html);
        $this->assertStringContainsString('role="listbox"', $html);

        // La ventana no se cierra sola y se limpia con su función
        $this->assertStringContainsString('data-al-cerrar="cerrarModal"', $html);

        // Escape de datos
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('★ Ana Luna', $html);
    }

    public function testEditarPedidoNoLlevaTokenYSiEstatus(): void
    {
        $html = view('partials/modal_pedido', [
            'modo' => 'editar', 'clientes' => [], 'repartidores' => [], 'idsPreferenciales' => [],
        ]);

        $this->assertStringNotContainsString('token_envio', $html);
        $this->assertStringContainsString('id="e-estado" name="estado"', $html);
        $this->assertStringContainsString('modifica_pedido_completo', $html);
    }

    // ── Scripts comunes ─────────────────────────────────────────────────
    public function testLosScriptsComunesSeCarganConVersion(): void
    {
        $html = view('partials/scripts_formularios');

        foreach (['reglas_campo', 'modales', 'envio_unico', 'aviso_modal'] as $script) {
            $this->assertMatchesRegularExpression('#js/' . $script . '\.js\?v=\d+#', $html, $script);
        }
    }

    public function testElPanelDeReglasFlotaYNoMueveLaPagina(): void
    {
        $css = file_get_contents(FCPATH . 'estilos/reglas_campo.css');
        $js  = file_get_contents(FCPATH . 'js/reglas_campo.js');

        $this->assertMatchesRegularExpression('/\.regla-campo\s*\{[^}]*position:\s*fixed/', $css);
        $this->assertMatchesRegularExpression('/\.regla-campo\s*\{[^}]*pointer-events:\s*none/', $css);
        $this->assertStringContainsString('document.body.appendChild(panel)', $js);
        $this->assertStringNotContainsString('insertBefore(panel', $js);
    }

    public function testNingunaVistaCierraModalesConClicEnElFondo(): void
    {
        foreach (glob(APPPATH . 'Views/*.php') as $vista) {
            if (str_ends_with($vista, 'main_page3.php')) {
                continue;
            }
            $this->assertStringNotContainsString('window.onclick', file_get_contents($vista), basename($vista));
        }
    }
}
