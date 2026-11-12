<?php

namespace App\Models;

use Throwable;

/**
 * Datos del inicio del personal: indicadores contra la semana anterior y lo que
 * requiere atención hoy. Solo lectura.
 *
 * Cada consulta va aparte y protegida: si falta una tabla de una migración que aún
 * no se corrió (entrega, sugerencia...), ese dato vale null y el inicio sigue funcionando.
 */
class Modelo_resumen extends PdoModelo
{
    /** Pedidos cuyo último estatus es este estado (subconsulta reutilizable). */
    private const SQL_ULTIMO_ESTADO = '(SELECT s.estado FROM estatus s WHERE s.id_pedido = p.id ORDER BY s.id DESC LIMIT 1)';

    /**
     * @return array{
     *   pedidos_hoy:?int, pedidos_hace_7:?int,
     *   ventas_semana:?float, ventas_semana_ant:?float,
     *   merma_semana:?float, merma_semana_ant:?float,
     *   por_caducar:list<array{id:int,nombre:string,fecha_cad:string}>,
     *   por_confirmar:?int, sin_repartidor:?int, fallidas_hoy:?int, sugerencias_7d:?int
     * }
     */
    public function inicio(): array
    {
        $hoy       = date('Y-m-d');
        $hace7     = date('Y-m-d', strtotime('-7 days'));
        $lunes     = date('Y-m-d', strtotime('monday this week'));
        $lunesAnt  = date('Y-m-d', strtotime($lunes . ' -7 days'));

        $pedidos = $this->intentar(fn () => $this->fila(
            'SELECT SUM(fecha = ?) AS hoy, SUM(fecha = ?) AS hace7 FROM pedido WHERE fecha IN (?, ?)',
            [$hoy, $hace7, $hoy, $hace7]
        ));

        // Semana actual (lunes a hoy) contra el mismo tramo de la semana anterior.
        $ventas = $this->intentar(fn () => $this->fila(
            'SELECT COALESCE(SUM(CASE WHEN p.fecha >= ? THEN v.monto END), 0) AS actual,
                    COALESCE(SUM(CASE WHEN p.fecha <  ? THEN v.monto END), 0) AS anterior
               FROM pedido p
               JOIN (SELECT id_pedido, SUM(COALESCE(total, cant * precio_venta)) AS monto
                       FROM producto_pedido GROUP BY id_pedido) v ON v.id_pedido = p.id
              WHERE (p.fecha BETWEEN ? AND ? OR p.fecha BETWEEN ? AND ?)
                AND COALESCE(' . self::SQL_ULTIMO_ESTADO . ", '') <> 'pedido_cancelado'",
            [$lunes, $lunes, $lunes, $hoy, $lunesAnt, $hace7]
        ));

        $merma = $this->intentar(fn () => $this->fila(
            'SELECT COALESCE(SUM(CASE WHEN m.fecha >= ? THEN m.cantidad * ' . Modelo_entrada::sqlCostoUnidadVenta() . ' END), 0) AS actual,
                    COALESCE(SUM(CASE WHEN m.fecha <  ? THEN m.cantidad * ' . Modelo_entrada::sqlCostoUnidadVenta() . ' END), 0) AS anterior
               FROM merma m
               JOIN entrada e ON e.id = m.id_entrada
              WHERE m.fecha BETWEEN ? AND ? OR m.fecha BETWEEN ? AND ?',
            [$lunes, $lunes, $lunes, $hoy, $lunesAnt, $hace7]
        ));

        $porCaducar = $this->intentar(fn () => $this->filas(
            'SELECT en.id, pr.nombre, en.fecha_cad
               FROM entrada en
               JOIN producto pr ON pr.id = en.id_producto
              WHERE en.fecha_cad BETWEEN ? AND ?
           ORDER BY en.fecha_cad, pr.nombre
              LIMIT 20',
            [$hoy, date('Y-m-d', strtotime('+2 days'))]
        )) ?? [];

        return [
            'pedidos_hoy'       => isset($pedidos['hoy']) ? (int) $pedidos['hoy'] : ($pedidos !== null ? 0 : null),
            'pedidos_hace_7'    => isset($pedidos['hace7']) ? (int) $pedidos['hace7'] : ($pedidos !== null ? 0 : null),
            'ventas_semana'     => $ventas !== null ? (float) $ventas['actual'] : null,
            'ventas_semana_ant' => $ventas !== null ? (float) $ventas['anterior'] : null,
            'merma_semana'      => $merma !== null ? (float) $merma['actual'] : null,
            'merma_semana_ant'  => $merma !== null ? (float) $merma['anterior'] : null,
            'por_caducar'       => array_map(static fn (array $f): array => [
                'id' => (int) $f['id'], 'nombre' => (string) $f['nombre'], 'fecha_cad' => (string) $f['fecha_cad'],
            ], $porCaducar),
            'por_confirmar'     => $this->contar('SELECT COUNT(*) FROM pedido p WHERE ' . self::SQL_ULTIMO_ESTADO . " = 'pedido_realizado'"),
            'sin_repartidor'    => $this->contar(
                'SELECT COUNT(*) FROM pedido p
                  WHERE ' . self::SQL_ULTIMO_ESTADO . " = 'pedido_confirmado'
                    AND NOT EXISTS (SELECT 1 FROM entrega e
                                     WHERE e.id_pedido = p.id AND e.estado IN ('asignada', 'en_ruta', 'entregada'))"
            ),
            'fallidas_hoy'      => $this->contar("SELECT COUNT(*) FROM entrega WHERE estado = 'fallida' AND DATE(fecha_entrega) = ?", [$hoy]),
            'sugerencias_7d'    => $this->contar('SELECT COUNT(*) FROM sugerencia WHERE fecha_creacion >= ?', [date('Y-m-d H:i:s', strtotime('-7 days'))]),
        ];
    }

    private function contar(string $sql, array $params = []): ?int
    {
        $v = $this->intentar(fn () => $this->valor($sql, $params));

        return $v === null ? null : (int) $v;
    }

    /** Ejecuta la consulta; si falla (tabla aún no creada), deja constancia en el log y devuelve null. */
    private function intentar(callable $consulta): mixed
    {
        try {
            return $consulta();
        } catch (Throwable $e) {
            log_message('info', 'Inicio: dato omitido: ' . $e->getMessage());

            return null;
        }
    }
}
