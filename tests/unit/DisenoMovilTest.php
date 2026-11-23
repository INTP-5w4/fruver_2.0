<?php

use App\Controllers\BaseController;
use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Diseño móvil y usabilidad (docs/diseno/DISENO_MOVIL.md), sin base de datos:
 * navegación (barra inferior ≤ 5 destinos, nada se pierde en «Más»), tokens de diseño,
 * contraste de los bordes de los campos, tablas como tarjetas, ícono en cada acción de
 * las listas y teléfonos con espacios o guiones.
 *
 * @internal
 */
final class DisenoMovilTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['url', 'seguridad', 'ui']);

        // El menú consulta ajustes de Shield: se leen en memoria para no depender de una base.
        $ajustes           = config('Settings');
        $ajustes->handlers = ['array'];
        Factories::injectMock('config', 'Settings', $ajustes);
        Services::resetSingle('settings');
    }

    private function dom(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    // ── Navegación ──────────────────────────────────────────────────────
    public function testBarraInferiorConCincoDestinosComoMaximo(): void
    {
        $xp = $this->dom(view('sidebar'));

        $destinos = $xp->query('//nav[contains(@class,"nav-inferior")]/*[contains(@class,"nav-inferior-item")]');
        $this->assertGreaterThanOrEqual(4, $destinos->length);
        $this->assertLessThanOrEqual(5, $destinos->length);
        $this->assertSame('Secciones', $xp->query('//nav[contains(@class,"nav-inferior")]')->item(0)->getAttribute('aria-label'));

        // «Más» es un botón con texto visible que abre la hoja y anuncia si está abierta
        $mas = $xp->query('//nav[contains(@class,"nav-inferior")]/button')->item(0);
        $this->assertSame('Más', trim($mas->textContent));
        $this->assertSame('hoja-mas', $mas->getAttribute('aria-controls'));
        $this->assertSame('false', $mas->getAttribute('aria-expanded'));
        $this->assertSame('dialog', $xp->query('//*[@id="hoja-mas"]')->item(0)->getAttribute('role'));
    }

    public function testNingunaOpcionDelMenuSePierdeEnCelular(): void
    {
        $xp = $this->dom(view('sidebar'));

        $menu = [];
        foreach ($xp->query('//aside[contains(@class,"sidebar")]//a') as $a) {
            $menu[] = $a->getAttribute('href');
        }
        $hoja = [];
        foreach ($xp->query('//*[@id="hoja-mas"]//a | //nav[contains(@class,"nav-inferior")]//a') as $a) {
            $hoja[] = $a->getAttribute('href');
        }

        $this->assertGreaterThan(10, count($menu));
        $this->assertSame([], array_values(array_diff($menu, $hoja)), 'Opciones del menú que no están en la barra inferior ni en «Más»');
    }

    public function testMenuDeEscritorioConGruposPlegables(): void
    {
        $xp = $this->dom(view('sidebar'));

        $grupos = $xp->query('//aside//details[contains(@class,"nav-grupo")]');
        $this->assertSame(4, $grupos->length);
        foreach ($grupos as $g) {
            $this->assertSame(1, $xp->query('./summary', $g)->length);
        }
        // En Inicio (ruta vacía en la prueba) ningún grupo viene abierto: 5 renglones visibles.
        $this->assertSame(0, $xp->query('//aside//details[@open]')->length);
        $this->assertSame('Menú principal', $xp->query('//aside/nav')->item(0)->getAttribute('aria-label'));
    }

    // ── Tokens y contraste ──────────────────────────────────────────────
    public function testTokensDeDisenoEnRoot(): void
    {
        $css = file_get_contents(FCPATH . 'estilos/base.css');

        foreach (['--esp-1', '--esp-4', '--esp-7', '--t-cuerpo', '--t-control', '--interlineado', '--elev-2', '--tactil-min', '--alto-nav-inferior'] as $token) {
            $this->assertMatchesRegularExpression('/' . preg_quote($token, '/') . '\s*:/', $css, $token);
        }
        // Letra de 16 px en celular (cuerpo y campos)
        $this->assertMatchesRegularExpression('/max-width:\s*599\.98px\)\s*\{\s*:root\s*\{[^}]*--t-cuerpo:\s*16px/', $css);
        $this->assertMatchesRegularExpression('/max-width:\s*767\.98px\)\s*\{\s*:root\s*\{[^}]*--t-control:\s*16px/', $css);
    }

    public function testBordeDeCamposConContrasteMinimoDe3a1(): void
    {
        $css = file_get_contents(FCPATH . 'estilos/base.css');
        preg_match('/--borde-control:\s*(#[0-9a-f]{6})/i', $css, $borde);
        preg_match('/--papel:\s*(#[0-9a-f]{6})/i', $css, $papel);

        $this->assertGreaterThanOrEqual(3.0, $this->contraste($borde[1], '#ffffff'));
        $this->assertGreaterThanOrEqual(3.0, $this->contraste($borde[1], $papel[1]));
        $this->assertGreaterThanOrEqual(4.5, $this->contraste('#4f5d53', $papel[1]), 'tinta suave');
    }

    private function contraste(string $a, string $b): float
    {
        $l = static function (string $hex): float {
            $c = array_map(static fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
            $c = array_map(static fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$l($a), $l($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    // ── Listas: tarjetas en celular ─────────────────────────────────────
    public function testTablasComoTarjetasEnCelular(): void
    {
        $css = file_get_contents(FCPATH . 'estilos/estilosPaginas.css');
        $js  = file_get_contents(FCPATH . 'js/panel.js');

        $this->assertMatchesRegularExpression('/max-width:\s*599\.98px\)[\s\S]*table\.tabla-tarjetas[\s\S]*content:\s*attr\(data-etiqueta\)/', $css);
        $this->assertStringContainsString("setAttribute('data-etiqueta'", $js);
        $this->assertStringContainsString('celda-accion', $js);
        // Las ventanas en celular ocupan la pantalla y el pie queda fijo abajo
        $this->assertMatchesRegularExpression('/footer\.w3-container, \.w3-modal \.modal-footer \{\s*position: sticky; bottom: 0/', $css);
    }

    // ── Ícono en cada acción de las listas y de la navegación ───────────
    public function testCadaAccionDeLasListasLlevaIcono(): void
    {
        $archivos = array_merge(glob(APPPATH . 'Views/lista_*.php'), [
            APPPATH . 'Views/sidebar.php', APPPATH . 'Views/Header.php', APPPATH . 'Views/auditoria.php',
            APPPATH . 'Views/partials/modal_pedido.php', APPPATH . 'Views/partials/buscador_lista.php',
        ]);
        $faltan = [];

        foreach ($archivos as $ruta) {
            // La salida dinámica de la vista (echo corto) cuenta como contenido; el código PHP se descarta.
            $html = preg_replace('/<\?php.*?\?>/s', '', preg_replace('/<\?=.*?\?>/s', 'X', file_get_contents($ruta)));
            $patron = '/<(button)\b[^>]*>(.*?)<\/button>|<a\b[^>]*\bclass="[^"]*\b(?:btn|btn-agregar|btn-guardar|btn-cancelar|w3-button|nav-item|hoja-item|nav-inferior-item|subnav-item)\b[^"]*"[^>]*>(.*?)<\/a>/s';
            preg_match_all($patron, $html, $m, PREG_SET_ORDER);
            foreach ($m as $etiqueta) {
                $cuerpo = $etiqueta[2] !== '' ? $etiqueta[2] : ($etiqueta[3] ?? '');
                $texto  = trim(html_entity_decode(strip_tags($cuerpo)));
                $conIcono = preg_match('/<i\b[^>]*class="[^"]*\bfa-/', $cuerpo) === 1 || str_contains($cuerpo, '<svg');
                $conNombre = preg_match('/\baria-label="/', $etiqueta[0]) === 1;
                if (! $conIcono || ($texto === '' && ! $conNombre)) {
                    $faltan[] = basename($ruta) . ': ' . mb_substr(preg_replace('/\s+/', ' ', strip_tags($etiqueta[0])), 0, 40);
                }
            }
        }

        $this->assertSame([], $faltan);
    }

    // ── Teléfonos sin castigar el formato ───────────────────────────────
    /** @return iterable<string, array{string, string}> */
    public static function telefonos(): iterable
    {
        yield 'con espacios'        => ['222 123 4567', '2221234567'];
        yield 'con guiones'         => ['222-123-45-67', '2221234567'];
        yield 'con paréntesis'      => ['(222) 123.4567', '2221234567'];
        yield 'con lada +52'        => ['+52 222 123 4567', '2221234567'];
        yield 'ya limpio'           => ['2221234567', '2221234567'];
        yield 'con letras: no toca' => ['222 abc 4567', '222 abc 4567'];
    }

    /** @dataProvider telefonos */
    public function testElTelefonoSeNormalizaAntesDeValidar(string $escrito, string $guardado): void
    {
        $request = \Config\Services::incomingrequest(null, false);
        $request->setMethod('post');
        $request->setGlobal('post', ['tel' => $escrito, 'nom' => 'Ana']);

        $controlador = new class () extends BaseController {};
        $controlador->initController($request, service('response'), service('logger'));

        $this->assertSame($guardado, $request->getPost('tel'));
        $this->assertSame('Ana', $request->getPost('nom'));
    }
}
