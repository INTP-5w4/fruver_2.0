<?php

namespace App\Models;

use DomainException;

/**
 * Rutas de reparto: agrupan las entregas de un repartidor en un día y guardan el
 * orden óptimo. Cada parada ES una fila de `entrega` (columnas id_ruta, orden_ruta,
 * eta, tramo, GPS), así se reutiliza todo el flujo de entregas que ya existe:
 * asignar → en_ruta → entregada | fallida.
 *
 * SEGURIDAD: los métodos del repartidor reciben users.id (sesión de Shield) y lo
 * cruzan con repartidor.id_user dentro del SQL, igual que Modelo_entrega.
 */
class Modelo_ruta extends PdoModelo
{
    protected string $tabla         = 'ruta';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = [
        'fecha', 'id_repartidor', 'id_almacen', 'estado', 'estrategia', 'regresa_almacen',
        'distancia_m', 'duracion_s', 'polyline', 'proveedor', 'creada_por',
        'iniciada_en', 'finalizada_en', 'updated_at',
    ];

    public const ESTADOS = ['asignada', 'en_curso', 'finalizada', 'cancelada'];

    private static ?bool $lista = null;

    /** ¿Ya se corrió la migración de rutas? */
    public function esquemaListo(): bool
    {
        if (self::$lista === null) {
            try {
                $this->filas('SELECT r.id, e.id_ruta, d.lat, p.id_direccion, a.id FROM ruta r, entrega e, direccion d, pedido p, almacen a LIMIT 0');
                self::$lista = true;
            } catch (\Throwable) {
                self::$lista = false;
            }
        }

        return self::$lista;
    }

    /**
     * SQL que resuelve la dirección de un pedido `p`: la elegida o, si no tiene, la
     * principal del cliente (o la más reciente). Úsalo como: LEFT JOIN direccion d ON d.id = (...)
     */
    public const SQL_DIRECCION_PEDIDO = 'COALESCE(p.id_direccion, (SELECT d2.id FROM direccion d2 WHERE d2.id_cliente = p.id_cliente ORDER BY d2.es_principal DESC, d2.id DESC LIMIT 1))';

    private const CAMPOS_PARADA = "
        p.id AS id_pedido, p.fecha AS fecha_pedido, p.id_cliente, p.ventana_fin,
        CONCAT_WS(' ', c.nombre, c.ape_pat, c.ape_mat) AS cliente, c.telefono AS telefono_cliente,
        d.id AS id_direccion, d.calle, d.numero, d.colonia, d.municipio, d.estado AS estado_dir,
        d.codigo_postal, d.referencias, d.place_id, d.lat, d.lng, d.precision_geo, d.origen_coord,
        (SELECT COALESCE(SUM(pp.total), 0) FROM producto_pedido pp WHERE pp.id_pedido = p.id) AS total_pedido";

    // ─────────────────────────────────────────────────────────────
    // PLANEACIÓN (administrador)
    // ─────────────────────────────────────────────────────────────

    /**
     * Pedidos que se pueden poner en una ruta:
     *  - confirmados (o en tránsito por un reintento) sin una entrega abierta, y
     *  - entregas ya asignadas a un repartidor que todavía no están en ninguna ruta.
     *
     * @return list<array<string,mixed>>
     */
    public function candidatos(): array
    {
        $dir = self::SQL_DIRECCION_PEDIDO;
        $ult = '(SELECT s.estado FROM estatus s WHERE s.id_pedido = p.id ORDER BY s.id DESC LIMIT 1)';

        return $this->filas(
            "SELECT * FROM (
                SELECT 'pedido' AS tipo, NULL AS id_entrega, NULL AS id_repartidor_asignado, " . self::CAMPOS_PARADA . "
                  FROM pedido p
                  JOIN cliente c        ON c.id = p.id_cliente
             LEFT JOIN direccion d      ON d.id = {$dir}
                 WHERE {$ult} IN ('pedido_confirmado', 'pedido_en_transito')
                   AND NOT EXISTS (SELECT 1 FROM entrega e
                                    WHERE e.id_pedido = p.id AND e.estado IN ('asignada', 'en_ruta', 'entregada'))
              UNION ALL
                SELECT 'entrega' AS tipo, e.id AS id_entrega, e.id_repartidor AS id_repartidor_asignado, " . self::CAMPOS_PARADA . "
                  FROM entrega e
                  JOIN pedido p         ON p.id = e.id_pedido
                  JOIN cliente c        ON c.id = p.id_cliente
             LEFT JOIN direccion d      ON d.id = {$dir}
                 WHERE e.estado = 'asignada' AND e.id_ruta IS NULL
            ) AS x
            ORDER BY (x.ventana_fin IS NULL), x.ventana_fin, x.fecha_pedido, x.id_pedido"
        );
    }

    /**
     * Datos de parada de varios pedidos (para calcular la ruta en el servidor; nunca
     * se confía en coordenadas que mande el navegador).
     *
     * @param list<int> $idsPedido
     * @return array<int,array<string,mixed>> id_pedido => fila
     */
    public function paradasDePedidos(array $idsPedido): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsPedido), static fn ($i) => $i > 0)));
        if ($ids === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $filas  = $this->filas(
            'SELECT ' . self::CAMPOS_PARADA . '
               FROM pedido p
               JOIN cliente c   ON c.id = p.id_cliente
          LEFT JOIN direccion d ON d.id = ' . self::SQL_DIRECCION_PEDIDO . "
              WHERE p.id IN ({$marcas})",
            $ids
        );

        return array_column($filas, null, 'id_pedido');
    }

    /**
     * Crea una ruta con sus paradas en orden. Para cada pedido reutiliza su entrega
     * "asignada" (sin ruta) o crea una nueva con Modelo_entrega::asignar().
     *
     * @param array<string,mixed>                 $ruta
     * @param list<array{id_pedido:int,eta:?string,distancia_tramo_m:?int,duracion_tramo_s:?int}> $paradas en orden de visita
     *
     * @throws DomainException si algún pedido ya no se puede asignar
     */
    public function crear(array $ruta, array $paradas): int
    {
        if ($paradas === []) {
            throw new DomainException('Error: la ruta no tiene paradas.');
        }

        return (int) $this->transaccion(function () use ($ruta, $paradas): int {
            $idRepartidor = (int) $ruta['id_repartidor'];
            $idRuta       = $this->insert($ruta + ['estado' => 'asignada']);
            $m_entrega    = new Modelo_entrega($this->pdo);

            foreach (array_values($paradas) as $i => $parada) {
                $idPedido = (int) $parada['id_pedido'];

                $abierta = $this->fila(
                    "SELECT id, estado, id_ruta, id_repartidor FROM entrega
                      WHERE id_pedido = ? AND estado IN ('asignada', 'en_ruta')
                      ORDER BY intento DESC LIMIT 1 FOR UPDATE",
                    [$idPedido]
                );

                if ($abierta !== null) {
                    if ($abierta['estado'] !== 'asignada' || $abierta['id_ruta'] !== null) {
                        throw new DomainException("Error: el pedido #{$idPedido} ya va en camino o está en otra ruta.");
                    }
                    $idEntrega = (int) $abierta['id'];
                    if ((int) $abierta['id_repartidor'] !== $idRepartidor) {
                        // Aún no sale: se puede pasar a otro repartidor.
                        $this->afectar('UPDATE entrega SET id_repartidor = ? WHERE id = ?', [$idRepartidor, $idEntrega]);
                    }
                } else {
                    $idEntrega = $m_entrega->asignar($idPedido, $idRepartidor);
                }

                $this->afectar(
                    'UPDATE entrega SET id_ruta = ?, orden_ruta = ?, eta = ?, distancia_tramo_m = ?, duracion_tramo_s = ? WHERE id = ?',
                    [$idRuta, $i + 1, $parada['eta'] ?? null, $parada['distancia_tramo_m'] ?? null, $parada['duracion_tramo_s'] ?? null, $idEntrega]
                );
            }

            return $idRuta;
        });
    }

    /**
     * Cancela una ruta que no ha salido. Las entregas quedan asignadas al repartidor,
     * sin ruta, para poder planearlas de nuevo.
     */
    public function cancelar(int $idRuta): bool
    {
        return (bool) $this->transaccion(function () use ($idRuta): bool {
            $ruta = $this->fila('SELECT estado FROM ruta WHERE id = ? FOR UPDATE', [$idRuta]);
            if ($ruta === null || $ruta['estado'] !== 'asignada') {
                return false;
            }

            $enCamino = (int) $this->valor(
                "SELECT COUNT(*) FROM entrega WHERE id_ruta = ? AND estado IN ('en_ruta', 'entregada', 'fallida')",
                [$idRuta]
            );
            if ($enCamino > 0) {
                return false;
            }

            $this->afectar(
                "UPDATE entrega SET id_ruta = NULL, orden_ruta = NULL, eta = NULL, distancia_tramo_m = NULL, duracion_tramo_s = NULL
                  WHERE id_ruta = ? AND estado = 'asignada'",
                [$idRuta]
            );
            $this->afectar("UPDATE ruta SET estado = 'cancelada', updated_at = NOW() WHERE id = ?", [$idRuta]);

            return true;
        });
    }

    /** @return list<array<string,mixed>> rutas de una fecha con avance */
    public function deFecha(string $fecha): array
    {
        return $this->filas(
            "SELECT r.*, CONCAT_WS(' ', rp.nombre, rp.ape_pat) AS repartidor, rp.vehiculo,
                    (SELECT COUNT(*) FROM entrega e WHERE e.id_ruta = r.id) AS paradas,
                    (SELECT COUNT(*) FROM entrega e WHERE e.id_ruta = r.id AND e.estado = 'entregada') AS entregadas,
                    (SELECT COUNT(*) FROM entrega e WHERE e.id_ruta = r.id AND e.estado = 'fallida') AS fallidas
               FROM ruta r
               JOIN repartidor rp ON rp.id = r.id_repartidor
              WHERE r.fecha = ?
           ORDER BY r.id DESC",
            [$fecha]
        );
    }

    /** Ruta con datos del repartidor y del almacén. */
    public function detalle(int $idRuta): ?array
    {
        return $this->fila(
            "SELECT r.*, CONCAT_WS(' ', rp.nombre, rp.ape_pat) AS repartidor, rp.vehiculo, rp.telefono AS telefono_repartidor,
                    rp.id_user, a.nombre AS almacen, a.direccion AS direccion_almacen, a.lat AS almacen_lat, a.lng AS almacen_lng
               FROM ruta r
               JOIN repartidor rp ON rp.id = r.id_repartidor
               JOIN almacen a     ON a.id = r.id_almacen
              WHERE r.id = ?",
            [$idRuta]
        );
    }

    /**
     * Paradas de una ruta en orden de visita.
     *
     * @return list<array<string,mixed>>
     */
    public function paradas(int $idRuta): array
    {
        return $this->filas(
            'SELECT e.*, ' . self::CAMPOS_PARADA . '
               FROM entrega e
               JOIN pedido p    ON p.id = e.id_pedido
               JOIN cliente c   ON c.id = p.id_cliente
          LEFT JOIN direccion d ON d.id = ' . self::SQL_DIRECCION_PEDIDO . '
              WHERE e.id_ruta = ?
           ORDER BY e.orden_ruta ASC, e.id ASC',
            [$idRuta]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // REPARTIDOR (siempre acotado a su cuenta)
    // ─────────────────────────────────────────────────────────────

    /** La ruta pendiente más antigua del repartidor (o una en particular si es suya). */
    public function activaDeUsuario(int $idUser, ?int $idRuta = null): ?array
    {
        $params = [$idUser];
        $filtro = "r.estado IN ('asignada', 'en_curso')";

        if ($idRuta !== null) {
            $filtro   = 'r.id = ?';
            $params[] = $idRuta;
        }

        $fila = $this->fila(
            "SELECT r.id FROM ruta r JOIN repartidor rp ON rp.id = r.id_repartidor
              WHERE rp.id_user = ? AND {$filtro}
           ORDER BY r.fecha ASC, r.id ASC LIMIT 1",
            $params
        );

        return $fila !== null ? $this->detalle((int) $fila['id']) : null;
    }

    /** ¿Cuántas rutas pendientes tiene el repartidor? (aviso en "Mis entregas") */
    public function pendientesDeUsuario(int $idUser): int
    {
        return (int) $this->valor(
            "SELECT COUNT(*) FROM ruta r JOIN repartidor rp ON rp.id = r.id_repartidor
              WHERE rp.id_user = ? AND r.estado IN ('asignada', 'en_curso')",
            [$idUser]
        );
    }

    /**
     * Sale a ruta: pasa cada entrega "asignada" a "en_ruta" (y el pedido a en tránsito)
     * con Modelo_entrega::iniciarRuta. Devuelve cuántas salieron y los avisos.
     *
     * @return array{salieron:int,avisos:list<string>}
     */
    public function iniciar(int $idRuta, int $idUser): array
    {
        return $this->transaccion(function () use ($idRuta, $idUser): array {
            $ruta = $this->fila(
                "SELECT r.id FROM ruta r JOIN repartidor rp ON rp.id = r.id_repartidor
                  WHERE r.id = ? AND rp.id_user = ? AND r.estado IN ('asignada', 'en_curso') FOR UPDATE",
                [$idRuta, $idUser]
            );
            if ($ruta === null) {
                throw new DomainException('Error: esta ruta no está disponible.');
            }

            $m_entrega = new Modelo_entrega($this->pdo);
            $salieron  = 0;
            $avisos    = [];

            foreach ($this->filas("SELECT id, id_pedido FROM entrega WHERE id_ruta = ? AND estado = 'asignada' ORDER BY orden_ruta", [$idRuta]) as $e) {
                try {
                    if ($m_entrega->iniciarRuta((int) $e['id'], $idUser)) {
                        $salieron++;
                    }
                } catch (DomainException $ex) {
                    $avisos[] = 'Pedido #' . $e['id_pedido'] . ': ' . $ex->getMessage();
                }
            }

            $this->afectar(
                "UPDATE ruta SET estado = 'en_curso', iniciada_en = COALESCE(iniciada_en, NOW()), updated_at = NOW() WHERE id = ?",
                [$idRuta]
            );

            return ['salieron' => $salieron, 'avisos' => $avisos];
        });
    }

    /**
     * Guarda un nuevo orden para las paradas PENDIENTES (reoptimizar desde la ubicación
     * del repartidor o reordenar a mano). Solo toca entregas de esa ruta.
     *
     * @param list<array{id_entrega:int,eta:?string,distancia_tramo_m:?int,duracion_tramo_s:?int}> $pendientes en orden
     */
    public function reordenarPendientes(int $idRuta, array $pendientes, array $totales): void
    {
        $this->transaccion(function () use ($idRuta, $pendientes, $totales): void {
            // Las paradas ya cerradas quedan primero, numeradas en el orden en que ocurrieron,
            // para que no se repitan números con las pendientes.
            $hechas = $this->filas(
                "SELECT id FROM entrega WHERE id_ruta = ? AND estado NOT IN ('asignada', 'en_ruta')
                  ORDER BY COALESCE(fecha_entrega, fecha_asignacion), orden_ruta, id",
                [$idRuta]
            );
            foreach ($hechas as $i => $h) {
                $this->afectar('UPDATE entrega SET orden_ruta = ? WHERE id = ?', [$i + 1, (int) $h['id']]);
            }
            $cerradas = count($hechas);

            foreach (array_values($pendientes) as $i => $p) {
                $this->afectar(
                    "UPDATE entrega SET orden_ruta = ?, eta = ?, distancia_tramo_m = ?, duracion_tramo_s = ?
                      WHERE id = ? AND id_ruta = ? AND estado IN ('asignada', 'en_ruta')",
                    [$cerradas + $i + 1, $p['eta'] ?? null, $p['distancia_tramo_m'] ?? null, $p['duracion_tramo_s'] ?? null, (int) $p['id_entrega'], $idRuta]
                );
            }

            $this->afectar(
                'UPDATE ruta SET estrategia = ?, proveedor = ?, polyline = ?, distancia_m = ?, duracion_s = ?, updated_at = NOW() WHERE id = ?',
                [$totales['estrategia'], $totales['proveedor'], $totales['polyline'], $totales['distancia_m'], $totales['duracion_s'], $idRuta]
            );
        });
    }

    /** Marca la ruta como finalizada cuando ya no le quedan entregas abiertas. */
    public function cerrarSiTermino(int $idRuta): bool
    {
        return $this->afectar(
            "UPDATE ruta SET estado = 'finalizada', finalizada_en = NOW(), updated_at = NOW()
              WHERE id = ? AND estado IN ('asignada', 'en_curso')
                AND NOT EXISTS (SELECT 1 FROM entrega e WHERE e.id_ruta = ? AND e.estado IN ('asignada', 'en_ruta'))
                AND EXISTS (SELECT 1 FROM entrega e WHERE e.id_ruta = ?)",
            [$idRuta, $idRuta, $idRuta]
        ) > 0;
    }

    /** Ruta a la que pertenece una entrega (o null). */
    public function deEntrega(int $idEntrega): ?int
    {
        $id = $this->valor('SELECT id_ruta FROM entrega WHERE id = ?', [$idEntrega]);

        return $id !== null ? (int) $id : null;
    }

    /** Texto de una dirección para mostrar / navegar sin coordenadas. */
    public static function textoDireccion(array $p): string
    {
        $linea1 = trim(($p['calle'] ?? '') . ' ' . ($p['numero'] ?? ''));
        $partes = array_filter([$linea1, $p['colonia'] ?? '', $p['municipio'] ?? '', $p['estado_dir'] ?? ''], static fn ($x) => trim((string) $x) !== '');

        return implode(', ', $partes);
    }
}
