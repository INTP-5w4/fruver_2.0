<?php

namespace App\Models;

use DomainException;

/**
 * Fichas de repartidor.
 *
 * BAJA LÓGICA (migración AddBajaRepartidor): una ficha con historial nunca se borra. Cuando
 * el repartidor deja de serlo se marca activo = 0: conserva sus entregas, rutas y pedidos,
 * pero ya no aparece al asignar. Si regresa, se reactiva la MISMA ficha.
 *
 * Regla para fichas enlazadas a una cuenta: activa ⇔ la cuenta está en el grupo "repartidor".
 * La baja y la reactivación de esas fichas las hace el superadmin al cambiar el rol
 * (App\Controllers\Roles). Las fichas sin cuenta las da de baja el personal en Repartidores.
 */
class Modelo_repartidor extends PdoModelo
{
    protected string $tabla         = 'repartidor';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = ['nombre', 'ape_pat', 'ape_mat', 'telefono', 'direccion', 'notas'];

    /** Vehículos (definen el modo de viaje en Google Maps). */
    public const VEHICULOS = ['moto' => 'Moto', 'auto' => 'Auto', 'camioneta' => 'Camioneta', 'bicicleta' => 'Bicicleta'];

    private static ?bool $conVehiculo = null;

    private static ?bool $conCombustible = null;

    public function __construct(?\PDO $pdo = null)
    {
        parent::__construct($pdo);

        if ($this->tieneVehiculo()) {
            $this->camposPermitidos[] = 'vehiculo';
        }
        if ($this->tieneCombustible()) {
            $this->camposPermitidos[] = 'rendimiento_km_l';
            $this->camposPermitidos[] = 'combustible';
        }
    }

    /** ¿Ya existen las columnas de combustible (migración AddCombustible)? */
    public function tieneCombustible(): bool
    {
        if (self::$conCombustible === null) {
            try {
                $this->filas('SELECT rendimiento_km_l, combustible FROM repartidor LIMIT 0');
                self::$conCombustible = true;
            } catch (\Throwable) {
                self::$conCombustible = false;
            }
        }

        return self::$conCombustible;
    }

    /** Ficha enlazada a una cuenta de acceso (o null si no tiene). */
    public function deUsuario(int $idUser): ?array
    {
        if (! $this->tieneCuenta()) {
            return null;
        }

        return $this->fila('SELECT * FROM repartidor WHERE id_user = ? LIMIT 1', [$idUser]);
    }

    /** ¿Ya existe la columna `vehiculo` (migración de rutas)? */
    public function tieneVehiculo(): bool
    {
        if (self::$conVehiculo === null) {
            try {
                $this->filas('SELECT vehiculo FROM repartidor LIMIT 0');
                self::$conVehiculo = true;
            } catch (\Throwable) {
                self::$conVehiculo = false;
            }
        }

        return self::$conVehiculo;
    }

    /**
     * Lista paginada de repartidores con búsqueda opcional.
     *
     * @return list<array<string,mixed>>
     */
    public function listar(?string $buscar = '', int $porPagina = 20): array
    {
        $buscar     = trim((string) $buscar);
        $conCuenta  = $this->tieneCuenta();
        $where      = '';
        $params     = [];

        if ($buscar !== '') {
            $like  = $this->patronLike($buscar);
            $parte = [];

            if (ctype_digit($buscar)) {
                $parte[]  = 'r.id = ?';
                $params[] = (int) $buscar;
            }

            foreach (['r.nombre', 'r.ape_pat', 'r.ape_mat', 'r.telefono'] as $columna) {
                $parte[]  = $columna . ' LIKE ?';
                $params[] = $like;
            }
            if ($conCuenta) {
                $parte[]  = 'u.username LIKE ?';
                $params[] = $like;
            }

            $where = ' WHERE ' . implode(' OR ', $parte);
        }

        // Con el nombre de la cuenta de acceso enlazada (y si tiene el rol de repartidor), si la columna ya existe.
        $sql = $conCuenta
            ? "SELECT r.*, u.username AS cuenta,
                      EXISTS (SELECT 1 FROM auth_groups_users g
                               WHERE g.user_id = r.id_user AND g.`group` = 'repartidor') AS cuenta_repartidor
                 FROM repartidor r LEFT JOIN users u ON u.id = r.id_user"
            : 'SELECT r.* FROM repartidor r';

        // Los activos primero; los dados de baja al final (siguen visibles por su historial).
        $orden = $this->tieneBaja() ? ' ORDER BY r.activo DESC, r.id DESC' : ' ORDER BY r.id DESC';

        return $this->paginarSql($sql . $where . $orden, $params, $porPagina);
    }

    // ─────────────────────────────────────────────────────────────
    // BAJA LÓGICA
    // ─────────────────────────────────────────────────────────────

    private static ?bool $conBaja = null;

    /** ¿Ya existe la columna `activo` (migración AddBajaRepartidor)? */
    public function tieneBaja(): bool
    {
        if (self::$conBaja === null) {
            try {
                $this->filas('SELECT activo, fecha_baja, motivo_baja, baja_por FROM repartidor LIMIT 0');
                self::$conBaja = true;
            } catch (\Throwable) {
                self::$conBaja = false;
            }
        }

        return self::$conBaja;
    }

    /** ¿La ficha está dada de baja? (sin la columna, ninguna lo está) */
    public static function deBaja(array $r): bool
    {
        return array_key_exists('activo', $r) && $r['activo'] !== null && (int) $r['activo'] === 0;
    }

    public static function nombreCompleto(array $r): string
    {
        $nombre = trim(implode(' ', array_filter([(string) ($r['nombre'] ?? ''), (string) ($r['ape_pat'] ?? ''), (string) ($r['ape_mat'] ?? '')])));

        return $nombre !== '' ? $nombre : 'Repartidor #' . (int) ($r['id'] ?? 0);
    }

    /**
     * Fichas que pueden recibir entregas, pedidos y rutas (las de los selectores).
     *
     * @return list<array<string,mixed>>
     */
    public function activos(): array
    {
        return $this->tieneBaja()
            ? $this->filas('SELECT * FROM repartidor WHERE activo = 1 ORDER BY id ASC')
            : $this->findAll();
    }

    /**
     * Devuelve la ficha si puede recibir trabajo nuevo. Se llama dentro de las transacciones
     * que asignan (entregas, rutas, pedidos) para que la regla no dependa del formulario.
     *
     * @throws DomainException si no existe o está dada de baja
     */
    public function exigirActivo(int $idRepartidor): array
    {
        $r = $this->find($idRepartidor);

        if ($r === null) {
            throw new DomainException('Error: el repartidor elegido no existe.');
        }
        if (self::deBaja($r)) {
            throw new DomainException('Error: ' . self::nombreCompleto($r) . ' fue dado de baja como repartidor; elige otro.');
        }

        return $r;
    }

    /**
     * Trabajo abierto de un repartidor. Lo "en camino" impide la baja; lo "asignado"
     * (todavía no sale) se puede cancelar para reasignarlo.
     *
     * @return array{entregas_asignadas:int, entregas_en_ruta:int, rutas_asignadas:int, rutas_en_curso:int}
     */
    public function pendientes(int $idRepartidor): array
    {
        $entregas = array_column($this->filas(
            "SELECT estado, COUNT(*) AS n FROM entrega
              WHERE id_repartidor = ? AND estado IN ('asignada', 'en_ruta') GROUP BY estado",
            [$idRepartidor]
        ), 'n', 'estado');

        $rutas = [];
        if ($this->hayRutas()) {
            $rutas = array_column($this->filas(
                "SELECT estado, COUNT(*) AS n FROM ruta
                  WHERE id_repartidor = ? AND estado IN ('asignada', 'en_curso') GROUP BY estado",
                [$idRepartidor]
            ), 'n', 'estado');
        }

        return [
            'entregas_asignadas' => (int) ($entregas['asignada'] ?? 0),
            'entregas_en_ruta'   => (int) ($entregas['en_ruta'] ?? 0),
            'rutas_asignadas'    => (int) ($rutas['asignada'] ?? 0),
            'rutas_en_curso'     => (int) ($rutas['en_curso'] ?? 0),
        ];
    }

    /** ¿Tiene pedidos, entregas o rutas a su nombre? (entonces no se borra, se da de baja) */
    public function tieneHistorial(int $idRepartidor): bool
    {
        $sql = 'EXISTS (SELECT 1 FROM entrega WHERE id_repartidor = ?)
                OR EXISTS (SELECT 1 FROM pedido WHERE id_repartidor = ?)';
        $params = [$idRepartidor, $idRepartidor];

        if ($this->hayRutas()) {
            $sql .= ' OR EXISTS (SELECT 1 FROM ruta WHERE id_repartidor = ?)';
            $params[] = $idRepartidor;
        }

        return (bool) $this->valor('SELECT ' . $sql, $params);
    }

    /**
     * Da de baja la ficha conservando todo su historial.
     *
     *  - Si tiene entregas en camino o una ruta en curso, NO se puede: alguien trae la
     *    mercancía y hay que cerrar esas entregas primero (entregada / fallida).
     *  - Lo que aún no sale (entregas y rutas "asignada") se cancela solo si $cancelarAsignadas;
     *    los pedidos quedan otra vez disponibles para asignarse a otro repartidor.
     *
     * Todo en una transacción y con las filas bloqueadas, para que nadie le asigne algo
     * mientras se da de baja.
     *
     * @return array{entregas_canceladas:int, rutas_canceladas:int, ya_estaba:bool}
     *
     * @throws DomainException si no se puede dar de baja
     */
    public function darDeBaja(int $idRepartidor, ?string $motivo, ?int $porUsuario, bool $cancelarAsignadas): array
    {
        if (! $this->tieneBaja()) {
            // Detalle técnico solo para superadmin/developer (y en el log); los demás, a quién avisar.
            log_message('error', 'Repartidores: falta la migración AddBajaRepartidor (php spark migrate).');
            helper('ui');

            throw new DomainException(mensaje_tecnico(
                'Falta ejecutar la migración de baja de repartidores (php spark migrate).',
                'La baja de repartidores todavía no está activada. Avisa al administrador del sistema.'
            ));
        }

        return $this->transaccion(function () use ($idRepartidor, $motivo, $porUsuario, $cancelarAsignadas): array {
            $r = $this->fila('SELECT * FROM repartidor WHERE id = ? FOR UPDATE', [$idRepartidor]);
            if ($r === null) {
                throw new DomainException('Error: el repartidor no existe.');
            }
            if (self::deBaja($r)) {
                return ['entregas_canceladas' => 0, 'rutas_canceladas' => 0, 'ya_estaba' => true];
            }

            $nombre = self::nombreCompleto($r);

            // Bloquea su trabajo abierto antes de contarlo.
            $this->filas("SELECT id FROM entrega WHERE id_repartidor = ? AND estado IN ('asignada', 'en_ruta') FOR UPDATE", [$idRepartidor]);
            $rutas = $this->hayRutas()
                ? $this->filas("SELECT id, estado FROM ruta WHERE id_repartidor = ? AND estado IN ('asignada', 'en_curso') FOR UPDATE", [$idRepartidor])
                : [];

            $p = $this->pendientes($idRepartidor);

            if ($p['entregas_en_ruta'] > 0 || $p['rutas_en_curso'] > 0) {
                throw new DomainException(sprintf(
                    'Error: %s tiene %s. Pídele que las cierre o márcalas como fallidas en Entregas antes de darlo de baja.',
                    $nombre,
                    self::plural($p['entregas_en_ruta'] ?: $p['rutas_en_curso'], $p['entregas_en_ruta'] > 0 ? 'entrega en camino' : 'ruta en curso', $p['entregas_en_ruta'] > 0 ? 'entregas en camino' : 'rutas en curso')
                ));
            }

            $abiertas = $p['entregas_asignadas'] + $p['rutas_asignadas'];
            if ($abiertas > 0 && ! $cancelarAsignadas) {
                throw new DomainException(sprintf(
                    'Error: %s tiene %s sin salir. Confirma que se cancelen para reasignarlas.',
                    $nombre,
                    self::plural($p['entregas_asignadas'], 'entrega asignada', 'entregas asignadas')
                ));
            }

            $incidencia = mb_substr('Baja del repartidor' . ($motivo !== null && $motivo !== '' ? ': ' . $motivo : '.'), 0, 500);

            // Rutas que no han salido: se cancelan (sus entregas quedan sin ruta) …
            $rutasCanceladas = 0;
            $m_ruta = new Modelo_ruta($this->pdo);
            foreach ($rutas as $ruta) {
                if (! $m_ruta->cancelar((int) $ruta['id'])) {
                    throw new DomainException('Error: la ruta #' . (int) $ruta['id'] . ' de ' . $nombre . ' no se pudo cancelar; revísala en Rutas.');
                }
                $rutasCanceladas++;
            }

            // … y sus entregas sin salir se cancelan: el pedido vuelve a "por asignar".
            $entregasCanceladas = $this->afectar(
                "UPDATE entrega SET estado = 'cancelada', incidencia = ?
                  WHERE id_repartidor = ? AND estado = 'asignada'",
                [$incidencia, $idRepartidor]
            );

            $this->afectar(
                'UPDATE repartidor SET activo = 0, fecha_baja = NOW(), motivo_baja = ?, baja_por = ? WHERE id = ?',
                [$motivo !== null && $motivo !== '' ? mb_substr($motivo, 0, 255) : null, $porUsuario, $idRepartidor]
            );

            return ['entregas_canceladas' => $entregasCanceladas, 'rutas_canceladas' => $rutasCanceladas, 'ya_estaba' => false];
        });
    }

    /** ¿La ficha está enlazada a una cuenta que hoy tiene el rol de repartidor? */
    public function cuentaEsRepartidor(array $r): bool
    {
        if (empty($r['id_user'])) {
            return false;
        }

        return (bool) $this->valor(
            "SELECT EXISTS (SELECT 1 FROM auth_groups_users g JOIN users u ON u.id = g.user_id AND u.deleted_at IS NULL
                             WHERE g.user_id = ? AND g.`group` = 'repartidor')",
            [(int) $r['id_user']]
        );
    }

    /** Vuelve a activar una ficha dada de baja (mismo id: conserva su historial). */
    public function reactivar(int $idRepartidor): bool
    {
        if (! $this->tieneBaja()) {
            return false;
        }

        return $this->afectar(
            'UPDATE repartidor SET activo = 1, fecha_baja = NULL, motivo_baja = NULL, baja_por = NULL WHERE id = ? AND activo = 0',
            [$idRepartidor]
        ) > 0;
    }

    private static ?bool $conRutas = null;

    private function hayRutas(): bool
    {
        if (self::$conRutas === null) {
            try {
                $this->filas('SELECT id, estado, id_repartidor FROM ruta LIMIT 0');
                self::$conRutas = true;
            } catch (\Throwable) {
                self::$conRutas = false;
            }
        }

        return self::$conRutas;
    }

    private static function plural(int $n, string $uno, string $varios): string
    {
        return $n . ' ' . ($n === 1 ? $uno : $varios);
    }

    private static ?bool $conCuenta = null;

    /** ¿Ya existe la columna `id_user` (enlace con la cuenta de Shield)? */
    public function tieneCuenta(): bool
    {
        if (self::$conCuenta === null) {
            try {
                $this->filas('SELECT id_user FROM repartidor LIMIT 0');
                self::$conCuenta = true;
            } catch (\Throwable) {
                self::$conCuenta = false;
            }
        }

        return self::$conCuenta;
    }

    /**
     * Da de alta como repartidor a cada cuenta del grupo "repartidor" que todavía no tiene
     * ficha en esta tabla, ya enlazada (repartidor.id_user). Así basta con poner a alguien en
     * el grupo (p. ej. `php spark shield:user addgroup -n usuario -g repartidor`) para que
     * aparezca en Repartidores, se le puedan asignar entregas y vea las suyas al entrar.
     *
     * La ficha se crea con el nombre de usuario y el resto de los datos vacíos. El repartidor
     * los completa al iniciar sesión (App\Filters\RepartidorDatosGuard → repartidor/mis-datos);
     * el personal también puede hacerlo desde Repartidores (aparece marcada "Faltan datos").
     *
     * @param int|null $soloUsuario revisar solo esta cuenta (al iniciar sesión)
     *
     * @return int fichas creadas
     */
    public function sincronizarCuentas(?int $soloUsuario = null): int
    {
        if (! $this->tieneCuenta()) {
            return 0;
        }

        $sql = "SELECT u.id, u.username, ai.secret AS correo
                  FROM auth_groups_users g
                  JOIN users u ON u.id = g.user_id AND u.deleted_at IS NULL
             LEFT JOIN auth_identities ai ON ai.user_id = u.id AND ai.type = 'email_password'
             LEFT JOIN repartidor r ON r.id_user = u.id
                 WHERE g.`group` = 'repartidor' AND r.id IS NULL";
        $params = [];
        if ($soloUsuario !== null) {
            $sql .= ' AND u.id = ?';
            $params[] = $soloUsuario;
        }

        $creadas = 0;
        foreach ($this->filas($sql, $params) as $u) {
            $nombre = trim((string) ($u['username'] ?? ''));
            if ($nombre === '') {
                $nombre = strstr((string) ($u['correo'] ?? ''), '@', true) ?: ('Cuenta ' . (int) $u['id']);
            }

            try {
                $this->afectar(
                    'INSERT INTO repartidor (nombre, ape_pat, ape_mat, telefono, direccion, notas, id_user)
                     VALUES (?, \'\', NULL, \'\', \'\', ?, ?)',
                    [
                        mb_substr($nombre, 0, 100),
                        'Alta automática desde la cuenta "' . mb_substr($nombre, 0, 60) . '" (grupo repartidor). Completa sus datos.',
                        (int) $u['id'],
                    ]
                );
                $creadas++;
            } catch (\Throwable $e) {
                // Otra petición la creó al mismo tiempo (llave UNIQUE de id_user): no pasa nada.
                log_message('warning', 'Repartidor automático no creado para la cuenta ' . (int) $u['id'] . ': ' . $e->getMessage());
            }
        }

        // Cuentas que volvieron al grupo "repartidor" (p. ej. con `php spark shield:user addgroup`):
        // se reactiva su ficha de antes, con su historial.
        if ($this->tieneBaja()) {
            $sql = "UPDATE repartidor r
                      JOIN auth_groups_users g ON g.user_id = r.id_user AND g.`group` = 'repartidor'
                      JOIN users u ON u.id = g.user_id AND u.deleted_at IS NULL
                       SET r.activo = 1, r.fecha_baja = NULL, r.motivo_baja = NULL, r.baja_por = NULL
                     WHERE r.activo = 0";
            $params = [];
            if ($soloUsuario !== null) {
                $sql .= ' AND u.id = ?';
                $params[] = $soloUsuario;
            }
            $this->afectar($sql, $params);
        }

        return $creadas;
    }

    /** ¿A la ficha le faltan datos obligatorios? (alta automática sin completar) */
    public static function faltanDatos(array $r): bool
    {
        foreach (['nombre', 'ape_pat', 'telefono', 'direccion'] as $campo) {
            if (trim((string) ($r[$campo] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Enlaza al repartidor con una cuenta de Shield (repartidor.id_user).
     * `id_user` no está en $camposPermitidos a propósito: no debe poder llegar desde un formulario.
     * Falla si la cuenta ya está enlazada a otro repartidor (llave UNIQUE).
     */
    public function vincularUsuario(int $idRepartidor, int $idUser): void
    {
        $this->afectar('UPDATE repartidor SET id_user = ? WHERE id = ?', [$idUser, $idRepartidor]);
    }
}
