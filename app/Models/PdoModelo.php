<?php

namespace App\Models;

use App\Libraries\ConexionPdo;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Modelo base con PDO para todo el proyecto.
 *
 * REGLAS DEL PATRÓN MVC EN FRUVER
 *  - Todo SQL vive en los modelos que extienden esta clase. Los controladores y las
 *    vistas no escriben ni ejecutan consultas.
 *  - Todo valor que viene de fuera (POST, GET, sesión, JSON) se envía como parámetro
 *    de una sentencia preparada. Jamás se concatena dentro del SQL.
 *  - Los nombres de tabla/columna salen únicamente de las propiedades de cada modelo
 *    (constantes del programador) o de listas blancas; nunca del usuario.
 *
 * Los métodos find / findAll / insert / update / delete / insertID conservan los
 * nombres de CodeIgniter para que los controladores cambien lo mínimo.
 */
abstract class PdoModelo
{
    protected string $tabla         = '';
    protected string $llavePrimaria = 'id';

    /** Lista blanca de columnas que insert()/update() aceptan (anti "mass assignment"). */
    protected array $camposPermitidos = [];

    /** Paginador de CodeIgniter (lo llenan los métodos que paginan). */
    public $pager = null;

    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? ConexionPdo::obtener();
    }

    // ─────────────────────────────────────────────────────────────
    // BAJO NIVEL (sentencias preparadas)
    // ─────────────────────────────────────────────────────────────

    /**
     * Prepara y ejecuta una consulta. $params puede ser posicional (?) o con nombre (:x).
     */
    protected function ejecutarSql(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo->prepare($sql);

        foreach ($params as $clave => $valor) {
            $marcador = is_int($clave) ? $clave + 1 : ':' . ltrim((string) $clave, ':');
            $st->bindValue($marcador, $valor, $this->tipoPdo($valor));
        }

        $st->execute();

        return $st;
    }

    private function tipoPdo(mixed $valor): int
    {
        return match (true) {
            is_int($valor)  => PDO::PARAM_INT,
            is_bool($valor) => PDO::PARAM_BOOL,
            $valor === null => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
    }

    /** @return list<array<string,mixed>> */
    protected function filas(string $sql, array $params = []): array
    {
        return $this->ejecutarSql($sql, $params)->fetchAll();
    }

    protected function fila(string $sql, array $params = []): ?array
    {
        $fila = $this->ejecutarSql($sql, $params)->fetch();

        return $fila === false ? null : $fila;
    }

    protected function valor(string $sql, array $params = []): mixed
    {
        $v = $this->ejecutarSql($sql, $params)->fetchColumn();

        return $v === false ? null : $v;
    }

    /** Ejecuta INSERT/UPDATE/DELETE y devuelve las filas afectadas. */
    protected function afectar(string $sql, array $params = []): int
    {
        return $this->ejecutarSql($sql, $params)->rowCount();
    }

    // ─────────────────────────────────────────────────────────────
    // CRUD GENÉRICO
    // ─────────────────────────────────────────────────────────────

    public function find(int|string|null $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        return $this->fila(
            "SELECT * FROM `{$this->tabla}` WHERE `{$this->llavePrimaria}` = ? LIMIT 1",
            [$id]
        );
    }

    /** @return list<array<string,mixed>> */
    public function findAll(): array
    {
        return $this->filas("SELECT * FROM `{$this->tabla}` ORDER BY `{$this->llavePrimaria}` ASC");
    }

    /** Inserta una fila (solo columnas de la lista blanca) y devuelve el nuevo id. */
    public function insert(array $datos): int
    {
        $datos = $this->soloPermitidos($datos);

        $columnas = implode(', ', array_map(fn ($c) => "`{$c}`", array_keys($datos)));
        $marcas   = implode(', ', array_fill(0, count($datos), '?'));

        $this->ejecutarSql(
            "INSERT INTO `{$this->tabla}` ({$columnas}) VALUES ({$marcas})",
            array_values($datos)
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function insertID(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int|string|null $id, array $datos): bool
    {
        if ($id === null || $id === '') {
            return false;
        }

        $datos = $this->soloPermitidos($datos);

        $set = implode(', ', array_map(fn ($c) => "`{$c}` = ?", array_keys($datos)));

        $this->ejecutarSql(
            "UPDATE `{$this->tabla}` SET {$set} WHERE `{$this->llavePrimaria}` = ?",
            [...array_values($datos), $id]
        );

        return true; // si no hubo excepción, la sentencia se ejecutó
    }

    public function delete(int|string|null $id): bool
    {
        if ($id === null || $id === '') {
            return false;
        }

        return $this->afectar(
            "DELETE FROM `{$this->tabla}` WHERE `{$this->llavePrimaria}` = ?",
            [$id]
        ) > 0;
    }

    /** Elimina las filas de una columna con un valor (p. ej. por id_pedido). */
    protected function eliminarPor(string $columna, int|string $valor): int
    {
        $this->columnaValida($columna);

        return $this->afectar("DELETE FROM `{$this->tabla}` WHERE `{$columna}` = ?", [$valor]);
    }

    /** @return list<array<string,mixed>> */
    protected function buscarPor(string $columna, int|string $valor, string $orden = ''): array
    {
        $this->columnaValida($columna);
        $ordenSql = $orden === '' ? '' : ' ORDER BY ' . $this->ordenSeguro($orden);

        return $this->filas("SELECT * FROM `{$this->tabla}` WHERE `{$columna}` = ?{$ordenSql}", [$valor]);
    }

    // ─────────────────────────────────────────────────────────────
    // TRANSACCIONES
    // ─────────────────────────────────────────────────────────────

    /**
     * Ejecuta $fn dentro de una transacción. Si lanza una excepción hace ROLLBACK
     * y la vuelve a lanzar; si no, hace COMMIT y devuelve lo que devolvió $fn.
     */
    public function transaccion(callable $fn): mixed
    {
        $propia = ! $this->pdo->inTransaction();

        if ($propia) {
            $this->pdo->beginTransaction();
        }

        try {
            $resultado = $fn($this);

            if ($propia) {
                $this->pdo->commit();
            }

            return $resultado;
        } catch (Throwable $e) {
            if ($propia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PAGINACIÓN (misma salida que Model::paginate de CodeIgniter)
    // ─────────────────────────────────────────────────────────────

    /**
     * Pagina una consulta SELECT ya armada (con sus parámetros) y deja el paginador
     * de CodeIgniter en $this->pager, para usarlo en la vista con $pager->links().
     *
     * @return list<array<string,mixed>>
     */
    protected function paginarSql(string $sql, array $params = [], int $porPagina = 20, string $grupo = 'default'): array
    {
        $total = (int) $this->valor("SELECT COUNT(*) FROM ({$sql}) AS conteo", $params);

        $pager  = service('pager');
        $pagina = $pager->getCurrentPage($grupo);

        $this->pager = $pager->store($grupo, $pagina, $porPagina, $total);

        $porPaginaReal = (int) $pager->getPerPage($grupo);
        $offset        = max(0, ($pager->getCurrentPage($grupo) - 1) * $porPaginaReal);

        return $this->filas($sql . ' LIMIT ' . $porPaginaReal . ' OFFSET ' . $offset, $params);
    }

    // ─────────────────────────────────────────────────────────────
    // UTILIDADES
    // ─────────────────────────────────────────────────────────────

    /** Escapa % _ \ para que un texto de búsqueda se trate literalmente en un LIKE. */
    protected function patronLike(string $texto): string
    {
        return '%' . addcslashes($texto, '%_\\') . '%';
    }

    /** Traduce una PDOException a un mensaje seguro para mostrar al usuario. */
    public static function mensajeSeguro(Throwable $e, string $genericoPorDefecto = 'Ocurrió un error al procesar la solicitud.'): string
    {
        if ($e instanceof PDOException) {
            // Los triggers de Fruver usan SIGNAL SQLSTATE '45000' con textos "Error: ...".
            if (($e->errorInfo[0] ?? '') === '45000' && isset($e->errorInfo[2])) {
                $texto = (string) $e->errorInfo[2];
                $pos   = strpos($texto, 'Error:');

                return $pos !== false ? substr($texto, $pos) : $texto;
            }

            log_message('error', 'PDOException: ' . $e->getMessage());
        } else {
            log_message('error', get_class($e) . ': ' . $e->getMessage());
        }

        return $genericoPorDefecto;
    }

    /** ¿La excepción es por una llave foránea (registros relacionados)? */
    public static function esErrorForaneo(Throwable $e): bool
    {
        return $e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1451;
    }

    private function soloPermitidos(array $datos): array
    {
        $filtrado = array_intersect_key($datos, array_flip($this->camposPermitidos));

        if ($filtrado === []) {
            throw new InvalidArgumentException('No hay campos permitidos para guardar en ' . $this->tabla);
        }

        return $filtrado;
    }

    private function columnaValida(string $columna): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $columna)) {
            throw new InvalidArgumentException('Nombre de columna no válido.');
        }
    }

    /** Valida "columna [ASC|DESC]" para ORDER BY (solo se llama con texto del programador). */
    private function ordenSeguro(string $orden): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*(\s+(ASC|DESC))?$/i', trim($orden))) {
            throw new InvalidArgumentException('Orden no válido.');
        }

        return trim($orden);
    }
}
