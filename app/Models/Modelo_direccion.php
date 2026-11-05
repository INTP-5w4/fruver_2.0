<?php

namespace App\Models;

use App\Libraries\Rutas\Punto;

class Modelo_direccion extends PdoModelo
{
    protected string $tabla         = 'direccion';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = ['colonia', 'calle', 'numero', 'municipio', 'estado', 'id_cliente'];

    /** Columnas que agrega la migración de rutas (2026-11-05-100000_AddRutasReparto). */
    public const CAMPOS_GEO = [
        'codigo_postal', 'referencias', 'direccion_formateada', 'place_id', 'lat', 'lng',
        'origen_coord', 'precision_geo', 'coord_actualizada_en', 'es_principal',
    ];

    public const ORIGENES = ['google', 'manual', 'gps_entrega'];

    private static ?bool $conGeo = null;

    public function __construct(?\PDO $pdo = null)
    {
        parent::__construct($pdo);

        if ($this->tieneColumnasGeo()) {
            $this->camposPermitidos = [...$this->camposPermitidos, ...self::CAMPOS_GEO];
        }
    }

    /**
     * ¿Ya se corrió la migración de rutas? Mientras no, la app funciona como antes
     * (mismo patrón que Modelo_cliente::tieneColumnasPreferencial).
     */
    public function tieneColumnasGeo(): bool
    {
        if (self::$conGeo === null) {
            try {
                $this->filas('SELECT lat, es_principal FROM direccion LIMIT 0');
                self::$conGeo = true;
            } catch (\Throwable) {
                self::$conGeo = false;
            }
        }

        return self::$conGeo;
    }

    /** Solo para pruebas. */
    public static function reiniciarDeteccion(): void
    {
        self::$conGeo = null;
    }

    /**
     * Lista paginada de direcciones junto con el nombre de su cliente.
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
            $where = ' WHERE (c.nombre LIKE ? OR d.id LIKE ? OR c.ape_pat LIKE ? OR d.colonia LIKE ?'
                   . ' OR d.calle LIKE ? OR d.municipio LIKE ? OR d.estado LIKE ?)';
            $params = array_fill(0, 7, $like);
        }

        return $this->paginarSql(
            'SELECT d.*, c.nombre, c.ape_pat, c.ape_mat
               FROM direccion d
               JOIN cliente c ON c.id = d.id_cliente' . $where . '
              ORDER BY d.id DESC',
            $params,
            $porPagina
        );
    }

    /**
     * Direcciones de un cliente (la principal primero).
     *
     * @return list<array<string,mixed>>
     */
    public function deCliente(int $idCliente): array
    {
        $orden = $this->tieneColumnasGeo() ? 'es_principal DESC, id DESC' : 'id DESC';

        return $this->filas("SELECT * FROM direccion WHERE id_cliente = ? ORDER BY {$orden}", [$idCliente]);
    }

    /**
     * Dirección que debe usar un pedido:
     *  - la solicitada, SOLO si pertenece al cliente (nadie puede mandar el pedido a la
     *    dirección de otro cliente manipulando el formulario);
     *  - si no se pidió ninguna, la principal del cliente (o la más reciente).
     */
    public function resolverParaPedido(int $idCliente, ?int $idSolicitada): ?int
    {
        if ($idSolicitada !== null && $idSolicitada > 0) {
            $ok = $this->valor('SELECT id FROM direccion WHERE id = ? AND id_cliente = ?', [$idSolicitada, $idCliente]);

            return $ok !== null ? (int) $ok : null;
        }

        $primera = $this->deCliente($idCliente)[0] ?? null;

        return $primera !== null ? (int) $primera['id'] : null;
    }

    /** Deja una dirección como la principal de su cliente (y quita la marca a las demás). */
    public function marcarPrincipal(int $idDireccion): void
    {
        if (! $this->tieneColumnasGeo()) {
            return;
        }

        $this->transaccion(function () use ($idDireccion): void {
            $idCliente = $this->valor('SELECT id_cliente FROM direccion WHERE id = ?', [$idDireccion]);
            if ($idCliente === null) {
                return;
            }
            $this->afectar('UPDATE direccion SET es_principal = (id = ?) WHERE id_cliente = ?', [$idDireccion, $idCliente]);
        });
    }

    /**
     * Guarda coordenadas. $origen: google (vence a los 30 días) | manual | gps_entrega.
     * Un pin manual o el GPS de una entrega no se sobrescriben con datos de Google.
     */
    public function guardarCoordenadas(int $idDireccion, Punto $p, string $origen, array $extra = []): bool
    {
        if (! $this->tieneColumnasGeo() || ! in_array($origen, self::ORIGENES, true)) {
            return false;
        }

        $sql = 'UPDATE direccion
                   SET lat = ?, lng = ?, origen_coord = ?, coord_actualizada_en = NOW(),
                       place_id = COALESCE(?, place_id),
                       direccion_formateada = COALESCE(?, direccion_formateada),
                       precision_geo = ?,
                       codigo_postal = COALESCE(codigo_postal, ?)
                 WHERE id = ?';
        $params = [
            $p->lat, $p->lng, $origen,
            $extra['place_id'] ?? null,
            $extra['direccion_formateada'] ?? null,
            $origen === 'google' ? ($extra['precision_geo'] ?? null) : strtoupper($origen),
            $extra['codigo_postal'] ?? null,
            $idDireccion,
        ];

        if ($origen === 'google') {
            $sql .= " AND (origen_coord IS NULL OR origen_coord = 'google')";
        }

        return $this->afectar($sql, $params) > 0;
    }

    /** Borra las coordenadas (p. ej. cuando cambia el texto de la dirección). */
    public function limpiarCoordenadas(int $idDireccion): void
    {
        if ($this->tieneColumnasGeo()) {
            $this->afectar(
                'UPDATE direccion SET lat = NULL, lng = NULL, origen_coord = NULL, precision_geo = NULL,
                        coord_actualizada_en = NULL, place_id = NULL, direccion_formateada = NULL
                  WHERE id = ?',
                [$idDireccion]
            );
        }
    }

    /**
     * Direcciones que necesitan geocodificarse: sin coordenadas, o con coordenadas de
     * Google con más de $dias días (Términos de servicio de Google Maps Platform).
     *
     * @return list<array<string,mixed>>
     */
    public function porGeocodificar(int $limite, int $dias, bool $todas = false): array
    {
        if (! $this->tieneColumnasGeo()) {
            return [];
        }

        $where = $todas
            ? "(origen_coord IS NULL OR origen_coord = 'google')"
            : "(lat IS NULL OR (origen_coord = 'google' AND coord_actualizada_en < (NOW() - INTERVAL ? DAY)))";

        return $this->filas(
            "SELECT * FROM direccion WHERE {$where} ORDER BY id ASC LIMIT " . max(1, min(1000, $limite)),
            $todas ? [] : [$dias]
        );
    }

    /** Cuántas direcciones hay sin ubicar / ubicadas. @return array{total:int,ubicadas:int,aproximadas:int} */
    public function resumenGeo(): array
    {
        if (! $this->tieneColumnasGeo()) {
            return ['total' => 0, 'ubicadas' => 0, 'aproximadas' => 0];
        }

        $f = $this->fila(
            "SELECT COUNT(*) AS total,
                    SUM(lat IS NOT NULL) AS ubicadas,
                    SUM(precision_geo = 'APPROXIMATE') AS aproximadas
               FROM direccion"
        );

        return ['total' => (int) $f['total'], 'ubicadas' => (int) $f['ubicadas'], 'aproximadas' => (int) $f['aproximadas']];
    }
}
