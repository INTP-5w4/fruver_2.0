<?php

namespace App\Models;

class Modelo_merma extends PdoModelo
{
    protected string $tabla         = 'merma';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = ['cantidad', 'fecha', 'notas', 'id_entrada', 'u_venta'];

    /** @return list<array<string,mixed>> */
    public function perdidasPorSemana(): array
    {
        return $this->filas(
            "SELECT DATE_FORMAT(m.fecha, '%x-W%v') AS semana,
                    SUM(m.cantidad * e.precio_compra_u) AS perdida
               FROM merma m
               JOIN entrada e ON e.id = m.id_entrada
              WHERE m.fecha >= ?
           GROUP BY semana
           ORDER BY semana ASC",
            [date('Y-m-d', strtotime('-8 weeks'))]
        );
    }

    /** @return list<array<string,mixed>> */
    public function perdidasPorMes(): array
    {
        return $this->filas(
            "SELECT DATE_FORMAT(m.fecha, '%Y-%m') AS mes,
                    SUM(m.cantidad * e.precio_compra_u) AS perdida
               FROM merma m
               JOIN entrada e ON e.id = m.id_entrada
              WHERE m.fecha >= ?
           GROUP BY mes
           ORDER BY mes ASC",
            [date('Y-m-d', strtotime('-6 months'))]
        );
    }

    /**
     * Lista paginada de mermas con búsqueda opcional.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(?string $buscar = '', int $porPagina = 20): array
    {
        $buscar = trim((string) $buscar);
        $where  = '';
        $params = [];

        if ($buscar !== '') {
            // Por producto (nombre), notas y fecha; si es un número, también por id de merma o de entrada.
            $like  = $this->patronLike($buscar);
            $parte = ['p.nombre LIKE ?', 'merma.notas LIKE ?', 'merma.fecha LIKE ?'];
            $params = [$like, $like, $like];

            if (ctype_digit($buscar)) {
                $parte[] = 'merma.id = ?';
                $parte[] = 'merma.id_entrada = ?';
                array_push($params, (int) $buscar, (int) $buscar);
            }

            $where = ' WHERE (' . implode(' OR ', $parte) . ')';
        }

        return $this->paginarSql(
            'SELECT merma.*
               FROM merma
          LEFT JOIN entrada e ON e.id = merma.id_entrada
          LEFT JOIN producto p ON p.id = e.id_producto' . $where . ' ORDER BY merma.id DESC',
            $params,
            $porPagina
        );
    }
}
