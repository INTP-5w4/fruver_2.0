<?php

use App\Libraries\Rutas\Combustible;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Renderiza con datos de ejemplo (sin base de datos) las pantallas de roles, datos del
 * repartidor, combustible y comunicaciones, para detectar variables mal escritas y
 * comprobar el escape de datos.
 *
 * @internal
 */
final class VistasNuevasTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url', 'seguridad', 'rutas', 'ui', 'whatsapp']);

        config('Settings')->handlers = ['array'];
        \Config\Services::resetSingle('settings');
    }

    public function testRoles(): void
    {
        $pager = service('pager');
        $pager->store('default', 1, 25, 2);

        $html = view('roles', [
            'cuentas' => [
                ['id' => 4, 'username' => 'yo', 'active' => 1, 'created_at' => null, 'last_active' => '2026-11-12 09:00:00',
                    'correo' => 'yo@x.mx', 'roles' => 'superadmin', 'id_repartidor' => null,
                    'rep_nombre' => null, 'rep_ape_pat' => null, 'rep_telefono' => null, 'rep_direccion' => null],
                ['id' => 21, 'username' => 'pepe<script>', 'active' => 1, 'created_at' => null, 'last_active' => null,
                    'correo' => 'p@x.mx', 'roles' => 'repartidor', 'id_repartidor' => 9,
                    'rep_nombre' => 'pepe', 'rep_ape_pat' => '', 'rep_telefono' => '', 'rep_direccion' => ''],
            ],
            'pager'     => $pager,
            'conteo'    => ['superadmin' => 1, 'repartidor' => 1],
            'catalogo'  => \App\Libraries\Roles::catalogo(),
            'buscar'    => '',
            'rol'       => '',
            'yo'        => 4,
            'enumAlDia' => false,
        ]);

        $this->assertStringContainsString('No puedes cambiar tu propio rol', $html);
        $this->assertStringContainsString('Faltan datos', $html);
        $this->assertStringContainsString('action="' . base_url('roles/21') . '"', $html);
        $this->assertStringContainsString('no coinciden con los configurados', $html);
        $this->assertStringNotContainsString('pepe<script>', $html);
        $this->assertStringNotContainsString('>Beta', $html);
    }

    public function testMisDatosRepartidor(): void
    {
        $html = view('mis_datos_repartidor', [
            'rep' => ['id' => 9, 'nombre' => '', 'ape_pat' => '', 'ape_mat' => null, 'telefono' => '', 'direccion' => '',
                'notas' => '', 'vehiculo' => 'auto', 'combustible' => 'diesel', 'rendimiento_km_l' => '12.50'],
            'incompleta' => true,
        ]);

        $this->assertStringContainsString('Completa tus datos de repartidor', $html);
        $this->assertStringContainsString('action="' . base_url('repartidor/mis-datos') . '"', $html);
        $this->assertMatchesRegularExpression('/<option value="diesel" selected>/', $html);
        $this->assertStringContainsString('value="12.5"', $html);
        $this->assertStringNotContainsString('Cancelar', $html); // con datos pendientes no se puede salir del formulario
    }

    public function testCombustible(): void
    {
        $html = view('rutas_combustible', [
            'precios'      => Combustible::precios(),
            'actualizados' => null,
            'fabrica'      => config('Rutas')->preciosCombustible,
            'repartidores' => [
                ['nombre' => 'Julio', 'ape_pat' => 'P', 'vehiculo' => 'moto', 'rendimiento_km_l' => null, 'combustible' => null],
                ['nombre' => 'Ana', 'ape_pat' => 'L', 'vehiculo' => 'bicicleta', 'rendimiento_km_l' => null, 'combustible' => null],
            ],
            'conColumnas' => true,
        ]);

        $this->assertStringContainsString('name="precio_magna"', $html);
        $this->assertStringContainsString('Gasolina roja', $html);
        $this->assertStringContainsString('No usa combustible', $html);
        $this->assertStringContainsString('precios aproximados de fábrica', $html);
    }

    private function datosComunicaciones(bool $pedidos): array
    {
        $fila = [
            'clave' => $pedidos ? 'p30' : 'u2', 'nombre_mostrar' => 'Ana <b>L</b>', 'detalle' => '#30 · En tránsito · $80.00',
            'texto' => "Hola Ana, tu pedido #30\n• 1 kg de kiwi", 'reemplazos' => ['{nombre}' => 'Ana'],
            'tel_wa' => '522221234567', 'whatsapp' => 'https://wa.me/522221234567?text=x', 'puede_wa' => true,
            'sin_wa' => null, 'llamar' => '2221234567', 'por_correo' => false, 'autorizado_en' => '2026-11-01',
        ];

        return [
            'cfg'         => config('Privacidad'),
            'finalidad'   => $pedidos ? 'pedidos' : 'v1',
            'esPedidos'   => $pedidos,
            'finalidades' => array_intersect_key(config('Privacidad')->finalidades, array_flip(['v1', 'v2', 'v3'])),
            'conteos'     => ['v1' => 1, 'v2' => 0, 'v3' => 0],
            'mensaje'     => 'Hola {nombre}, oferta de [PRODUCTO]',
            'plantillas'  => ['detalle' => ['Detalle del pedido', 'Hola {nombre}: {detalle}'], 'en_camino' => ['Va en camino', 'Llega {hora}']],
            'plantilla'   => 'en_camino',
            'filas'       => [$fila],
            'porCorreo'   => 0,
            'porWhatsapp' => 1,
            'bloqueado'   => false,
            'correoListo' => false,
            'asunto'      => 'FRUVER',
            'pie'         => $pedidos ? '' : "\n\nBaja",
            'estado'      => $pedidos ? 'pedido_en_transito' : null,
            'estados'     => ['pedido_realizado', 'pedido_en_transito'],
            'diasPedidos' => 15,
        ];
    }

    public function testComunicacionesPedidos(): void
    {
        $html = view('comunicaciones', $this->datosComunicaciones(true));

        $this->assertStringContainsString('Avisos de pedidos', $html);
        $this->assertStringContainsString('Pedidos recientes (1)', $html);
        // esc(..., 'attr') codifica las llaves; el navegador las decodifica.
        $this->assertStringContainsString('data-com-plantilla="Llega {hora}"', html_entity_decode($html, ENT_QUOTES | ENT_HTML5));
        $this->assertMatchesRegularExpression('/com-plantilla com-plantilla--activa"[^>]*data-com-plantilla-clave="en_camino"/s', $html);
        $this->assertStringContainsString('<option value="pedido_en_transito" selected>', $html);
        $this->assertStringContainsString('data-clave="p30"', $html);
        $this->assertStringNotContainsString('Correo electrónico (', $html);
        $this->assertStringNotContainsString('Ana <b>L</b>', $html);
        // El aviso de [corchetes] aparece porque el mensaje tiene uno.
        $this->assertMatchesRegularExpression('/data-com-corchetes(?! hidden)/', $html);
    }

    public function testComunicacionesPromociones(): void
    {
        $html = view('comunicaciones', $this->datosComunicaciones(false));

        $this->assertStringContainsString('Personas que autorizaron V1', $html);
        $this->assertStringContainsString('Correo electrónico (', $html);
        $this->assertStringContainsString('Mensajes listos', $html);
    }
}
