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

    public function __construct(?\PDO $pdo = null)
    {
        parent::__construct($pdo);

        if ($this->tieneVehiculo()) {
            $this->camposPermitidos[] = 'vehiculo';
        }
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

            $parte[] = 'nombre LIKE ?';
            $parte[] = 'ape_pat LIKE ?';
            $parte[] = 'ape_mat LIKE ?';
            $parte[] = 'telefono LIKE ?';
            array_push($params, $like, $like, $like, $like);

            $where = ' WHERE ' . implode(' OR ', $parte);
        }

        return $this->paginarSql(
            'SELECT * FROM repartidor' . $where . ' ORDER BY id DESC',
            $params,
            $porPagina
        );
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
