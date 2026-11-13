<?php

namespace App\Models;

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

        // Con el nombre de la cuenta de acceso enlazada, si la columna ya existe.
        $sql = $conCuenta
            ? 'SELECT r.*, u.username AS cuenta FROM repartidor r LEFT JOIN users u ON u.id = r.id_user'
            : 'SELECT r.* FROM repartidor r';

        return $this->paginarSql($sql . $where . ' ORDER BY r.id DESC', $params, $porPagina);
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
