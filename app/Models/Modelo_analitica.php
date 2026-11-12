<?php

namespace App\Models;

use Throwable;

/**
 * Analíticas del inicio del personal: lo que ayuda a decidir qué comprar, qué precio
 * revisar, dónde se pierde dinero y a quién invitar a volver. Solo lectura.
 *
 *  - Costo de lo vendido: por cada renglón vendido se toma la entrada del mismo producto
 *    (y la misma unidad de venta) más cercana ANTES de la fecha del pedido; si no hay, la
 *    más reciente. Su costo por unidad de venta lo da Modelo_entrada::sqlCostoUnidadVenta().
 *    Si no hay entrada que coincida, el costo queda "sin registrar" y no se inventa.
 *  - Los pedidos cancelados no cuentan como venta.
 *  - Las listas por producto usan los últimos 30 días; si en ese tiempo no hubo movimiento,
 *    usan todo el historial y lo dicen ('periodo' => 'historico').
 */
class Modelo_analitica extends PdoModelo
{
    public const DIAS = 30;

    /** Ritmo de venta para reabastecer: últimos 90 días. */
    public const DIAS_REABASTO = 90;

    /** Último estatus del pedido p. */
    private const SQL_ESTADO = '(SELECT s.estado FROM estatus s WHERE s.id_pedido = p.id ORDER BY s.id DESC LIMIT 1)';

    /** El pedido p no está cancelado. */
    private const SQL_NO_CANCELADO = "COALESCE((SELECT s.estado FROM estatus s WHERE s.id_pedido = p.id ORDER BY s.id DESC LIMIT 1), '') <> 'pedido_cancelado'";

    /** Costo de un renglón vendido pp del pedido p (null si no hay entrada que coincida). */
    private static function sqlCostoRenglon(): string
    {
        // "Ramos" y "Ramo", "Kilogramo" y "kilogramo": misma unidad.
        $unidad = static fn (string $c): string => "LOWER(TRIM(TRAILING 's' FROM TRIM({$c})))";

        return '(pp.cant * (SELECT ' . Modelo_entrada::sqlCostoUnidadVenta('e2') . '
                              FROM entrada e2
                             WHERE e2.id_producto = pp.id_producto
                               AND (pp.unidad_venta IS NULL OR TRIM(pp.unidad_venta) = \'\'
                                    OR ' . $unidad('pp.unidad_venta') . ' = ' . $unidad('e2.u_venta') . ')
                          ORDER BY (e2.fecha <= p.fecha) DESC, e2.fecha DESC, e2.id DESC
                             LIMIT 1))';
    }

    // ─────────────────────────────────────────────────────────────
    // INDICADORES DE LA SEMANA
    // ─────────────────────────────────────────────────────────────

    /**
     * Ganancia estimada de la semana (lunes a hoy) contra el mismo tramo de la anterior.
     * Solo con lo que tiene costo registrado; 'sin_costo' es lo vendido sin costo conocido.
     *
     * @return array{actual:?float, anterior:?float, margen:?float, sin_costo:float}|null
     */
    public function gananciaSemana(): ?array
    {
        $hoy      = date('Y-m-d');
        $lunes    = date('Y-m-d', strtotime('monday this week'));
        $lunesAnt = date('Y-m-d', strtotime($lunes . ' -7 days'));
        $hace7    = date('Y-m-d', strtotime('-7 days'));

        $f = $this->intentar(fn () => $this->fila(
            'SELECT SUM(CASE WHEN x.actual = 1 AND x.costo IS NOT NULL THEN x.venta - x.costo END) AS ganancia,
                    SUM(CASE WHEN x.actual = 1 AND x.costo IS NOT NULL THEN x.venta END)           AS ventas_con_costo,
                    SUM(CASE WHEN x.actual = 1 AND x.costo IS NULL THEN x.venta END)               AS sin_costo,
                    SUM(CASE WHEN x.actual = 0 AND x.costo IS NOT NULL THEN x.venta - x.costo END) AS ganancia_ant
               FROM (SELECT (p.fecha >= ?) AS actual, pp.total AS venta, ' . self::sqlCostoRenglon() . ' AS costo
                       FROM pedido p
                       JOIN producto_pedido pp ON pp.id_pedido = p.id
                      WHERE (p.fecha BETWEEN ? AND ? OR p.fecha BETWEEN ? AND ?)
                        AND ' . self::SQL_NO_CANCELADO . ') x',
            [$lunes, $lunes, $hoy, $lunesAnt, $hace7]
        ));

        if ($f === null) {
            return null;
        }

        $ventasConCosto = (float) ($f['ventas_con_costo'] ?? 0);

        return [
            'actual'    => $f['ganancia'] !== null ? (float) $f['ganancia'] : 0.0,
            'anterior'  => $f['ganancia_ant'] !== null ? (float) $f['ganancia_ant'] : 0.0,
            'margen'    => $ventasConCosto > 0 ? (float) $f['ganancia'] / $ventasConCosto * 100 : null,
            'sin_costo' => (float) ($f['sin_costo'] ?? 0),
        ];
    }

    /**
     * Dinero por cobrar: pedidos cuyo último estatus es "a crédito".
     *
     * @return array{monto:float, pedidos:int}|null
     */
    public function porCobrar(): ?array
    {
        $f = $this->intentar(fn () => $this->fila(
            'SELECT COUNT(DISTINCT p.id) AS pedidos, COALESCE(SUM(pp.total), 0) AS monto
               FROM pedido p
               JOIN producto_pedido pp ON pp.id_pedido = p.id
              WHERE ' . self::SQL_ESTADO . " = 'pedido_a_credito'"
        ));

        return $f === null ? null : ['monto' => (float) $f['monto'], 'pedidos' => (int) $f['pedidos']];
    }

    // ─────────────────────────────────────────────────────────────
    // GRÁFICAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Ventas ($) y número de pedidos de las últimas $semanas semanas, incluidas las que
     * no tuvieron movimiento (en cero), de la más antigua a la actual.
     *
     * @return list<array{inicio:string, ventas:float, pedidos:int}>
     */
    public function ventasSemanales(int $semanas = 8): array
    {
        $lunes  = strtotime('monday this week');
        $desde  = date('Y-m-d', strtotime('-' . ($semanas - 1) . ' weeks', $lunes));
        $salida = [];
        for ($i = $semanas - 1; $i >= 0; $i--) {
            $inicio          = date('Y-m-d', strtotime("-{$i} weeks", $lunes));
            $salida[$inicio] = ['inicio' => $inicio, 'ventas' => 0.0, 'pedidos' => 0];
        }

        $filas = $this->intentar(fn () => $this->filas(
            'SELECT DATE_SUB(p.fecha, INTERVAL WEEKDAY(p.fecha) DAY) AS inicio,
                    COUNT(*) AS pedidos, COALESCE(SUM(v.monto), 0) AS ventas
               FROM pedido p
          LEFT JOIN (SELECT id_pedido, SUM(total) AS monto FROM producto_pedido GROUP BY id_pedido) v ON v.id_pedido = p.id
              WHERE p.fecha >= ? AND ' . self::SQL_NO_CANCELADO . '
           GROUP BY inicio',
            [$desde]
        )) ?? [];

        foreach ($filas as $f) {
            if (isset($salida[$f['inicio']])) {
                $salida[$f['inicio']]['ventas']  = (float) $f['ventas'];
                $salida[$f['inicio']]['pedidos'] = (int) $f['pedidos'];
            }
        }

        return array_values($salida);
    }

    /**
     * Ganancia por producto: lo vendido, lo que costó y el margen. Ordenado por lo vendido.
     *
     * @return array{periodo:string, filas:list<array{nombre:string, ventas:float, costo:?float, ganancia:?float, margen:?float}>}
     */
    public function gananciaPorProducto(int $limite = 6): array
    {
        $consulta = fn (?string $desde) => $this->filas(
            'SELECT pr.nombre,
                    SUM(x.venta) AS ventas,
                    SUM(x.costo) AS costo,
                    SUM(CASE WHEN x.costo IS NULL THEN 1 ELSE 0 END) AS renglones_sin_costo,
                    COUNT(*) AS renglones
               FROM (SELECT pp.id_producto, pp.total AS venta, ' . self::sqlCostoRenglon() . ' AS costo
                       FROM pedido p
                       JOIN producto_pedido pp ON pp.id_pedido = p.id
                      WHERE ' . ($desde !== null ? 'p.fecha >= ? AND ' : '') . self::SQL_NO_CANCELADO . ') x
               JOIN producto pr ON pr.id = x.id_producto
           GROUP BY x.id_producto, pr.nombre
             HAVING SUM(x.venta) > 0
           ORDER BY ventas DESC
              LIMIT ' . max(1, $limite),
            $desde !== null ? [$desde] : []
        );

        [$periodo, $filas] = $this->conRespaldo($consulta);

        return [
            'periodo' => $periodo,
            'filas'   => array_map(static function (array $f): array {
                // Si una parte de lo vendido no tiene costo, la ganancia no se puede saber.
                $completo = (int) $f['renglones_sin_costo'] === 0;
                $ventas   = (float) $f['ventas'];
                $costo    = $completo ? (float) $f['costo'] : null;
                $ganancia = $costo !== null ? $ventas - $costo : null;

                return [
                    'nombre'   => (string) $f['nombre'],
                    'ventas'   => $ventas,
                    'costo'    => $costo,
                    'ganancia' => $ganancia,
                    'margen'   => $ganancia !== null && $ventas > 0 ? $ganancia / $ventas * 100 : null,
                ];
            }, $filas),
        ];
    }

    /**
     * Pérdida por merma por producto (costo de lo que se tiró). De mayor a menor.
     *
     * @return array{periodo:string, filas:list<array{nombre:string, perdida:float, cantidad:float, unidad:string}>}
     */
    public function mermaPorProducto(int $limite = 6): array
    {
        $consulta = fn (?string $desde) => $this->filas(
            'SELECT pr.nombre,
                    SUM(m.cantidad * ' . Modelo_entrada::sqlCostoUnidadVenta('e') . ') AS perdida,
                    SUM(m.cantidad) AS cantidad,
                    MAX(COALESCE(NULLIF(m.u_venta, \'\'), e.u_venta)) AS unidad
               FROM merma m
               JOIN entrada e   ON e.id = m.id_entrada
               JOIN producto pr ON pr.id = e.id_producto
              WHERE ' . ($desde !== null ? 'm.fecha >= ?' : '1 = 1') . '
           GROUP BY pr.id, pr.nombre
             HAVING perdida > 0
           ORDER BY perdida DESC
              LIMIT ' . max(1, $limite),
            $desde !== null ? [$desde] : []
        );

        [$periodo, $filas] = $this->conRespaldo($consulta);

        return [
            'periodo' => $periodo,
            'filas'   => array_map(static fn (array $f): array => [
                'nombre'   => (string) $f['nombre'],
                'perdida'  => (float) $f['perdida'],
                'cantidad' => (float) $f['cantidad'],
                'unidad'   => (string) ($f['unidad'] ?? ''),
            ], $filas),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // LISTAS PARA ACTUAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Qué reabastecer: productos que SÍ se venden, con lo disponible y para cuántos días
     * alcanza al ritmo de venta. Primero los agotados y los que alcanzan para menos días.
     *
     * @return array{periodo:string, filas:list<array{nombre:string, disponible:float, por_semana:float, dias:?float, unidad:string}>}
     */
    public function reabastecer(int $limite = 6): array
    {
        $consulta = fn (?string $desde) => $this->filas(
            'SELECT pr.id, pr.nombre,
                    SUM(pp.cant) AS vendido,
                    MIN(p.fecha) AS primera, MAX(p.fecha) AS ultima,
                    MAX(pp.unidad_venta) AS unidad
               FROM producto_pedido pp
               JOIN pedido p    ON p.id = pp.id_pedido
               JOIN producto pr ON pr.id = pp.id_producto
              WHERE pp.cant > 0 AND ' . ($desde !== null ? 'p.fecha >= ? AND ' : '') . self::SQL_NO_CANCELADO . '
           GROUP BY pr.id, pr.nombre',
            $desde !== null ? [$desde] : []
        );

        // 90 días: un producto agotado deja de venderse, y con 30 días desaparecería justo cuando urge.
        [$periodo, $filas] = $this->conRespaldo($consulta, self::DIAS_REABASTO);

        // Disponible para venta: la fila más reciente de existencia de cada producto.
        $stock = $this->intentar(fn () => array_column(
            $this->filas('SELECT x.id_producto, x.e_venta FROM existencia x
                           JOIN (SELECT id_producto, MAX(id) AS ultimo FROM existencia GROUP BY id_producto) u ON u.ultimo = x.id'),
            'e_venta',
            'id_producto'
        )) ?? [];

        $salida = [];
        foreach ($filas as $f) {
            // Semanas del periodo: 90 días, o del primer al último pedido en el historial (mínimo 1).
            $semanas = $periodo === 'reciente'
                ? self::DIAS_REABASTO / 7
                : max(1.0, (strtotime((string) $f['ultima']) - strtotime((string) $f['primera'])) / 86400 / 7);
            $porSemana  = (float) $f['vendido'] / $semanas;
            $disponible = (float) ($stock[$f['id']] ?? 0);

            $salida[] = [
                'nombre'     => (string) $f['nombre'],
                'disponible' => $disponible,
                'por_semana' => $porSemana,
                'dias'       => $porSemana > 0 ? $disponible / ($porSemana / 7) : null,
                'unidad'     => (string) ($f['unidad'] ?? ''),
            ];
        }

        usort($salida, static fn (array $a, array $b): int => [$a['dias'] ?? INF, -$a['por_semana']] <=> [$b['dias'] ?? INF, -$b['por_semana']]);

        return ['periodo' => $periodo, 'filas' => array_slice($salida, 0, max(1, $limite))];
    }

    /**
     * Clientes que compraron antes y no han vuelto en $dias días, de quien más ha comprado
     * a quien menos: a quiénes vale la pena invitar (Comunicaciones · V2 recompra).
     *
     * @return list<array{nombre:string, ultimo:string, dias:int, pedidos:int, total:float}>
     */
    public function clientesSinVolver(int $dias = self::DIAS, int $limite = 5): array
    {
        $filas = $this->intentar(fn () => $this->filas(
            'SELECT c.nombre, c.ape_pat, MAX(p.fecha) AS ultimo, COUNT(DISTINCT p.id) AS pedidos,
                    COALESCE(SUM(v.monto), 0) AS total
               FROM pedido p
               JOIN cliente c ON c.id = p.id_cliente
          LEFT JOIN (SELECT id_pedido, SUM(total) AS monto FROM producto_pedido GROUP BY id_pedido) v ON v.id_pedido = p.id
              WHERE ' . self::SQL_NO_CANCELADO . '
           GROUP BY c.id, c.nombre, c.ape_pat
             HAVING MAX(p.fecha) < ?
           ORDER BY total DESC, ultimo DESC
              LIMIT ' . max(1, $limite),
            [date('Y-m-d', strtotime("-{$dias} days"))]
        )) ?? [];

        return array_map(static fn (array $f): array => [
            'nombre'  => trim((string) $f['nombre'] . ' ' . (string) ($f['ape_pat'] ?? '')),
            'ultimo'  => (string) $f['ultimo'],
            'dias'    => (int) floor((strtotime(date('Y-m-d')) - strtotime((string) $f['ultimo'])) / 86400),
            'pedidos' => (int) $f['pedidos'],
            'total'   => (float) $f['total'],
        ], $filas);
    }

    // ─────────────────────────────────────────────────────────────
    // APOYO
    // ─────────────────────────────────────────────────────────────

    /**
     * Corre la consulta con los últimos $dias días; si no hay nada, con todo el historial.
     *
     * @param callable(?string):list<array<string,mixed>> $consulta
     *
     * @return array{0:string, 1:list<array<string,mixed>>} ['reciente'|'historico'|'sin_datos', filas]
     */
    private function conRespaldo(callable $consulta, int $dias = self::DIAS): array
    {
        $recientes = $this->intentar(fn () => $consulta(date('Y-m-d', strtotime('-' . $dias . ' days')))) ?? [];
        if ($recientes !== []) {
            return ['reciente', $recientes];
        }

        $todo = $this->intentar(fn () => $consulta(null)) ?? [];

        return [$todo !== [] ? 'historico' : 'sin_datos', $todo];
    }

    /** Ejecuta la consulta; si falla (tabla aún no creada), deja constancia en el log y devuelve null. */
    private function intentar(callable $consulta): mixed
    {
        try {
            return $consulta();
        } catch (Throwable $e) {
            log_message('info', 'Analítica: dato omitido: ' . $e->getMessage());

            return null;
        }
    }
}
