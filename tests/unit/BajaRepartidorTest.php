<?php

use App\Models\Modelo_repartidor;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Baja lógica de repartidores en las pantallas (sin base de datos):
 *  - Roles avisa del trabajo pendiente y muestra el historial de cliente y la bitácora.
 *  - Repartidores ofrece "Dar de baja" / "Reactivar" según quién debe hacerlo.
 *  - Los selectores de pedidos no ofrecen a un repartidor dado de baja, salvo que ya sea el del pedido.
 *
 * @internal
 */
final class BajaRepartidorTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url', 'seguridad', 'rutas', 'ui', 'whatsapp']);

        config('Settings')->handlers = ['array'];
        \Config\Services::resetSingle('settings');
    }

    public function testDeBajaYNombre(): void
    {
        $this->assertTrue(Modelo_repartidor::deBaja(['activo' => 0]));
        $this->assertTrue(Modelo_repartidor::deBaja(['activo' => '0']));
        $this->assertFalse(Modelo_repartidor::deBaja(['activo' => 1]));
        // Sin la migración no existe la columna: nadie está de baja.
        $this->assertFalse(Modelo_repartidor::deBaja(['id' => 3]));
        $this->assertSame('Ana López', Modelo_repartidor::nombreCompleto(['nombre' => 'Ana', 'ape_pat' => 'López', 'ape_mat' => null]));
        $this->assertSame('Repartidor #7', Modelo_repartidor::nombreCompleto(['id' => 7, 'nombre' => '', 'ape_pat' => '']));
    }

    /**
     * mensajeSeguro nunca muestra el texto de una excepción que no sea de la base: las reglas de
     * negocio (DomainException) se atrapan a propósito en cada controlador.
     */
    public function testMensajeSeguroNoMuestraTextosDeExcepciones(): void
    {
        $generico = 'No se pudo guardar.';

        $this->assertSame($generico, \App\Models\PdoModelo::mensajeSeguro(new DomainException('Error: detalle interno'), $generico));
        $this->assertSame($generico, \App\Models\PdoModelo::mensajeSeguro(new RuntimeException('host=127.0.0.1 user=root'), $generico));
    }

    private function cuenta(array $extra): array
    {
        return $extra + [
            'active' => 1, 'created_at' => null, 'last_active' => null, 'correo' => 'x@x.mx',
            'id_repartidor' => null, 'rep_nombre' => null, 'rep_ape_pat' => null, 'rep_telefono' => null, 'rep_direccion' => null,
            'rep_activo' => null, 'rep_fecha_baja' => null, 'id_cliente' => null, 'pedidos_cliente' => 0,
        ];
    }

    public function testRolesAvisaPendientesYMuestraHistorial(): void
    {
        $pager = service('pager');
        $pager->store('default', 1, 25, 3);

        $html = view('roles', [
            'cuentas' => [
                // Repartidor activo con 2 entregas por salir y 1 en camino.
                $this->cuenta(['id' => 21, 'username' => 'pepe', 'roles' => 'repartidor', 'id_repartidor' => 9,
                    'rep_nombre' => 'Pepe', 'rep_ape_pat' => 'Ruiz', 'rep_telefono' => '22', 'rep_direccion' => 'C 1', 'rep_activo' => 1]),
                // Ex repartidor que ahora es cliente: ficha de baja y sus compras.
                $this->cuenta(['id' => 22, 'username' => 'luis', 'roles' => 'user', 'id_repartidor' => 10,
                    'rep_nombre' => 'Luis', 'rep_ape_pat' => 'P', 'rep_telefono' => '1', 'rep_direccion' => 'x',
                    'rep_activo' => 0, 'rep_fecha_baja' => '2026-11-20 10:00:00', 'id_cliente' => 5, 'pedidos_cliente' => 3]),
            ],
            'pager'      => $pager,
            'conteo'     => ['repartidor' => 1, 'user' => 1],
            'catalogo'   => \App\Libraries\Roles::catalogo(),
            'buscar'     => '',
            'rol'        => '',
            'yo'         => 4,
            'enumAlDia'  => true,
            'pendientes' => [21 => ['entregas_asignadas' => 2, 'entregas_en_ruta' => 1, 'rutas_asignadas' => 0, 'rutas_en_curso' => 0]],
            'bitacora'   => [[
                'id' => 1, 'id_user' => 22, 'cuenta' => 'luis<b>', 'rol_antes' => 'repartidor', 'rol_despues' => 'user',
                'motivo' => 'Terminó contrato', 'detalle' => 'Ficha de repartidor #10 dada de baja', 'por_usuario' => 'yo', 'fecha' => '2026-11-20 10:00:00',
            ]],
        ]);

        // Solo el repartidor activo trae los campos de baja.
        $this->assertSame(1, substr_count($html, 'class="roles-baja"'));
        $this->assertStringContainsString('data-repartidor="1"', $html);
        $this->assertStringContainsString('name="cancelar_pendientes" value="1"', $html);
        $this->assertStringContainsString('Cancelar sus 2 entrega(s) pendientes', $html);
        $this->assertStringContainsString('Tiene 1 entrega(s) en camino', $html);
        $this->assertStringContainsString('2 por salir · 1 en camino', $html);
        $this->assertStringContainsString('no escribas datos sensibles', $html);

        $this->assertStringContainsString('Repartidor dado de baja', $html);
        $this->assertStringContainsString('20/11/2026', $html);
        $this->assertStringContainsString('Cliente: 3 pedido(s)', $html);

        $this->assertStringContainsString('Últimos cambios de rol', $html);
        $this->assertStringContainsString('Repartidor', $html);
        $this->assertStringContainsString('→ Cliente', $html);
        $this->assertStringContainsString('Terminó contrato', $html);
        $this->assertStringNotContainsString('luis<b>', $html);
    }

    public function testListaRepartidorAccionesDeBaja(): void
    {
        $pager = service('pager');
        $pager->store('default', 1, 20, 4);

        $base = ['ape_mat' => '', 'telefono' => '1', 'direccion' => 'x', 'notas' => '', 'vehiculo' => 'moto',
            'fecha_baja' => null, 'motivo_baja' => null, 'cuenta' => null, 'cuenta_repartidor' => 0, 'id_user' => null];

        $html = view('lista_repartidor', [
            'repartidores' => [
                ['id' => 1, 'nombre' => 'Manual', 'ape_pat' => 'Activo', 'activo' => 1] + $base,
                ['id' => 2, 'nombre' => 'Manual', 'ape_pat' => 'Baja', 'activo' => 0, 'fecha_baja' => '2026-11-19 09:00:00', 'motivo_baja' => 'Renunció'] + $base,
                ['id' => 3, 'nombre' => 'Con', 'ape_pat' => 'Rol', 'activo' => 1, 'id_user' => 21, 'cuenta' => 'pepe', 'cuenta_repartidor' => 1] + $base,
                ['id' => 4, 'nombre' => 'Ex', 'ape_pat' => 'Cuenta', 'activo' => 0, 'id_user' => 22, 'cuenta' => 'luis', 'cuenta_repartidor' => 0] + $base,
            ],
            'pager'     => $pager,
            'buscar'    => '',
            'conCuenta' => true,
            'nuevos'    => 0,
        ]);

        // Manual activo → se puede dar de baja aquí; con rol de repartidor → solo en Roles.
        $this->assertStringContainsString('action="' . base_url('baja_repartidor/1') . '"', $html);
        $this->assertStringNotContainsString('action="' . base_url('baja_repartidor/3') . '"', $html);
        // Manual de baja → se reactiva aquí; su cuenta ya no es repartidor → solo en Roles.
        $this->assertStringContainsString('action="' . base_url('reactiva_repartidor/2') . '"', $html);
        $this->assertStringNotContainsString('action="' . base_url('reactiva_repartidor/4') . '"', $html);

        $this->assertSame(2, substr_count($html, '>Dado de baja'));
        $this->assertStringContainsString('Dado de baja 19/11/2026', $html);
        $this->assertStringContainsString(esc('Renunció', 'attr'), $html); // el motivo va en el title
    }

    public function testModificaPedidoConservaAlRepartidorDeBaja(): void
    {
        $repartidores = [
            ['id' => 1, 'nombre' => 'Activo', 'ape_pat' => 'A', 'ape_mat' => '', 'activo' => 1],
            ['id' => 2, 'nombre' => 'Baja', 'ape_pat' => 'B', 'ape_mat' => '', 'activo' => 0],
            ['id' => 3, 'nombre' => 'Otra', 'ape_pat' => 'Baja', 'ape_mat' => '', 'activo' => 0],
        ];
        $clientes = [['id' => 5, 'nombre' => 'C', 'ape_pat' => 'D', 'ape_mat' => '']];

        $html = view('modifica_pedido', [
            'pedidos'      => ['id' => 30, 'fecha' => '2026-11-01', 'id_cliente' => 5, 'id_repartidor' => 2],
            'clientes'     => $clientes,
            'repartidores' => $repartidores,
        ]);

        $this->assertMatchesRegularExpression('/<option value="2"\s+selected>\s*Baja B\s*\(dado de baja\)/', $html);
        $this->assertStringContainsString('<option value="1"', $html);
        $this->assertStringNotContainsString('<option value="3"', $html);
    }

    public function testListaPedidoNoOfreceRepartidoresDeBaja(): void
    {
        $repartidores = [
            1 => ['id' => 1, 'nombre' => 'Activo', 'ape_pat' => 'A', 'ape_mat' => '', 'activo' => 1],
            2 => ['id' => 2, 'nombre' => 'Baja', 'ape_pat' => 'B', 'ape_mat' => '', 'activo' => 0],
        ];

        $html = view('lista_pedido', [
            'pedidos'      => [['id' => 30, 'fecha' => '2026-11-01', 'id_cliente' => 5, 'id_repartidor' => 2]],
            'pager'        => (static function () { $p = service('pager'); $p->store('default', 1, 20, 1); return $p; })(),
            'buscar'       => '',
            'clientes'     => [5 => ['id' => 5, 'nombre' => 'C', 'ape_pat' => 'D', 'ape_mat' => '']],
            'repartidores' => $repartidores,
            'pps'          => [],
        ]);

        // En la tabla sigue apareciendo quién llevó el pedido.
        $this->assertStringContainsString('Baja B', $html);
        // En el modal de edición va oculto (solo para conservarlo); en el de alta no aparece.
        $this->assertSame(1, preg_match_all('/<option value="2" hidden data-baja="1">/', $html));
        $this->assertSame(2, preg_match_all('/<option value="1"/', $html));
    }
}
