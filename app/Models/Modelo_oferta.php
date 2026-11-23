<?php

namespace App\Models;

/**
 * Ofertas (promociones) sobre el precio de la tienda.
 *
 * Una oferta baja el precio de ciertos productos o de una categoría completa. El cálculo del
 * precio con descuento NO vive aquí: lo hace Modelo_producto::sqlCatalogo para que se aplique
 * igual en la tienda y en el recálculo del carrito en el servidor. Este modelo solo administra
 * las ofertas (altas, cambios, vigencia) y a qué productos abarcan.
 */
class Modelo_oferta extends PdoModelo
{
    protected string $tabla         = 'oferta';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = ['nombre', 'tipo', 'valor', 'alcance', 'categoria', 'fecha_inicio', 'fecha_fin', 'activa'];

    public const TIPOS    = ['porcentaje', 'precio'];
    public const ALCANCES = ['productos', 'categoria'];

    /**
     * Ofertas para el panel, con cuántos productos abarca cada una. El estado de vigencia
     * (vigente, programada, vencida o inactiva) se decide en la vista a partir de activa y fechas.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(?string $buscar = null): array
    {
        $sql = 'SELECT o.*,
                       (SELECT COUNT(*) FROM oferta_producto op WHERE op.id_oferta = o.id) AS productos_n
                  FROM oferta o';
        $params = [];

        $buscar = trim((string) $buscar);
        if ($buscar !== '') {
            $sql     .= ' WHERE o.nombre LIKE ? OR o.categoria LIKE ?';
            $like     = $this->patronLike($buscar);
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY o.activa DESC, o.creada DESC';

        return $this->filas($sql, $params);
    }

    /**
     * Productos de TODAS las ofertas en una sola consulta, para llenar el formulario de edición
     * sin una consulta por fila.
     *
     * @return array<int, list<int>> id_oferta => [id_producto, ...]
     */
    public function mapaProductos(): array
    {
        $mapa = [];
        foreach ($this->filas('SELECT id_oferta, id_producto FROM oferta_producto ORDER BY id_oferta, id_producto') as $f) {
            $mapa[(int) $f['id_oferta']][] = (int) $f['id_producto'];
        }

        return $mapa;
    }

    /** Ids de los productos que abarca una oferta de alcance «productos». @return list<int> */
    public function idsProducto(int $id): array
    {
        $filas = $this->filas('SELECT id_producto FROM oferta_producto WHERE id_oferta = ? ORDER BY id_producto', [$id]);

        return array_map(static fn ($f) => (int) $f['id_producto'], $filas);
    }

    /**
     * Crea o actualiza una oferta y sincroniza sus productos en una sola transacción.
     * Si el alcance es «categoria» se ignora la lista de productos.
     *
     * @param  array<string,mixed> $datos      ya validados por el controlador
     * @param  list<int>           $idsProducto
     * @return int id de la oferta
     */
    public function guardar(array $datos, array $idsProducto, ?int $id = null): int
    {
        $datos = array_intersect_key($datos, array_flip($this->camposPermitidos));

        if (($datos['alcance'] ?? 'productos') === 'categoria') {
            $idsProducto = [];
        } else {
            $datos['categoria'] = null;
        }

        $limpios = array_values(array_unique(array_filter(array_map('intval', $idsProducto), static fn ($i) => $i > 0)));

        return (int) $this->transaccion(function () use ($datos, $limpios, $id) {
            if ($id === null) {
                $id = $this->insert($datos);
            } else {
                $this->update($id, $datos);
                $this->afectar('DELETE FROM oferta_producto WHERE id_oferta = ?', [$id]);
            }

            if ($limpios !== []) {
                $marcas = implode(',', array_fill(0, count($limpios), '(?, ?)'));
                $vals   = [];
                foreach ($limpios as $idProd) {
                    $vals[] = $id;
                    $vals[] = $idProd;
                }
                $this->afectar("INSERT INTO oferta_producto (id_oferta, id_producto) VALUES {$marcas}", $vals);
            }

            return $id;
        });
    }

    /** Prende o apaga el interruptor manual. */
    public function alternarActiva(int $id): bool
    {
        return $this->afectar('UPDATE oferta SET activa = 1 - activa WHERE id = ?', [$id]) > 0;
    }

    /** Borra la oferta; sus filas en oferta_producto se van en cascada. */
    public function eliminar(int $id): bool
    {
        return $this->delete($id);
    }
}
