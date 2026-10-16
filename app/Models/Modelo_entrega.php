<?php

namespace App\Models;

use DomainException;

/**
 * Entregas de pedidos. Una fila por intento (ver migración CreateEntregaTable).
 *
 * Estados: asignada → en_ruta → entregada | fallida   (o cancelada)
 *
 * SEGURIDAD: los métodos que usa el repartidor (…DeRepartidor, iniciarRuta,
 * marcarEntregada, marcarFallida) reciben el id de la CUENTA (users.id de la sesión
 * de Shield) y lo cruzan con repartidor.id_user dentro del propio SQL. Así un
 * repartidor nunca puede ver ni modificar entregas de otro, aunque manipule el id
 * que viaja en la petición.
 */
class Modelo_entrega extends PdoModelo
{
    protected string $tabla         = 'entrega';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = [
        'id_pedido', 'id_repartidor', 'intento', 'estado',
        'fecha_salida', 'fecha_entrega',
        'monto_cobrado', 'metodo_cobro', 'evidencia', 'incidencia', 'notas',
    ];

    private const METODOS_COBRO = ['efectivo', 'transferencia', 'tarjeta'];

    // ─────────────────────────────────────────────────────────────
    // ADMINISTRADOR
    // ─────────────────────────────────────────────────────────────

    /**
     * Asigna un repartidor a un pedido creando el siguiente intento.
     * No permite asignar si el pedido ya fue entregado o si tiene un intento abierto
     * (asignada / en_ruta): primero hay que cerrarlo (fallida o cancelada).
     *
     * @throws DomainException si el pedido no admite una nueva asignación.
     */
    public function asignar(int $idPedido, int $idRepartidor): int
    {
        return (int) $this->transaccion(function () use ($idPedido, $idRepartidor): int {
            $estadoPedido = (new Modelo_estatus($this->pdo))->ultimoPorId($idPedido)['estado'] ?? 'pedido_pendiente';

            if (! in_array($estadoPedido, ['pedido_confirmado', 'pedido_en_transito'], true)) {
                throw new DomainException('Error: solo se puede asignar repartidor a un pedido confirmado.');
            }

            // Bloquea las filas del pedido para que dos asignaciones simultáneas
            // no calculen el mismo número de intento.
            $previas = $this->filas(
                'SELECT intento, estado FROM entrega WHERE id_pedido = ? ORDER BY intento DESC FOR UPDATE',
                [$idPedido]
            );

            foreach ($previas as $previa) {
                if ($previa['estado'] === 'entregada') {
                    throw new DomainException('Error: el pedido ya fue entregado.');
                }
                if (in_array($previa['estado'], ['asignada', 'en_ruta'], true)) {
                    throw new DomainException('Error: el pedido ya tiene una entrega en curso.');
                }
            }

            $siguiente = $previas === [] ? 1 : ((int) $previas[0]['intento'] + 1);

            return $this->insert([
                'id_pedido'     => $idPedido,
                'id_repartidor' => $idRepartidor,
                'intento'       => $siguiente,
            ]);
        });
    }

    /** Cancela un intento que aún no se cerró (asignada / en_ruta). */
    public function cancelar(int $idEntrega, ?string $motivo = null): bool
    {
        return $this->afectar(
            "UPDATE entrega SET estado = 'cancelada', incidencia = ?
             WHERE id = ? AND estado IN ('asignada', 'en_ruta')",
            [$this->limpiar($motivo, 500), $idEntrega]
        ) > 0;
    }

    /**
     * Todas las entregas (vista del administrador) con búsqueda opcional.
     *
     * @return list<array<string,mixed>>
     */
    public function listarTodas(string $buscar = '', int $porPagina = 20): array
    {
        $where  = '';
        $params = [];
        $buscar = trim($buscar);

        if ($buscar !== '') {
            $like   = $this->patronLike($buscar);
            $where  = ' WHERE (e.id_pedido LIKE ? OR e.estado LIKE ? OR r.nombre LIKE ? OR r.ape_pat LIKE ?
                          OR c.nombre LIKE ? OR c.ape_pat LIKE ?)';
            $params = array_fill(0, 6, $like);
        }

        return $this->paginarSql(
            "SELECT e.*,
                    CONCAT_WS(' ', r.nombre, r.ape_pat) AS repartidor,
                    CONCAT_WS(' ', c.nombre, c.ape_pat) AS cliente
               FROM entrega e
               JOIN repartidor r ON r.id = e.id_repartidor
               JOIN pedido p     ON p.id = e.id_pedido
               JOIN cliente c    ON c.id = p.id_cliente
               {$where}
              ORDER BY e.id DESC",
            $params,
            $porPagina
        );
    }

    /**
     * Pedidos a los que se les puede asignar repartidor: confirmados o en tránsito
     * (reintento) y sin una entrega abierta ni ya entregada.
     *
     * @return list<array<string,mixed>>
     */
    public function pedidosAsignables(): array
    {
        return $this->filas(
            "SELECT p.id, p.fecha, CONCAT_WS(' ', c.nombre, c.ape_pat) AS cliente
               FROM pedido p
               JOIN cliente c ON c.id = p.id_cliente
              WHERE (SELECT s.estado FROM estatus s WHERE s.id_pedido = p.id ORDER BY s.id DESC LIMIT 1)
                    IN ('pedido_confirmado', 'pedido_en_transito')
                AND NOT EXISTS (SELECT 1 FROM entrega e
                                 WHERE e.id_pedido = p.id AND e.estado IN ('asignada', 'en_ruta', 'entregada'))
              ORDER BY p.id DESC"
        );
    }

    /**
     * Historial de intentos de un pedido, del más reciente al más antiguo.
     *
     * @return list<array<string,mixed>>
     */
    public function porPedido(int $idPedido): array
    {
        return $this->filas(
            "SELECT e.*, CONCAT_WS(' ', r.nombre, r.ape_pat, r.ape_mat) AS repartidor
               FROM entrega e
               JOIN repartidor r ON r.id = e.id_repartidor
              WHERE e.id_pedido = ?
              ORDER BY e.intento DESC",
            [$idPedido]
        );
    }

    /** Último intento de un pedido (o null si nunca se asignó). */
    public function ultimaDePedido(int $idPedido): ?array
    {
        return $this->fila(
            'SELECT * FROM entrega WHERE id_pedido = ? ORDER BY intento DESC LIMIT 1',
            [$idPedido]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // REPARTIDOR (siempre acotado a la cuenta de la sesión)
    // ─────────────────────────────────────────────────────────────

    /**
     * Entregas del repartidor logueado. $estado opcional: uno de los estados válidos;
     * null = todas. Las pendientes (asignada / en_ruta) aparecen primero.
     *
     * @return list<array<string,mixed>>
     */
    public function listarDeRepartidor(int $idUser, ?string $estado = null, int $porPagina = 20): array
    {
        $where  = 'WHERE r.id_user = ?';
        $params = [$idUser];

        if ($estado !== null && $estado !== '') {
            if (! in_array($estado, ['asignada', 'en_ruta', 'entregada', 'fallida', 'cancelada'], true)) {
                return [];
            }
            $where   .= ' AND e.estado = ?';
            $params[] = $estado;
        }

        return $this->paginarSql(
            "SELECT e.*, p.fecha AS fecha_pedido,
                    CONCAT_WS(' ', c.nombre, c.ape_pat, c.ape_mat) AS cliente,
                    c.telefono AS telefono_cliente
               FROM entrega e
               JOIN repartidor r ON r.id = e.id_repartidor
               JOIN pedido p     ON p.id = e.id_pedido
               JOIN cliente c    ON c.id = p.id_cliente
               {$where}
              ORDER BY (e.estado IN ('asignada','en_ruta')) DESC, e.fecha_asignacion DESC, e.id DESC",
            $params,
            $porPagina
        );
    }

    /** Una entrega, solo si pertenece al repartidor de esa cuenta. */
    public function obtenerDeRepartidor(int $idEntrega, int $idUser): ?array
    {
        return $this->fila(
            "SELECT e.*, p.fecha AS fecha_pedido, p.id_cliente,
                    CONCAT_WS(' ', c.nombre, c.ape_pat, c.ape_mat) AS cliente,
                    c.telefono AS telefono_cliente
               FROM entrega e
               JOIN repartidor r ON r.id = e.id_repartidor
               JOIN pedido p     ON p.id = e.id_pedido
               JOIN cliente c    ON c.id = p.id_cliente
              WHERE e.id = ? AND r.id_user = ?
              LIMIT 1",
            [$idEntrega, $idUser]
        );
    }

    /**
     * asignada → en_ruta. Si el pedido estaba "confirmado" lo pasa a "en tránsito"
     * (el trigger de `estatus` descuenta el inventario). En un reintento el pedido ya
     * está en tránsito y no se vuelve a registrar el estatus.
     *
     * @return bool false si no existe, no es suya o no está asignada.
     * @throws DomainException si el pedido no está confirmado ni en tránsito.
     */
    public function iniciarRuta(int $idEntrega, int $idUser): bool
    {
        return (bool) $this->transaccion(function () use ($idEntrega, $idUser): bool {
            $entrega = $this->fila(
                "SELECT e.id, e.id_pedido
                   FROM entrega e
                   JOIN repartidor r ON r.id = e.id_repartidor
                  WHERE e.id = ? AND r.id_user = ? AND e.estado = 'asignada'
                  FOR UPDATE",
                [$idEntrega, $idUser]
            );

            if ($entrega === null) {
                return false;
            }

            $m_estatus = new Modelo_estatus($this->pdo);
            $estado    = $m_estatus->ultimoPorId((int) $entrega['id_pedido'])['estado'] ?? 'pedido_pendiente';

            if ($estado === 'pedido_confirmado') {
                $m_estatus->insert(['estado' => 'pedido_en_transito', 'id_pedido' => (int) $entrega['id_pedido']]);
            } elseif ($estado !== 'pedido_en_transito') {
                throw new DomainException('Error: el pedido ya no está disponible para salir a entrega.');
            }

            $this->afectar(
                "UPDATE entrega SET estado = 'en_ruta', fecha_salida = NOW() WHERE id = ?",
                [$idEntrega]
            );

            return true;
        });
    }

    /**
     * en_ruta → entregada, con cobro contra entrega y evidencia opcionales, y registra
     * el estatus "pedido_entregado" del pedido.
     * $metodo debe ser efectivo|transferencia|tarjeta si se manda $monto.
     *
     * @return bool false si no existe, no es suya, no está en ruta o el cobro no es válido.
     * @throws DomainException si el pedido no está en tránsito.
     */
    public function marcarEntregada(
        int $idEntrega,
        int $idUser,
        ?float $monto = null,
        ?string $metodo = null,
        ?string $evidencia = null,
        ?string $notas = null
    ): bool {
        if ($monto !== null && ($monto < 0 || ! in_array($metodo, self::METODOS_COBRO, true))) {
            return false;
        }

        return (bool) $this->transaccion(function () use ($idEntrega, $idUser, $monto, $metodo, $evidencia, $notas): bool {
            $entrega = $this->fila(
                "SELECT e.id, e.id_pedido
                   FROM entrega e
                   JOIN repartidor r ON r.id = e.id_repartidor
                  WHERE e.id = ? AND r.id_user = ? AND e.estado = 'en_ruta'
                  FOR UPDATE",
                [$idEntrega, $idUser]
            );

            if ($entrega === null) {
                return false;
            }

            $m_estatus = new Modelo_estatus($this->pdo);
            $estado    = $m_estatus->ultimoPorId((int) $entrega['id_pedido'])['estado'] ?? 'pedido_pendiente';

            if ($estado !== 'pedido_en_transito') {
                throw new DomainException('Error: el pedido no está en tránsito.');
            }

            $m_estatus->insert(['estado' => 'pedido_entregado', 'id_pedido' => (int) $entrega['id_pedido']]);

            $this->afectar(
                "UPDATE entrega
                    SET estado = 'entregada', fecha_entrega = NOW(),
                        monto_cobrado = ?, metodo_cobro = ?, evidencia = ?, notas = ?
                  WHERE id = ?",
                [
                    $monto,
                    $monto === null ? null : $metodo,
                    $this->limpiar($evidencia, 255),
                    $this->limpiar($notas, 500),
                    $idEntrega,
                ]
            );

            return true;
        });
    }

    /** en_ruta → fallida, con la incidencia obligatoria (el admin podrá reasignar). */
    public function marcarFallida(int $idEntrega, int $idUser, string $incidencia): bool
    {
        $incidencia = $this->limpiar($incidencia, 500);

        if ($incidencia === null) {
            return false;
        }

        return $this->afectar(
            "UPDATE entrega e
               JOIN repartidor r ON r.id = e.id_repartidor
                SET e.estado = 'fallida', e.fecha_entrega = NOW(), e.incidencia = ?
              WHERE e.id = ? AND r.id_user = ? AND e.estado = 'en_ruta'",
            [$incidencia, $idEntrega, $idUser]
        ) > 0;
    }

    // ─────────────────────────────────────────────────────────────

    /** Recorta espacios y largo; devuelve null si queda vacío. */
    private function limpiar(?string $texto, int $max): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : mb_substr($texto, 0, $max);
    }
}
