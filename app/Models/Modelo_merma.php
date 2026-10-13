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
            if (is_numeric($buscar)) {
                $where    = ' WHERE merma.id = ?';
                $params[] = (int) $buscar;
            } else {
                $like   = $this->patronLike($buscar);
                $where  = ' WHERE (merma.cantidad LIKE ? OR merma.fecha LIKE ? OR merma.id_entrada LIKE ?)';
                $params = [$like, $like, $like];
            }
        }

        return $this->paginarSql(
            'SELECT merma.* FROM merma' . $where . ' ORDER BY merma.id DESC',
            $params,
            $porPagina
        );
    }
}
