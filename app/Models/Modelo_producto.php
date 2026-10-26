<?php

namespace App\Models;

use App\Libraries\CodigoProducto;
use PDOException;
use RuntimeException;

class Modelo_producto extends PdoModelo
{
    protected string $tabla         = 'producto';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = ['nombre', 'descripcion', 'categoria', 'img', 'codigo'];

    /** Categorías válidas (coinciden con el ENUM de la columna producto.categoria). */
    public const CATEGORIAS = ['frutas', 'hierbas', 'verduras'];

    /**
     * Lista paginada de productos con búsqueda opcional (admin).
     *
     * @return list<array<string,mixed>>
     */
    public function listar(?string $buscar = '', int $porPagina = 20): array
    {
        $buscar = trim((string) $buscar);
        $where  = '';
        $params = [];

        if ($buscar !== '') {
            $like  = $this->patronLike($buscar);
            $parte = [];

            if (ctype_digit($buscar)) {
                $parte[]  = 'id = ?';
                $params[] = (int) $buscar;
            }

            $parte[] = 'codigo LIKE ?';
            $parte[] = 'nombre LIKE ?';
            $parte[] = 'descripcion LIKE ?';
            $parte[] = 'categoria LIKE ?';
            array_push($params, $like, $like, $like, $like);

            $where = ' WHERE ' . implode(' OR ', $parte);
        }

        return $this->paginarSql(
            'SELECT * FROM producto' . $where . ' ORDER BY id DESC',
            $params,
            $porPagina
        );
    }

    /**
     * Productos con menos stock (suma de entradas).
     *
     * @return list<array<string,mixed>>
     */
    public function productosLowStock(int $limite = 5): array
    {
        return $this->filas(
            'SELECT p.nombre, SUM(e.cantidad) AS total
               FROM producto p
          LEFT JOIN entrada e ON e.id_producto = p.id
           GROUP BY p.id, p.nombre
           ORDER BY total ASC
              LIMIT ' . max(1, $limite)
        );
    }

    /**
     * Los 5 productos más vendidos.
     *
     * @return list<array<string,mixed>>
     */
    public function topProductos(): array
    {
        return $this->filas(
            'SELECT p.nombre, SUM(pp.total) AS total_vendido
               FROM producto_pedido pp
               JOIN producto p ON p.id = pp.id_producto
           GROUP BY pp.id_producto, p.nombre
           ORDER BY total_vendido DESC
              LIMIT 5'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // CÓDIGO ÚNICO DE PRODUCTO
    // ─────────────────────────────────────────────────────────────

    /**
     * Inserta un producto y le asigna su código único (ver App\Libraries\CodigoProducto).
     *
     * El código se calcula con categoría + nombre. Si ya existe, se le agrega un consecutivo
     * (-02, -03...). Si dos personas guardan al mismo tiempo y chocan en el índice UNIQUE,
     * se vuelve a calcular y se reintenta. Los demás errores (por ejemplo el trigger
     * no_duplica_producto, que rechaza un nombre repetido) se propagan tal cual para que
     * el controlador los muestre con PdoModelo::mensajeSeguro().
     *
     * @param  array<string,mixed> $datos nombre, descripcion, categoria, img
     * @return string El código asignado (el id nuevo queda en insertID()).
     */
    public function insertarConCodigo(array $datos): string
    {
        $base = CodigoProducto::base((string) ($datos['categoria'] ?? ''), (string) ($datos['nombre'] ?? ''));

        for ($intento = 1; $intento <= 5; $intento++) {
            $codigo = CodigoProducto::siguienteLibre($base, $this->codigosConBase($base));

            try {
                $this->insert([...$datos, 'codigo' => $codigo]);

                return $codigo;
            } catch (PDOException $e) {
                // 1062 = entrada duplicada en el índice UNIQUE de `codigo`: alguien tomó ese
                // código justo antes. Se recalcula y se reintenta.
                $choqueDeCodigo = (int) ($e->errorInfo[1] ?? 0) === 1062
                    && str_contains((string) ($e->errorInfo[2] ?? ''), 'uq_producto_codigo');

                if (! $choqueDeCodigo || $intento === 5) {
                    throw $e;
                }
            }
        }

        // No se llega aquí: el bucle siempre devuelve o lanza.
        throw new \RuntimeException('No se pudo generar un código único de producto.');
    }

    /**
     * Códigos ya usados que empiezan con $base (el propio base y sus consecutivos -NN).
     *
     * @return list<string>
     */
    private function codigosConBase(string $base): array
    {
        $filas = $this->filas(
            'SELECT codigo FROM producto WHERE codigo = ? OR codigo LIKE ?',
            [$base, addcslashes($base, '%_\\') . '-%']
        );

        return array_column($filas, 'codigo');
    }

    // ─────────────────────────────────────────────────────────────
    // CATÁLOGO PÚBLICO (landing page main_page_4)
    // ─────────────────────────────────────────────────────────────

    /**
     * SQL base del catálogo: producto + su entrada más reciente (precio y unidad de venta)
     * + el stock disponible (e_venta de su fila de existencia más reciente, igual que
     * Modelo_existencia::stockDisponiblePorProducto).
     *
     * Solo aparecen productos con precio de venta > 0 (los que aún no tienen precio
     * no se pueden vender).
     */
    private function sqlCatalogo(): string
    {
        return 'SELECT p.id, p.nombre, p.descripcion, p.img, p.categoria,
                       e.precio_venta_u AS precio,
                       e.u_venta        AS unidad,
                       GREATEST(COALESCE(x.e_venta, 0), 0) AS stock
                  FROM producto p
                  JOIN (SELECT id_producto, MAX(id) AS max_id
                          FROM entrada
                      GROUP BY id_producto) ult ON ult.id_producto = p.id
                  JOIN entrada e ON e.id = ult.max_id
             LEFT JOIN (SELECT ex.id_producto, ex.e_venta
                          FROM existencia ex
                          JOIN (SELECT id_producto, MAX(id) AS max_id
                                  FROM existencia
                              GROUP BY id_producto) m ON m.max_id = ex.id) x ON x.id_producto = p.id
                 WHERE e.precio_venta_u > 0';
    }

    /**
     * Catálogo de la landing en UNA sola consulta, repartido en una fila por categoría.
     *
     * Devuelve siempre las tres llaves (frutas, hierbas, verduras), aunque alguna venga
     * vacía. Dentro de cada fila van primero los productos con stock y luego por nombre.
     * $texto (opcional) filtra por nombre o descripción; $porCategoria limita cada fila.
     *
     * @return array<string, list<array<string,mixed>>>
     */
    public function catalogoAgrupado(?string $texto = null, int $porCategoria = 24): array
    {
        $categorias = self::CATEGORIAS;
        $marcas     = implode(',', array_fill(0, count($categorias), '?'));

        $sql    = $this->sqlCatalogo() . " AND p.categoria IN ({$marcas})";
        $params = $categorias;

        $texto = trim((string) $texto);
        if ($texto !== '') {
            $like     = $this->patronLike($texto);
            $sql     .= ' AND (p.nombre LIKE ? OR p.descripcion LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        // Tope de seguridad por si algún día el catálogo crece mucho.
        $sql .= ' ORDER BY (GREATEST(COALESCE(x.e_venta, 0), 0) > 0) DESC, p.nombre ASC LIMIT 600';

        $filas = array_fill_keys($categorias, []);

        foreach ($this->filas($sql, $params) as $fila) {
            if (count($filas[$fila['categoria']]) < $porCategoria) {
                $filas[$fila['categoria']][] = $fila;
            }
        }

        return $filas;
    }

    /**
     * Datos vigentes (precio, unidad, stock) de varios productos a la vez, indexados por id.
     * Se usa para volver a calcular el carrito en el servidor: el precio NUNCA se toma
     * de lo que mande el navegador.
     *
     * @param  list<int> $ids
     * @return array<int, array<string,mixed>>
     */
    public function catalogoPorIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($i) => $i > 0)));

        if ($ids === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $filas  = $this->filas($this->sqlCatalogo() . " AND p.id IN ({$marcas})", $ids);

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int) $fila['id']] = $fila;
        }

        return $mapa;
    }
}
