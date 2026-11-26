<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Datos de vitrina para la demostración ante el cliente ("Demo-vitrina").
 *
 *   php spark db:seed DemoVitrina
 *
 * Genera, siguiendo el mismo flujo que la aplicación (los triggers de MySQL mueven la
 * existencia y calculan totales/caducidades), un catálogo amplio y un historial que hace
 * lucir el inicio del personal (ganancias, ventas por semana, merma, reabasto, clientes)
 * y la pantalla de Entregas (pedidos por asignar repartidos en varios estados):
 *
 *   1. ~30 productos con su entrada (stock alto; unos pocos con poco stock para "reabastecer").
 *   2. ~21 clientes en 7 estados distintos (clave para agrupar las entregas por estado),
 *      algunos preferenciales y algunos "dormidos" (no vuelven, para "clientes sin volver").
 *   3. Historial de pedidos entregados de 8 semanas, con más venta en la semana actual que en
 *      la anterior (para que la ganancia salga "al alza").
 *   4. Mermas de las últimas semanas (pérdida por producto y comparación semanal).
 *   5. Pedidos "por asignar" (confirmados, sin repartidor) en cada estado, más algunos por
 *      confirmar y a crédito, para que los indicadores de "atención hoy" tengan datos.
 *
 * Reusa productos (por código) y clientes (por teléfono) si ya existen; las entradas,
 * pedidos y mermas se crean en cada corrida (es data de demo; correrlo otra vez agrega más).
 */
class DemoVitrina extends Seeder
{
    /** [código, nombre, descripción, categoría, u_compra, equivalente, $compra, $venta, unidad_medida, cajas]. */
    private const PRODUCTOS = [
        ['DEMO-MANZANA',  'Manzana roja',        'Manzana roja de temporada, crujiente y dulce', 'frutas',   'Caja',    18, 540, 46, 'kg',    24],
        ['DEMO-PLATANO',  'Plátano Tabasco',     'Plátano maduro, ideal para toda la familia',    'frutas',   'Caja',    25, 250, 18, 'kg',    30],
        ['DEMO-NARANJA',  'Naranja Valencia',    'Naranja jugosa para jugo o mesa',               'frutas',   'Arpilla', 40, 360, 15, 'kg',    26],
        ['DEMO-LIMON',    'Limón sin semilla',   'Limón verde sin semilla, muy rendidor',         'frutas',   'Arpilla', 25, 300, 24, 'kg',     8],
        ['DEMO-PAPAYA',   'Papaya Maradol',      'Papaya Maradol en su punto',                    'frutas',   'Caja',    15, 225, 24, 'kg',    20],
        ['DEMO-PINA',     'Piña miel',           'Piña miel, dulce y aromática',                  'frutas',   'Caja',    12, 180, 28, 'pieza', 18],
        ['DEMO-SANDIA',   'Sandía',              'Sandía fresca sin semilla',                     'frutas',   'Bulto',   30, 300, 14, 'kg',    22],
        ['DEMO-UVA',      'Uva verde',           'Uva verde sin semilla',                         'frutas',   'Caja',    10, 450, 58, 'kg',    14],
        ['DEMO-FRESA',    'Fresa',               'Fresa de Irapuato, seleccionada',               'frutas',   'Caja',     8, 320, 55, 'kg',     7],
        ['DEMO-GUAYABA',  'Guayaba',             'Guayaba fresca, aroma intenso',                 'frutas',   'Caja',    12, 180, 28, 'kg',    16],
        ['DEMO-MELON',    'Melón Cantaloupe',    'Melón Cantaloupe dulce',                        'frutas',   'Caja',    15, 225, 22, 'kg',    18],

        ['DEMO-CEBOLLA',  'Cebolla blanca',      'Cebolla blanca de primera',                     'verduras', 'Bulto',   25, 250, 16, 'kg',    28],
        ['DEMO-AJO',      'Ajo',                 'Ajo blanco en cabeza',                          'verduras', 'Caja',    10, 400, 70, 'kg',    12],
        ['DEMO-CHILESER', 'Chile serrano',       'Chile serrano fresco',                          'verduras', 'Arpilla', 20, 300, 30, 'kg',    18],
        ['DEMO-CHILEPOB', 'Chile poblano',       'Chile poblano para rellenar',                   'verduras', 'Arpilla', 18, 270, 28, 'kg',    16],
        ['DEMO-CALABAZA', 'Calabaza italiana',   'Calabaza italiana tierna',                      'verduras', 'Arpilla', 20, 200, 18, 'kg',    20],
        ['DEMO-PEPINO',   'Pepino',              'Pepino verde para ensalada',                    'verduras', 'Arpilla', 22, 220, 15, 'kg',    20],
        ['DEMO-LECHUGA',  'Lechuga romana',      'Lechuga romana fresca',                         'verduras', 'Caja',    24, 240, 16, 'pieza', 22],
        ['DEMO-ESPINACA', 'Espinaca',            'Espinaca de hoja tierna',                       'verduras', 'Caja',    12, 180, 26, 'kg',    14],
        ['DEMO-NOPAL',    'Nopal',               'Nopal limpio sin espinas',                      'verduras', 'Arpilla', 30, 150, 12, 'kg',    18],
        ['DEMO-BETABEL',  'Betabel',             'Betabel fresco',                                'verduras', 'Arpilla', 20, 180, 16, 'kg',    16],
        ['DEMO-COLIFLOR', 'Coliflor',            'Coliflor blanca y firme',                       'verduras', 'Caja',    16, 240, 24, 'pieza', 16],
        ['DEMO-EJOTE',    'Ejote',               'Ejote tierno seleccionado',                     'verduras', 'Arpilla', 15, 300, 32, 'kg',     9],
        ['DEMO-TOMBOLA',  'Tomate bola',         'Tomate bola rojo y firme',                      'verduras', 'Caja',    20, 260, 24, 'kg',    24],

        ['DEMO-CILANTRO', 'Cilantro',            'Cilantro fresco en manojo',                     'hierbas',  'Mazo',    30,  60,  9, 'ramo',  10],
        ['DEMO-PEREJIL',  'Perejil',             'Perejil liso en manojo',                        'hierbas',  'Mazo',    30,  60,  9, 'ramo',  10],
        ['DEMO-EPAZOTE',  'Epazote',             'Epazote fresco para guisos',                    'hierbas',  'Mazo',    25,  50,  8, 'ramo',   8],
        ['DEMO-MENTA',    'Menta',               'Menta fresca aromática',                        'hierbas',  'Mazo',    20,  50, 10, 'ramo',   8],
        ['DEMO-ALBAHACA', 'Albahaca',            'Albahaca fresca de olor',                       'hierbas',  'Mazo',    20,  60, 13, 'ramo',   8],
        ['DEMO-ROMERO',   'Romero',              'Romero fresco en manojo',                       'hierbas',  'Mazo',    20,  60, 14, 'ramo',   8],
    ];

    /**
     * Clientes en varios estados (para agrupar entregas por estado).
     * [nombre, ape_pat, ape_mat, telefono, pref(si/no), estado, municipio, colonia, calle, num, lat, lng, dormido].
     */
    private const CLIENTES = [
        // Puebla
        ['Rosa',      'Hernández', 'Nava',     '2287000001', 'si', 'Puebla',   'Puebla',       'Centro',        'Av. Reforma',        '120', 19.0414, -98.2063, false],
        ['Mariana',   'Vega',      'Luna',     '2287000002', 'no', 'Puebla',   'Cholula',      'San Andrés',    'Calle 14 Oriente',   '33',  19.0600, -98.3000, false],
        ['Esteban',   'Ríos',      'Mora',     '2287000003', 'no', 'Puebla',   'Atlixco',      'Centro',        'Av. Hidalgo',        '8',   18.9080, -98.4360, true],
        // Tlaxcala
        ['Lucía',     'Campos',    'Rojas',    '2287000004', 'si', 'Tlaxcala', 'Tlaxcala',     'Centro',        'Av. Juárez',         '45',  19.3139, -98.2404, false],
        ['Hugo',      'Salas',     'Prieto',   '2287000005', 'no', 'Tlaxcala', 'Apizaco',      'La Loma',       'Calle 5 de Mayo',    '210', 19.4130, -98.1440, false],
        ['Paola',     'Mena',      'Cid',      '2287000006', 'no', 'Tlaxcala', 'Huamantla',    'Centro',        'Calle Allende',      '14',  19.3130, -97.9230, false],
        // Veracruz
        ['Gabriel',   'Fuentes',   'Lara',     '2287000007', 'no', 'Veracruz', 'Xalapa',       'Centro',        'Calle Enríquez',     '77',  19.5438, -96.9102, false],
        ['Daniela',   'Ponce',     'Rubio',    '2287000008', 'si', 'Veracruz', 'Córdoba',      'Centro',        'Av. 1',              '305', 18.8940, -96.9340, false],
        ['Raúl',      'Cabrera',   'Nieto',    '2287000009', 'no', 'Veracruz', 'Orizaba',      'Centro',        'Calle Oriente 6',    '52',  18.8510, -97.0990, true],
        // Hidalgo
        ['Verónica',  'Guzmán',    'Soto',     '2287000010', 'no', 'Hidalgo',  'Pachuca',      'Centro',        'Av. Revolución',     '90',  20.1011, -98.7591, false],
        ['Andrés',    'Peña',      'Vera',     '2287000011', 'si', 'Hidalgo',  'Tulancingo',   'Centro',        'Calle Juárez',       '18',  20.0830, -98.3680, false],
        ['Karla',     'Mejía',     'Ruiz',     '2287000012', 'no', 'Hidalgo',  'Tizayuca',     'Centro',        'Av. Hidalgo',        '240', 19.8400, -98.9800, false],
        // México
        ['Sergio',    'Ortiz',     'Gil',      '2287000013', 'no', 'México',   'Toluca',       'Centro',        'Av. Independencia',  '64',  19.2826, -99.6557, false],
        ['Brenda',    'Navarro',   'Téllez',   '2287000014', 'no', 'México',   'Texcoco',      'Centro',        'Calle Fray Pedro',   '11',  19.5150, -98.8830, false],
        ['Iván',      'Robles',    'Paz',      '2287000015', 'si', 'México',   'Ecatepec',     'San Cristóbal', 'Av. Central',        '880', 19.6010, -99.0600, true],
        // Morelos
        ['Natalia',   'Cortés',    'Haro',     '2287000016', 'no', 'Morelos',  'Cuernavaca',   'Centro',        'Av. Morelos',        '150', 18.9242, -99.2216, false],
        ['Diego',     'Lozano',    'Vela',     '2287000017', 'si', 'Morelos',  'Cuautla',      'Centro',        'Calle Galeana',      '27',  18.8120, -98.9540, false],
        ['Mónica',    'Arias',     'Bravo',    '2287000018', 'no', 'Morelos',  'Jiutepec',     'Centro',        'Av. Tejalpa',        '63',  18.8810, -99.1790, false],
        // Oaxaca
        ['Felipe',    'Reyna',     'Cano',     '2287000019', 'no', 'Oaxaca',   'Oaxaca de Juárez', 'Centro',    'Calle Macedonio',    '40',  17.0732, -96.7266, false],
        ['Alejandra', 'Macías',    'Solís',    '2287000020', 'si', 'Oaxaca',   'Huajuapan',    'Centro',        'Av. Morelos',        '9',   17.8060, -97.7790, false],
        ['Tomás',     'Vargas',    'Rico',     '2287000021', 'no', 'Oaxaca',   'Tuxtepec',     'Centro',        'Av. Independencia',  '115', 18.0870, -96.1240, true],
    ];

    public function run()
    {
        $db  = $this->db;
        $hoy = date('Y-m-d');
        mt_srand(20261126); // reproducible

        $db->query("SET @fruver_id_user = NULL, @fruver_usuario = 'Seeder Demo-vitrina'");
        $db->transException(true)->transStart();

        // 1) Productos + entradas (stock)
        $P = [];
        foreach (self::PRODUCTOS as $p) {
            $id  = $this->idProducto($p);
            $ent = $this->entrada($id, $p, (int) $p[9], date('Y-m-d', strtotime("-{$this->entre(2, 14)} days")));
            $P[] = ['id' => $id, 'venta' => (int) $p[7], 'uv' => $this->unidadPedido($p[8]), 'um' => $p[8], 'ent' => $ent, 'nombre' => $p[1]];
        }

        // Unas entradas por caducar (fecha_cad en los próximos 2 días) para "atención hoy"
        foreach ([0, 5, 12] as $idx) {
            $pp = self::PRODUCTOS[$idx];
            $this->entrada($P[$idx]['id'], $pp, 3, $hoy, date('Y-m-d', strtotime('+1 day')));
        }

        // 2) Clientes por estado
        $porEstado = [];
        $activos   = [];
        $dormidos  = [];
        foreach (self::CLIENTES as $c) {
            [$idC, $idD]        = $this->clienteDemo($c);
            $porEstado[$c[5]][] = [$idC, $idD];
            if ($c[12]) {
                $dormidos[] = [$idC, $idD];
            } else {
                $activos[] = [$idC, $idD];
            }
        }

        // 3) Historial de pedidos entregados (8 semanas; más en la semana actual que en la anterior)
        $lunes    = strtotime('monday this week');
        $histBase = [7 => 6, 6 => 6, 5 => 7, 4 => 7, 3 => 8, 2 => 9, 1 => 9, 0 => 12];
        $pedHist  = 0;
        foreach ($histBase as $w => $cuantos) {
            for ($n = 0; $n < $cuantos; $n++) {
                [$idC, $idD] = $activos[array_rand($activos)];
                $fecha       = $this->diaEnSemana($lunes, $w);
                $this->pedido($idC, $idD, $fecha, $this->lineas($P, 2, 4), [['pedido_entregado', $fecha . ' 13:00:00']]);
                $pedHist++;
            }
        }

        // Clientes "dormidos": solo pedidos viejos (para "clientes sin volver")
        foreach ($dormidos as [$idC, $idD]) {
            $fecha = $this->diaEnSemana($lunes, 7);
            $this->pedido($idC, $idD, $fecha, $this->lineas($P, 2, 3), [['pedido_entregado', $fecha . ' 12:00:00']]);
            $pedHist++;
        }

        // 4) Mermas de las últimas semanas (pérdida por producto y comparación semanal)
        $mermas = 0;
        for ($k = 0; $k < 18; $k++) {
            $prod  = $P[array_rand($P)];
            $fecha = date('Y-m-d', strtotime("-{$this->entre(0, 20)} days"));
            $db->table('merma')->insert([
                'cantidad'   => $this->entre(2, 8),
                'fecha'      => $fecha,
                'notas'      => 'Merma de demostración (maniobra/daño)',
                'id_entrada' => $prod['ent'],
                'u_venta'    => $this->unidadEntrada($prod['um']),
            ]);
            $mermas++;
        }

        // 5) Pedidos "por asignar" (confirmados, sin repartidor) en cada estado
        $porAsignar = 0;
        foreach ($porEstado as $lista) {
            $cuantos = min(count($lista), $this->entre(2, 3));
            for ($n = 0; $n < $cuantos; $n++) {
                [$idC, $idD] = $lista[$n % count($lista)];
                $base        = strtotime($hoy . ' 08:00:00') + $porAsignar * 90;
                $this->pedido($idC, $idD, $hoy, $this->lineas($P, 2, 4), [
                    ['pedido_realizado',  date('Y-m-d H:i:s', $base)],
                    ['pedido_confirmado', date('Y-m-d H:i:s', $base + 30)],
                ]);
                $porAsignar++;
            }
        }

        // Algunos por confirmar (último estatus: realizado) y a crédito (por cobrar)
        for ($n = 0; $n < 3; $n++) {
            [$idC, $idD] = $activos[array_rand($activos)];
            $this->pedido($idC, $idD, $hoy, $this->lineas($P, 2, 3), [['pedido_realizado', date('Y-m-d H:i:s', strtotime($hoy . ' 09:00:00') + $n * 60)]]);
        }
        for ($n = 0; $n < 3; $n++) {
            [$idC, $idD] = $activos[array_rand($activos)];
            $fecha = date('Y-m-d', strtotime("-{$this->entre(1, 5)} days"));
            $this->pedido($idC, $idD, $fecha, $this->lineas($P, 2, 4), [['pedido_a_credito', $fecha . ' 15:00:00']]);
        }

        $db->transComplete();

        $this->escribir(sprintf(
            'Demo-vitrina: %d productos, %d clientes en %d estados, %d pedidos de historial, %d mermas, %d por asignar (en varios estados) + por confirmar y a crédito.',
            count($P),
            count(self::CLIENTES),
            count($porEstado),
            $pedHist,
            $mermas,
            $porAsignar
        ));
    }

    // ───────────────────────── Apoyo ─────────────────────────

    /** Entero aleatorio inclusivo. */
    private function entre(int $a, int $b): int
    {
        return mt_rand($a, $b);
    }

    private function unidadEntrada(string $um): string
    {
        return ['kg' => 'Kilogramo', 'pieza' => 'Pieza', 'ramo' => 'Ramo', 'domo' => 'Domo'][$um] ?? 'Kilogramo';
    }

    private function unidadPedido(string $um): string
    {
        return ['kg' => 'Kilogramo', 'pieza' => 'Pieza', 'ramo' => 'Ramos', 'domo' => 'Domo'][$um] ?? 'Kilogramo';
    }

    /** Un día (lun–sáb) de la semana que empieza $w semanas antes de $lunes, sin pasar de hoy. */
    private function diaEnSemana(int $lunes, int $w): string
    {
        $inicio = strtotime("-{$w} weeks", $lunes);
        $dia    = $inicio + $this->entre(0, 5) * 86400;

        return date('Y-m-d', min($dia, time()));
    }

    /** Renglones aleatorios de productos distintos: [idProducto, venta, unidad, cantidad]. */
    private function lineas(array $P, int $min, int $max): array
    {
        $claves = array_rand($P, min($this->entre($min, $max), count($P)));
        $claves = is_array($claves) ? $claves : [$claves];

        $lineas = [];
        foreach ($claves as $i) {
            $lineas[] = [$P[$i]['id'], $P[$i]['venta'], $P[$i]['uv'], $this->entre(2, 14)];
        }

        return $lineas;
    }

    /** Id del producto por código; si no existe lo crea. */
    private function idProducto(array $p): int
    {
        $fila = $this->db->table('producto')->select('id')->where('codigo', $p[0])->get()->getRowArray()
            ?? $this->db->table('producto')->select('id')->where('nombre', $p[1])->get()->getRowArray();

        if ($fila) {
            return (int) $fila['id'];
        }

        $this->db->table('producto')->insert([
            'nombre'      => $p[1],
            'descripcion' => $p[2],
            'img'         => '',
            'categoria'   => $p[3],
            'codigo'      => $p[0],
        ]);

        return (int) $this->db->insertID();
    }

    /** Inserta una entrada (el trigger calcula conversión, caducidad y suma la existencia). Devuelve su id. */
    private function entrada(int $idProducto, array $p, int $cajas, string $fecha, ?string $fechaCad = null): int
    {
        $this->db->table('entrada')->insert([
            'fecha'           => $fecha,
            'fecha_cad'       => $fechaCad,
            'cantidad'        => $cajas,
            'u_compra'        => $p[4],
            'u_venta'         => $this->unidadEntrada($p[8]),
            'equivalente'     => (int) $p[5],
            'precio_compra_u' => (int) $p[6],
            'precio_venta_u'  => (int) $p[7],
            'id_producto'     => $idProducto,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Cliente (se reconoce por teléfono) con su dirección principal ubicada.
     *
     * @return array{0:int,1:int} [id_cliente, id_direccion]
     */
    private function clienteDemo(array $c): array
    {
        [$nombre, $apePat, $apeMat, $tel, $pref, $estado, $municipio, $colonia, $calle, $num, $lat, $lng] = $c;

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
                'preferencial_motivo' => $pref === 'si' ? 'Cliente frecuente' : null,
                'preferencial_fecha'  => $pref === 'si' ? date('Y-m-d H:i:s') : null,
                'preferencial_por'    => $pref === 'si' ? 'Seeder Demo-vitrina' : null,
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
            'estado'               => $estado,
            'id_cliente'           => $idCliente,
            'referencias'          => 'Dirección de demostración',
            'direccion_formateada' => "{$calle} {$num}, {$colonia}, {$municipio}, {$estado}",
            'lat'                  => $lat,
            'lng'                  => $lng,
            'origen_coord'         => 'manual',
            'precision_geo'        => 'MANUAL',
            'coord_actualizada_en' => date('Y-m-d H:i:s'),
            'es_principal'         => 1,
        ]);

        return [$idCliente, (int) $this->db->insertID()];
    }

    /**
     * Pedido con sus renglones y su recorrido de estatus.
     *
     * @param list<array{0:int,1:int,2:string,3:int}> $lineas  [idProducto, precioVenta, unidad, cantidad]
     * @param list<array{0:string,1:string}>          $estados [estado, 'Y-m-d H:i:s']
     */
    private function pedido(int $idCliente, int $idDireccion, string $fecha, array $lineas, array $estados): int
    {
        $db = $this->db;

        $db->table('pedido')->insert([
            'fecha'         => $fecha,
            'id_cliente'    => $idCliente,
            'id_direccion'  => $idDireccion,
            'id_repartidor' => null,
        ]);
        $idPedido = (int) $db->insertID();

        foreach ($lineas as [$idProd, $venta, $unidad, $cant]) {
            $db->table('producto_pedido')->insert([
                'cant'         => $cant,
                'precio_venta' => $venta,
                'unidad_venta' => $unidad,
                'id_pedido'    => $idPedido,
                'id_producto'  => $idProd,
            ]);
        }

        foreach ($estados as [$estado, $cuando]) {
            $db->table('estatus')->insert([
                'estado'    => $estado,
                'fecha'     => $cuando,
                'id_pedido' => $idPedido,
            ]);
        }

        return $idPedido;
    }

    private function escribir(string $texto): void
    {
        if (is_cli() && ! $this->silent) {
            CLI::write($texto, 'yellow');
        }
    }
}
