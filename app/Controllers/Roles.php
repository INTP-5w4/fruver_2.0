<?php

namespace App\Controllers;

use App\Libraries\Roles as CatalogoRoles;
use App\Models\Modelo_bitacora_rol;
use App\Models\Modelo_repartidor;
use App\Models\Modelo_roles;
use App\Models\PdoModelo;
use DomainException;
use Throwable;

/**
 * Gestión de roles (solo superadmin; ver Config\Routes).
 *
 * Por qué solo superadmin: asignar un rol es dar permisos. Si un admin pudiera hacerlo,
 * podría convertirse (o convertir a otro) en superadmin. Los admins siguen dando de alta
 * repartidores, clientes, etc. desde sus pantallas.
 *
 * Reglas:
 *  - Un rol por cuenta (se elige de la lista: solo los roles que existen en Config\AuthGroups).
 *  - Nadie cambia su propio rol (evita quedarse fuera por error).
 *  - Siempre queda al menos un superadmin.
 *  - El rol son PERMISOS; las fichas (cliente, repartidor) son HISTORIA y nunca se borran
 *    por cambiar de rol:
 *      · Su ficha de cliente y sus pedidos siguen igual con cualquier rol (puede seguir comprando).
 *      · Al dar el rol de repartidor se crea su ficha (la completa él al iniciar sesión) o,
 *        si ya fue repartidor, se reactiva la misma con todo su historial.
 *      · Al quitarlo, su ficha se da de baja: conserva entregas y rutas, pero ya no se le asigna
 *        nada. Si trae entregas en camino no se puede (hay que cerrarlas); las que aún no salen
 *        se cancelan solo si se confirma, y sus pedidos quedan listos para otro repartidor.
 *  - Cada cambio queda en rol_bitacora (quién, a quién, de qué a qué, por qué).
 */
class Roles extends BaseController
{
    public function index()
    {
        $buscar = $this->textoBusqueda();
        $rol    = (string) $this->request->getGet('rol');
        $rol    = CatalogoRoles::existe($rol) ? $rol : '';
        $modelo = new Modelo_roles();

        $enumAlDia = true;
        try {
            $enumAlDia = CatalogoRoles::enumAlDia(db_connect());
        } catch (Throwable $e) {
            log_message('error', 'Roles: no se pudo revisar el ENUM. ' . $e->getMessage());
        }

        $cuentas = $modelo->listar($buscar, $rol);

        // Trabajo abierto de los repartidores de esta página (para avisar antes de quitarles el rol).
        $pendientes = [];
        $m_rep      = new Modelo_repartidor();
        foreach ($cuentas as $c) {
            if ($c['id_repartidor'] !== null && (int) $c['rep_activo'] === 1
                && in_array('repartidor', explode(',', (string) $c['roles']), true)) {
                $pendientes[(int) $c['id']] = $m_rep->pendientes((int) $c['id_repartidor']);
            }
        }

        $bitacora = [];
        try {
            $bitacora = (new Modelo_bitacora_rol())->recientes(10);
        } catch (Throwable $e) {
            log_message('error', 'Roles: no se pudo leer la bitácora. ' . $e->getMessage());
        }

        return view('roles', [
            'cuentas'    => $cuentas,
            'pager'      => $modelo->pager,
            'conteo'     => $modelo->conteo(),
            'catalogo'   => CatalogoRoles::catalogo(),
            'buscar'     => $buscar,
            'rol'        => $rol,
            'yo'         => (int) auth()->id(),
            'enumAlDia'  => $enumAlDia,
            'pendientes' => $pendientes,
            'bitacora'   => $bitacora,
        ]);
    }

    /** POST roles/{id}: cambia el rol de una cuenta. */
    public function cambiar($id = null)
    {
        $volver = redirect()->to(site_url('roles') . $this->consultaVuelta());
        $idUser = ctype_digit((string) $id) ? (int) $id : 0;
        $nuevo  = $this->post('rol');

        if ($idUser === 0 || ! CatalogoRoles::existe($nuevo)) {
            return $volver->with('error', 'Elige un rol de la lista.');
        }
        if ($idUser === (int) auth()->id()) {
            return $volver->with('error', 'No puedes cambiar tu propio rol. Pídeselo a otro superadmin.');
        }

        $cuenta = auth()->getProvider()->findById($idUser);
        if ($cuenta === null) {
            return $volver->with('error', 'La cuenta no existe.');
        }

        $antes = $cuenta->getGroups();
        if ($antes === [$nuevo]) {
            return $volver->with('mensaje', 'La cuenta ' . $this->nombre($cuenta) . ' ya tenía ese rol.');
        }

        // Siempre debe quedar al menos un superadmin.
        if (in_array('superadmin', $antes, true) && $nuevo !== 'superadmin'
            && (new Modelo_roles())->cuantosCon('superadmin') <= 1) {
            return $volver->with('error', 'Es el único superadmin: asigna otro antes de quitarle este rol.');
        }

        $motivo   = mb_substr($this->post('motivo'), 0, 255);
        $cancelar = $this->post('cancelar_pendientes') === '1';
        $m_rep    = new Modelo_repartidor();
        $ficha    = $m_rep->deUsuario($idUser);
        $nombre   = $this->nombre($cuenta);

        // ¿Deja de ser repartidor? Su ficha activa se dará de baja: primero se revisa su trabajo abierto.
        $daDeBaja = in_array('repartidor', $antes, true) && $nuevo !== 'repartidor'
            && $ficha !== null && ! Modelo_repartidor::deBaja($ficha) && $m_rep->tieneBaja();

        if ($daDeBaja) {
            $p = $m_rep->pendientes((int) $ficha['id']);

            if ($p['entregas_en_ruta'] > 0 || $p['rutas_en_curso'] > 0) {
                return $volver->with('error', $nombre . ' tiene entregas en camino. Deben cerrarse (entregada o fallida) '
                    . 'o cancelarse en Entregas antes de quitarle el rol de repartidor.');
            }
            if ($p['entregas_asignadas'] > 0 && ! $cancelar) {
                return $volver->with('error', $nombre . ' tiene ' . $p['entregas_asignadas']
                    . ($p['entregas_asignadas'] === 1 ? ' entrega asignada' : ' entregas asignadas')
                    . ' que aún no salen. Marca "Cancelar sus entregas pendientes" para continuar: '
                    . 'esos pedidos quedarán listos para asignarse a otro repartidor.');
            }
        }

        // Orden pensado para que una falla a medias no deje pedidos huérfanos: Shield usa otra
        // conexión, así que no cabe todo en una transacción. Primero el rol (si falla, nada cambió);
        // luego la baja, que vuelve a revisar todo con las filas bloqueadas; si esa falla, el rol
        // se revierte.
        try {
            $cuenta->syncGroups($nuevo);
        } catch (Throwable $e) {
            return $volver->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo cambiar el rol.'));
        }

        $mensaje = 'La cuenta ' . $nombre . ' ahora es: ' . CatalogoRoles::titulo($nuevo) . '.';
        $detalle = null;

        if ($daDeBaja) {
            try {
                $baja = $m_rep->darDeBaja((int) $ficha['id'], $motivo, (int) auth()->id(), $cancelar);
            } catch (Throwable $e) {
                try {
                    $cuenta->syncGroups(...$antes);
                } catch (Throwable $e2) {
                    log_message('critical', 'Roles: no se pudo revertir el rol de la cuenta ' . $idUser . ': ' . $e2->getMessage());
                }

                // Una regla de negocio (p. ej. le asignaron algo mientras tanto) se explica tal cual;
                // cualquier otra falla queda en el log y se muestra un mensaje genérico.
                return $volver->with('error', $e instanceof DomainException
                    ? $e->getMessage() . ' El rol no se cambió.'
                    : PdoModelo::mensajeSeguro($e, 'No se pudo dar de baja su ficha de repartidor; el rol no se cambió.'));
            }

            $detalle = 'Ficha de repartidor #' . (int) $ficha['id'] . ' dada de baja';
            $mensaje .= ' Su ficha de repartidor quedó dada de baja: conserva su historial, pero ya no se le pueden asignar entregas.';
            if ($baja['entregas_canceladas'] > 0) {
                $detalle .= '; ' . $baja['entregas_canceladas'] . ' entrega(s) y ' . $baja['rutas_canceladas'] . ' ruta(s) canceladas';
                $mensaje .= ' Se cancelaron ' . $baja['entregas_canceladas'] . ' entrega(s) pendientes: asigna esos pedidos a otro repartidor en Entregas.';
            }
        } elseif ($nuevo === 'repartidor') {
            $estabaDeBaja = $ficha !== null && Modelo_repartidor::deBaja($ficha);
            try {
                $m_rep->sincronizarCuentas($idUser); // crea la ficha o reactiva la que tenía
            } catch (Throwable $e) {
                log_message('error', 'Roles: no se pudo crear o reactivar la ficha de repartidor. ' . $e->getMessage());
            }

            if ($estabaDeBaja) {
                $detalle  = 'Ficha de repartidor #' . (int) $ficha['id'] . ' reactivada';
                $mensaje .= ' Se reactivó su ficha de repartidor de antes, con todo su historial.';
            } elseif ($ficha === null) {
                $detalle  = 'Ficha de repartidor creada';
                $mensaje .= ' La próxima vez que inicie sesión se le pedirá completar sus datos de repartidor.';
            }
        }

        log_message('notice', 'Roles: la cuenta {usuario} cambió el rol de la cuenta {id} de [{antes}] a {nuevo}. {detalle}', [
            'usuario' => auth()->id(),
            'id'      => $idUser,
            'antes'   => implode(', ', $antes),
            'nuevo'   => $nuevo,
            'detalle' => (string) $detalle,
        ]);

        try {
            $yo = auth()->user();
            (new Modelo_bitacora_rol())->registrar(
                $idUser,
                trim($nombre, '"'),
                implode(',', $antes),
                $nuevo,
                $motivo,
                $detalle,
                (int) auth()->id(),
                (string) ($yo->username ?: $yo->email ?: ('#' . $yo->id))
            );
        } catch (Throwable $e) {
            log_message('error', 'Roles: no se pudo escribir la bitácora. ' . $e->getMessage());
        }

        return $volver->with('mensaje', $mensaje);
    }

    private function nombre(object $cuenta): string
    {
        return '"' . (string) ($cuenta->username ?: $cuenta->email ?: ('#' . $cuenta->id)) . '"';
    }

    /** Conserva búsqueda, filtro y página al regresar (solo esos parámetros). */
    private function consultaVuelta(): string
    {
        $q = array_filter([
            'buscar' => mb_substr($this->post('buscar'), 0, 100),
            'rol'    => CatalogoRoles::existe($this->post('filtro_rol')) ? $this->post('filtro_rol') : '',
            'page'   => ctype_digit($this->post('page')) ? $this->post('page') : '',
        ], static fn (string $v): bool => $v !== '');

        return $q !== [] ? '?' . http_build_query($q) : '';
    }
}
