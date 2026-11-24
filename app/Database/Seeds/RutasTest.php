<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Datos de prueba para el cálculo de rutas ("Rutas-test").
 *
 *   php spark db:seed RutasTest
 *
 * Inserta, siguiendo el mismo flujo que la aplicación (los triggers de MySQL mueven la existencia):
 *   1. Entradas de hoy de 8 productos      -> after_insert_entrada suma la existencia.
 *   2. 10 clientes con dirección ubicada (pin manual) alrededor del almacén de San José Chiapa;
 *      3 de ellos son preferenciales (para comparar la opción "Preferentes primero").
 *   3. 10 pedidos de hoy con 2-4 productos (lo que se ve en "Productos por pedido"), varios con ventana de entrega.
 *   4. Estatus pedido_realizado -> pedido_confirmado -> pedido_en_transito
 *      (bloquea y luego consume la existencia, igual que en la operación normal).
 *
 * Los pedidos quedan en tránsito y sin entrega abierta, así que aparecen como candidatos en /rutas.
 * Los clientes se reconocen por su teléfono (22299000xx): si ya existen se reutilizan; las entradas
 * y los pedidos se crean en cada ejecución para poder volver a probar después de asignar rutas.
 */
class RutasTest extends Seeder
{
    /** Productos (por código) con lo que se compra y a cuánto se vende. */
    private const PRODUCTOS = [
        ['codigo' => 'GEN-TOMSAL', 'nombre' => 'tomate saladet',   'categoria' => 'verduras', 'equivalente' => 20, 'compra' => 260, 'venta' => 28],
        ['codigo' => 'VER-CHAYOT', 'nombre' => 'Chayotte',         'categoria' => 'verduras', 'equivalente' => 20, 'compra' => 180, 'venta' => 18],
        ['codigo' => 'GEN-ZANAHO', 'nombre' => 'Zanahoria',        'categoria' => 'verduras', 'equivalente' => 25, 'compra' => 200, 'venta' => 16],
        ['codigo' => 'VER-PAPA',   'nombre' => 'Papa',             'categoria' => 'verduras', 'equivalente' => 25, 'compra' => 375, 'venta' => 26],
        ['codigo' => 'FRU-MANGO',  'nombre' => 'Mango',            'categoria' => 'frutas',   'equivalente' => 15, 'compra' => 330, 'venta' => 38],
        ['codigo' => 'FRU-DURAZN', 'nombre' => 'Durazno',          'categoria' => 'frutas',   'equivalente' => 15, 'compra' => 420, 'venta' => 45],
        ['codigo' => 'FRU-ELOTE',  'nombre' => 'elote',            'categoria' => 'verduras', 'equivalente' => 20, 'compra' => 160, 'venta' => 14],
        ['codigo' => 'FRU-BROCOL', 'nombre' => 'Brocoli',          'categoria' => 'verduras', 'equivalente' => 10, 'compra' => 250, 'venta' => 42],
    ];

    /** Cajas que entran hoy de cada producto. */
    private const CAJAS_POR_PRODUCTO = 12;

    /**
     * Clientes y su dirección (pin manual con coordenadas reales aproximadas de cada localidad).
     * ventana: hora límite de entrega (null = sin ventana).
     */
    private const CLIENTES = [
        ['Rosa',      'Hernández', 'Flores',   '2229900001', 'si', 'San José Chiapa',          'Centro',            'Av. Reforma',             '12',  19.2087000, -97.7612000, null],
        ['Miguel',    'Sánchez',   'Ramírez',  '2229900002', 'no', 'Rafael Lara Grajales',     'Centro',            'Calle 5 de Mayo',         '208', 19.2453000, -97.7846000, '12:00'],
        ['Guadalupe', 'Pérez',     'Morales',  '2229900003', 'no', 'Nopalucan',                'Centro',            'Calle Hidalgo',           '34',  19.2063000, -97.8049000, null],
        ['Arturo',    'López',     'Cruz',     '2229900004', 'si', 'Mazapiltepec de Juárez',   'Centro',            'Calle Benito Juárez',     '7',   19.2203000, -97.7210000, '11:00'],
        ['Carmen',    'Martínez',  'Vázquez',  '2229900005', 'no', 'Soltepec',                 'Centro',            'Calle Morelos',           '15',  19.2586000, -97.7514000, null],
        ['Jorge',     'García',    'Torres',   '2229900006', 'no', 'San Salvador el Seco',     'Centro',            'Av. Independencia',       '102', 19.1343000, -97.6406000, '14:00'],
        ['Leticia',   'Romero',    'Aguilar',  '2229900007', 'si', 'Oriental',                 'Centro',            'Calle Ferrocarril',       '45',  19.3670000, -97.6103000, null],
        ['Fernando',  'Díaz',      'Ortega',   '2229900008', 'no', 'San Nicolás Buenos Aires', 'Centro',            'Calle Zaragoza',          '3',   19.1445000, -97.5476000, null],
        ['Patricia',  'Jiménez',   'Castro',   '2229900009', 'no', 'Acatzingo',                'Centro',            'Calle 2 Sur',             '110', 18.9819000, -97.7826000, '16:00'],
        ['Ricardo',   'Mendoza',   'Reyes',    '2229900010', 'no', 'Tepeaca',                  'Barrio de San José','Calle 3 Norte',           '58',  18.9667000, -97.9000000, null],
    ];

    /** Líneas de cada pedido: [índice de producto, kilos]. Un pedido por cliente, en el mismo orden. */
    private const PEDIDOS = [
        [[0, 6], [3, 10], [6, 4]],
        [[1, 5], [2, 8]],
        [[4, 3], [5, 2], [7, 2], [0, 4]],
        [[3, 15], [6, 10]],
        [[2, 6], [7, 3], [1, 4]],
        [[0, 12], [4, 5]],
        [[5, 4], [3, 8], [2, 5]],
        [[6, 8], [1, 6]],
        [[7, 4], [0, 5], [4, 2]],
        [[3, 20], [2, 10], [5, 3], [1, 5]],
    ];

    public function run()
    {
        $db  = $this->db;
        $hoy = date('Y-m-d');

        // Lo que registren los triggers de bitácora queda a nombre del seeder
        $db->query("SET @fruver_id_user = NULL, @fruver_usuario = 'Seeder Rutas-test'");

        $db->transException(true)->transStart();

        // 1) Productos y entradas de hoy (el trigger suma la existencia)
        $productos = [];
        foreach (self::PRODUCTOS as $p) {
            $id = $this->idProducto($p);
            $db->table('entrada')->insert([
                'fecha'           => $hoy,
                'fecha_cad'       => null, // trigger: fecha + 5 días
                'cantidad'        => self::CAJAS_POR_PRODUCTO,
                'u_compra'        => 'Caja',
                'u_venta'         => 'Kilogramo',
                'equivalente'     => $p['equivalente'],
                'precio_compra_u' => $p['compra'],
                'precio_venta_u'  => $p['venta'],
                'id_producto'     => $id,
            ]);
            $productos[] = ['id' => $id, 'venta' => $p['venta']];
        }

        // 2) Clientes con dirección ubicada
        $clientes = [];
        foreach (self::CLIENTES as $c) {
            $clientes[] = $this->cliente($c);
        }

        // 3) y 4) Pedidos con sus productos, hasta "en tránsito"
        $pedidos = [];
        foreach (self::PEDIDOS as $i => $lineas) {
            [$idCliente, $idDireccion] = $clientes[$i];
            $ventana = self::CLIENTES[$i][11];

            $db->table('pedido')->insert([
                'fecha'          => $hoy,
                'id_cliente'     => $idCliente,
                'id_direccion'   => $idDireccion,
                'id_repartidor'  => null,
                'ventana_inicio' => $ventana !== null ? '08:00:00' : null,
                'ventana_fin'    => $ventana !== null ? $ventana . ':00' : null,
            ]);
            $idPedido = (int) $db->insertID();

            foreach ($lineas as [$ip, $kilos]) {
                $db->table('producto_pedido')->insert([
                    'cant'         => $kilos,
                    'precio_venta' => $productos[$ip]['venta'],
                    'unidad_venta' => 'Kilogramo',
                    'total'        => $kilos * $productos[$ip]['venta'], // el trigger calcula_total lo recalcula
                    'id_pedido'    => $idPedido,
                    'id_producto'  => $productos[$ip]['id'],
                ]);
            }

            // Mismo recorrido que en la operación: bloquea, confirma y consume la existencia
            $base = strtotime($hoy . ' 07:00:00') + $i * 60;
            foreach (['pedido_realizado' => 0, 'pedido_confirmado' => 15, 'pedido_en_transito' => 30] as $estado => $seg) {
                $db->table('estatus')->insert([
                    'estado'    => $estado,
                    'fecha'     => date('Y-m-d H:i:s', $base + $seg),
                    'id_pedido' => $idPedido,
                ]);
            }

            $pedidos[] = $idPedido;
        }

        $db->transComplete();

        $this->escribir(sprintf(
            'Rutas-test: %d entradas, %d clientes, %d pedidos en tránsito (#%d a #%d). Ábrelos en /rutas.',
            count($productos),
            count($clientes),
            count($pedidos),
            min($pedidos),
            max($pedidos)
        ));
    }

    /** Id del producto por código (o nombre); si no existe lo crea. */
    private function idProducto(array $p): int
    {
        $fila = $this->db->table('producto')->select('id')->where('codigo', $p['codigo'])->get()->getRowArray()
            ?? $this->db->table('producto')->select('id')->where('nombre', $p['nombre'])->get()->getRowArray();

        if ($fila) {
            return (int) $fila['id'];
        }

        $this->db->table('producto')->insert([
            'nombre'      => $p['nombre'],
            'descripcion' => 'Creado por el seeder Rutas-test',
            'img'         => '',
            'categoria'   => $p['categoria'],
            'codigo'      => $p['codigo'],
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Cliente (se reconoce por teléfono) con su dirección principal ubicada.
     *
     * @return array{0:int,1:int} [id_cliente, id_direccion]
     */
    private function cliente(array $c): array
    {
        [$nombre, $apePat, $apeMat, $tel, $pref, $municipio, $colonia, $calle, $num, $lat, $lng] = $c;

        $fila = $this->db->table('cliente')->select('id')->where('telefono', $tel)->get()->getRowArray();
        if ($fila) {
            $idCliente = (int) $fila['id'];
        } else {
            $this->db->table('cliente')->insert([
                'nombre'              => $nombre,
                'ape_pat'             => $apePat,
                'ape_mat'             => $apeMat,
                'telefono'            => $tel,
                'preferencial_modo'   => $pref,
                'preferencial_motivo' => $pref === 'si' ? 'Cliente de prueba (Rutas-test)' : null,
                'preferencial_fecha'  => $pref === 'si' ? date('Y-m-d H:i:s') : null,
                'preferencial_por'    => $pref === 'si' ? 'Seeder Rutas-test' : null,
            ]);
            $idCliente = (int) $this->db->insertID();
        }

        $dir = $this->db->table('direccion')->select('id')
            ->where('id_cliente', $idCliente)->where('lat IS NOT NULL')
            ->orderBy('es_principal', 'DESC')->orderBy('id', 'DESC')
            ->get()->getRowArray();
        if ($dir) {
            return [$idCliente, (int) $dir['id']];
        }

        $this->db->table('direccion')->insert([
            'colonia'              => $colonia,
            'calle'                => $calle,
            'numero'               => $num,
            'codigo_postal'        => null,
            'municipio'            => $municipio,
            'estado'               => 'Puebla',
            'id_cliente'           => $idCliente,
            'referencias'          => 'Dirección de prueba (Rutas-test)',
            'direccion_formateada' => "{$calle} {$num}, {$colonia}, {$municipio}, Puebla",
            'lat'                  => $lat,
            'lng'                  => $lng,
            'origen_coord'         => 'manual',
            'precision_geo'        => 'MANUAL',
            'coord_actualizada_en' => date('Y-m-d H:i:s'),
            'es_principal'         => 1,
        ]);

        return [$idCliente, (int) $this->db->insertID()];
    }

    private function escribir(string $texto): void
    {
        if (is_cli() && ! $this->silent) {
            CLI::write($texto, 'yellow');
        }
    }
}
