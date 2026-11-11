<?php

namespace App\Models;

use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use Throwable;

/**
 * Constancias del Aviso de Privacidad y de las finalidades voluntarias V1–V4.
 *
 * La tabla solo recibe INSERT: cada aceptación, cambio o revocación es una fila nueva.
 * El estado vigente de una cuenta es su fila más reciente (ver migración 2026-11-10-100000).
 */
class Modelo_consentimiento extends PdoModelo
{
    protected string $tabla         = 'consentimiento';
    protected string $llavePrimaria = 'id';

    protected array $camposPermitidos = [
        'id_user', 'version_aviso', 'tipo_relacion', 'acepta_aviso', 'mayor_edad',
        'v1', 'v2', 'v3', 'v4', 'canal_telefono', 'canal_mensajeria', 'canal_correo',
        'telefono_contacto', 'origen', 'ip_address', 'user_agent',
    ];

    public const ORIGENES = ['registro', 'aceptacion', 'preferencias', 'revocacion'];

    /** Grupos de Shield que son "usuario interno" del Sistema; el resto son clientes. */
    public const GRUPOS_INTERNOS = ['superadmin', 'admin', 'developer', 'repartidor'];

    private const FINALIDADES = ['v1', 'v2', 'v3', 'v4'];

    private static ?bool $hayTabla = null;

    /**
     * ¿Ya existe la tabla? Si todavía no se corrió la migración, el sistema sigue
     * funcionando (sin pedir el aviso) en lugar de fallar en todas las pantallas.
     */
    public function tablaExiste(): bool
    {
        if (self::$hayTabla === null) {
            try {
                $this->filas('SELECT id FROM consentimiento LIMIT 1');
                self::$hayTabla = true;
            } catch (Throwable) {
                self::$hayTabla = false;
            }
        }

        return self::$hayTabla;
    }

    public static function tipoRelacion(User $usuario): string
    {
        return $usuario->inGroup(...self::GRUPOS_INTERNOS) ? 'usuario_interno' : 'cliente';
    }

    /**
     * Guarda una constancia nueva.
     *
     * @param array<string, int|string|null> $eleccion salida de FormularioConsentimiento::leer()
     */
    public function registrar(
        int $idUsuario,
        array $eleccion,
        string $origen,
        string $tipoRelacion,
        string $version,
        string $ip,
        ?string $userAgent
    ): int {
        if (! in_array($origen, self::ORIGENES, true)) {
            throw new InvalidArgumentException('Origen de consentimiento no válido.');
        }

        return $this->insert([
            ...$eleccion,
            'id_user'       => $idUsuario,
            'version_aviso' => mb_substr($version, 0, 20),
            'tipo_relacion' => $tipoRelacion === 'usuario_interno' ? 'usuario_interno' : 'cliente',
            'origen'        => $origen,
            'ip_address'    => mb_substr($ip, 0, 45),
            'user_agent'    => $userAgent === null || $userAgent === '' ? null : mb_substr($userAgent, 0, 255),
        ]);
    }

    /** Estado vigente (fila más reciente) o null si la cuenta nunca ha aceptado el aviso. */
    public function vigente(int $idUsuario): ?array
    {
        return $this->fila(
            'SELECT * FROM consentimiento WHERE id_user = ? ORDER BY id DESC LIMIT 1',
            [$idUsuario]
        );
    }

    /** ¿La cuenta aceptó esta versión del aviso? */
    public function aceptoVersion(int $idUsuario, string $version): bool
    {
        return $this->valor(
            'SELECT 1 FROM consentimiento WHERE id_user = ? AND version_aviso = ? AND acepta_aviso = 1 LIMIT 1',
            [$idUsuario, $version]
        ) !== null;
    }

    /** @return list<array<string,mixed>> */
    public function historial(int $idUsuario, int $limite = 20): array
    {
        return $this->filas(
            'SELECT * FROM consentimiento WHERE id_user = ? ORDER BY id DESC LIMIT ' . max(1, min(100, $limite)),
            [$idUsuario]
        );
    }

    /**
     * Cuentas cuyo estado vigente autoriza una finalidad (por ejemplo, 'v1' para promociones).
     * Cualquier función futura que envíe promociones, recordatorios o encuestas, o que
     * personalice ofertas, debe filtrar a los destinatarios con este método.
     *
     * @return list<int> ids de users
     */
    public function usuariosQueAceptan(string $finalidad): array
    {
        if (! in_array($finalidad, self::FINALIDADES, true)) {
            throw new InvalidArgumentException('Finalidad no válida.');
        }

        $filas = $this->filas(
            "SELECT c.id_user
               FROM consentimiento c
               JOIN (SELECT id_user, MAX(id) AS ultimo FROM consentimiento GROUP BY id_user) u
                 ON u.ultimo = c.id
              WHERE c.`{$finalidad}` = 1"
        );

        return array_map('intval', array_column($filas, 'id_user'));
    }

    /**
     * Personas que hoy autorizan la finalidad, con los canales que eligieron.
     * Solo cuentas no eliminadas; los datos de cliente vienen de su perfil (si lo tiene).
     * El teléfono de contacto es el que la persona dio para esta finalidad, no el de entregas.
     *
     * @return list<array<string,mixed>>
     */
    public function destinatarios(string $finalidad): array
    {
        if (! in_array($finalidad, self::FINALIDADES, true)) {
            throw new InvalidArgumentException('Finalidad no válida.');
        }

        return $this->filas(
            "SELECT c.id_user, c.canal_telefono, c.canal_mensajeria, c.canal_correo, c.telefono_contacto,
                    c.created_at AS autorizado_en,
                    u.username, i.secret AS correo,
                    cl.id AS id_cliente, cl.nombre, cl.ape_pat
               FROM consentimiento c
               JOIN (SELECT id_user, MAX(id) AS ultimo FROM consentimiento GROUP BY id_user) v ON v.ultimo = c.id
               JOIN users u ON u.id = c.id_user AND u.deleted_at IS NULL
          LEFT JOIN auth_identities i ON i.user_id = u.id AND i.type = 'email_password'
          LEFT JOIN cliente cl ON cl.id_user = u.id
              WHERE c.`{$finalidad}` = 1
           ORDER BY cl.nombre IS NULL, cl.nombre, u.username"
        );
    }

    /**
     * Cuántas personas autorizan hoy cada finalidad.
     *
     * @return array<string,int> v1..v4 => total
     */
    public function conteoFinalidades(): array
    {
        $fila = $this->fila(
            'SELECT COALESCE(SUM(c.v1), 0) AS v1, COALESCE(SUM(c.v2), 0) AS v2,
                    COALESCE(SUM(c.v3), 0) AS v3, COALESCE(SUM(c.v4), 0) AS v4
               FROM consentimiento c
               JOIN (SELECT id_user, MAX(id) AS ultimo FROM consentimiento GROUP BY id_user) v ON v.ultimo = c.id
               JOIN users u ON u.id = c.id_user AND u.deleted_at IS NULL'
        ) ?? [];

        return array_map('intval', array_intersect_key($fila + array_fill_keys(self::FINALIDADES, 0), array_flip(self::FINALIDADES)));
    }

    /** ¿El estado vigente de la cuenta autoriza la finalidad? */
    public function autoriza(int $idUsuario, string $finalidad): bool
    {
        if (! in_array($finalidad, self::FINALIDADES, true)) {
            throw new InvalidArgumentException('Finalidad no válida.');
        }

        return (int) ($this->vigente($idUsuario)[$finalidad] ?? 0) === 1;
    }

    // ─────────────────────────────────────────────────────────────
    // PLAZOS DE CONSERVACIÓN (sección 9 del aviso)
    // ─────────────────────────────────────────────────────────────

    /** Borra los registros de inicio de sesión más viejos que $dias. Devuelve cuántos borró. */
    public function depurarRegistroAccesos(int $dias): int
    {
        if ($dias < 1) {
            throw new InvalidArgumentException('El plazo debe ser de al menos un día.');
        }

        return $this->afectar(
            'DELETE FROM auth_logins WHERE date < (NOW() - INTERVAL ? DAY)',
            [$dias]
        );
    }

    /** Borra los identificadores de "recordarme" ya vencidos. */
    public function depurarRecordarmeVencidos(): int
    {
        return $this->afectar('DELETE FROM auth_remember_tokens WHERE expires < NOW()');
    }
}
