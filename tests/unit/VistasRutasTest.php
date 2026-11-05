<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Renderiza las vistas de rutas con datos de ejemplo (sin base de datos) para
 * detectar variables o claves mal escritas y comprobar el escape de datos.
 *
 * @internal
 */
final class VistasRutasTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['url', 'form', 'rutas']);

        // El header consulta la sesión de Shield, que lee ajustes de la BD: en pruebas
        // se usan ajustes en memoria.
        config('Settings')->handlers = ['array'];
        \Config\Services::resetSingle('settings');
    }

    private function parada(array $extra = []): array
    {
        return $extra + [
            'id' => 7, 'id_pedido' => 15, 'estado' => 'en_ruta', 'orden_ruta' => 1,
            'eta' => '2026-11-06 09:25:00', 'fecha_entrega' => null, 'monto_cobrado' => null, 'metodo_cobro' => null,
            'incidencia' => null, 'evidencia' => null, 'ventana_fin' => '13:00:00',
            'cliente' => 'Ana <b>López</b>', 'telefono_cliente' => '2221234567',
            'calle' => 'Reforma', 'numero' => '12', 'colonia' => 'Centro', 'municipio' => 'Puebla', 'estado_dir' => 'Puebla',
            'referencias' => 'Portón verde', 'lat' => '19.0500000', 'lng' => '-98.2100000', 'place_id' => null,
            'total_pedido' => '245.50', 'navegar' => 'https://www.google.com/maps/dir/?api=1&destination=19.05%2C-98.21',
            'aviso_wa' => 'https://wa.me/522221234567?text=hola',
        ];
    }

    private function ruta(): array
    {
        return [
            'id' => 3, 'fecha' => '2026-11-06', 'estado' => 'en_curso', 'estrategia' => 'rapida', 'proveedor' => 'google',
            'regresa_almacen' => 1, 'distancia_m' => 12400, 'duracion_s' => 2460, 'polyline' => 'abc',
            'repartidor' => 'Julio César', 'vehiculo' => 'moto', 'almacen' => 'Bodega',
            'almacen_lat' => '19.0433', 'almacen_lng' => '-98.1981',
        ];
    }

    public function testMiRuta(): void
    {
        $html = view('mi_ruta', [
            'ruta'        => $this->ruta(),
            'paradas'     => [$this->parada(), $this->parada(['id' => 8, 'id_pedido' => 16, 'estado' => 'asignada'])],
            'siguiente'   => 7,
            'regreso'     => null,
            'almacenMapa' => ['lat' => 19.04, 'lng' => -98.19],
        ]);

        $this->assertStringContainsString('Reoptimizar desde aquí', $html);
        $this->assertStringContainsString('Salir a ruta (1)', $html);
        $this->assertStringContainsString('name="volver_ruta" value="3"', $html);
        $this->assertStringContainsString('Ana &lt;b&gt;López&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>López</b>', $html);
        $this->assertStringContainsString('id="ruta-datos"', $html);
    }

    public function testDetalle(): void
    {
        $html = view('rutas_detalle', [
            'ruta'        => $this->ruta() + ['estado' => 'asignada'],
            'paradas'     => [$this->parada(['estado' => 'asignada'])],
            'almacenMapa' => ['lat' => 19.04, 'lng' => -98.19],
        ]);

        $this->assertStringContainsString('Avisar hora al cliente', $html);
        $this->assertStringContainsString('Ruta #3', $html);
    }

    public function testPlaneador(): void
    {
        $c = $this->parada() + ['tipo' => 'pedido', 'id_entrega' => null, 'id_repartidor_asignado' => null, 'fecha_pedido' => '2026-11-05',
            'id_direccion' => 4, 'precision_geo' => 'ROOFTOP'];
        $sin = ['lat' => null, 'lng' => null, 'id_pedido' => 20, 'id_direccion' => null] + $c;

        $html = view('rutas_planeador', [
            'fecha' => '2026-11-06', 'candidatos' => [$c, $sin],
            'rutas' => [['id' => 3, 'repartidor' => 'Julio', 'estado' => 'asignada', 'paradas' => 2, 'entregadas' => 0, 'fallidas' => 0,
                         'distancia_m' => 1000, 'duracion_s' => 600, 'estrategia' => 'rapida', 'proveedor' => 'google']],
            'repartidores' => [['id' => 2, 'nombre' => 'Julio', 'ape_pat' => 'César', 'vehiculo' => 'moto']],
            'almacenes' => [['id' => 1, 'nombre' => 'Bodega', 'lat' => '19.04', 'lng' => '-98.19']],
            'almacenMapa' => null, 'horaSalida' => '09:00', 'maxParadas' => 25, 'conServicio' => false, 'proveedor' => 'cálculo aproximado', 'conMapa' => false,
        ]);

        $this->assertStringContainsString('Calcular rutas óptimas', $html);
        $this->assertStringContainsString('Sin dirección', $html);
        $this->assertStringContainsString('"max":25', $html);
        $this->assertStringContainsString('No hay servicio de rutas configurado', $html);
    }

    public function testAlmacen(): void
    {
        $html = view('rutas_almacen', ['almacenes' => [], 'almacenMapa' => null]);

        $this->assertStringContainsString('name="geo_lat"', $html);
        $this->assertStringContainsString('Bodega&#x20;principal', $html); // esc(..., 'attr') codifica espacios
    }

    private function pager()
    {
        $pager = service('pager');
        $pager->store('default', 1, 20, 1);

        return $pager;
    }

    public function testMisEntregasMuestraDireccionYNavegacion(): void
    {
        $e = $this->parada(['estado' => 'asignada', 'intento' => 1, 'fecha_pedido' => '2026-11-05']);
        $html = view('mis_entregas', ['entregas' => [$e], 'pager' => $this->pager(), 'estado' => null, 'rutasPendientes' => 1]);

        $this->assertStringContainsString('Tienes 1 ruta por hacer', $html);
        $this->assertStringContainsString('Reforma 12, Centro, Puebla, Puebla', $html);
        $this->assertStringContainsString('Navegar con Google Maps', $html);
        $this->assertStringContainsString('Cobrar $245.50', $html);
    }

    public function testListaDireccionConMapa(): void
    {
        $d = ['id' => 4, 'colonia' => 'Centro', 'calle' => 'Reforma', 'numero' => '12', 'municipio' => 'Puebla', 'estado' => 'Puebla',
              'id_cliente' => 7, 'nombre' => 'Ana', 'ape_pat' => 'López', 'ape_mat' => '', 'lat' => null, 'lng' => null,
              'precision_geo' => null, 'origen_coord' => null, 'codigo_postal' => null, 'referencias' => null, 'es_principal' => 1];
        $html = view('lista_direccion', [
            'direcciones' => [$d], 'clientes' => [7 => $d], 'clientes_lista' => [$d], 'buscar' => '', 'pager' => $this->pager(),
            'resumenGeo' => ['total' => 1, 'ubicadas' => 0, 'aproximadas' => 0], 'conGeo' => true,
        ]);

        $this->assertStringContainsString('Sin ubicar', $html);
        $this->assertStringContainsString('direcciones/geocodificar/4', $html);
        $this->assertStringContainsString('id="geo_editar"', $html);
        $this->assertStringContainsString('name="referencias"', $html);
    }

    private function conConfig(callable $ajuste): void
    {
        $cfg = new \Config\Rutas();
        $ajuste($cfg);
        \CodeIgniter\Config\Factories::injectMock('config', 'Rutas', $cfg);
    }

    protected function tearDown(): void
    {
        \CodeIgniter\Config\Factories::reset('config');
        parent::tearDown();
    }

    public function testConfigDeMapaConOpenrouteservice(): void
    {
        $this->conConfig(static function ($c) { $c->llaveOrs = 'SECRETO-ORS'; });
        $html = view('partials/mapa_config', ['almacenMapa' => ['lat' => '19.1', 'lng' => '-98.2']]);

        $this->assertStringContainsString('data-proveedor="leaflet"', $html);
        $this->assertStringContainsString('leaflet@1.9.4/dist/leaflet.js', $html);
        $this->assertStringContainsString('integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="', $html);
        $this->assertStringContainsString('direcciones&#x2F;buscar', $html); // esc attr codifica "/"
        $this->assertStringContainsString('openrouteservice.org', $html);
        $this->assertStringNotContainsString('SECRETO-ORS', $html, 'La llave de openrouteservice nunca va al navegador');
    }

    public function testConfigDeMapaConGoogleYSinMapa(): void
    {
        $this->conConfig(static function ($c) { $c->llaveServidor = 'SECRETO-SERVIDOR'; $c->llaveNavegador = 'NAVEGADOR'; });
        $html = view('partials/mapa_config');
        $this->assertStringContainsString('data-proveedor="google"', $html);
        $this->assertStringContainsString('data-llave="NAVEGADOR"', $html);
        $this->assertStringNotContainsString('SECRETO-SERVIDOR', $html);
        $this->assertStringNotContainsString('leaflet.js', $html);

        $this->conConfig(static function ($c) { $c->llaveServidor = 'SECRETO-SERVIDOR'; });
        $html = view('partials/mapa_config');
        $this->assertStringNotContainsString('fruver-mapas', $html, 'Google sin llave de navegador: sin mapa');
    }

    public function testConfigDeMapaSinLlavesUsaOpenStreetMapSinBuscador(): void
    {
        $this->conConfig(static function ($c) {});
        $html = view('partials/mapa_config');
        $this->assertStringContainsString('data-proveedor="leaflet"', $html);
        $this->assertStringContainsString('data-buscar=""', $html);
    }
}
