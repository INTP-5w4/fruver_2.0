<?php

namespace App\Controllers;

use App\Libraries\Roles as CatalogoRoles;
use App\Models\Modelo_repartidor;
use App\Models\Modelo_roles;
use App\Models\PdoModelo;
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
 *  - Al dar el rol de repartidor se crea su ficha; la completa él mismo al iniciar sesión.
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

        return view('roles', [
            'cuentas'   => $modelo->listar($buscar, $rol),
            'pager'     => $modelo->pager,
            'conteo'    => $modelo->conteo(),
            'catalogo'  => CatalogoRoles::catalogo(),
            'buscar'    => $buscar,
            'rol'       => $rol,
            'yo'        => (int) auth()->id(),
            'enumAlDia' => $enumAlDia,
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

        try {
            $cuenta->syncGroups($nuevo);
        } catch (Throwable $e) {
            return $volver->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo cambiar el rol.'));
        }

        log_message('notice', 'Roles: la cuenta {usuario} cambió el rol de la cuenta {id} de [{antes}] a {nuevo}.', [
            'usuario' => auth()->id(),
            'id'      => $idUser,
            'antes'   => implode(', ', $antes),
            'nuevo'   => $nuevo,
        ]);

        $mensaje = 'La cuenta ' . $this->nombre($cuenta) . ' ahora es: ' . CatalogoRoles::titulo($nuevo) . '.';

        if ($nuevo === 'repartidor') {
            try {
                (new Modelo_repartidor())->sincronizarCuentas($idUser);
            } catch (Throwable $e) {
                log_message('error', 'Roles: no se pudo crear la ficha de repartidor. ' . $e->getMessage());
            }
            $mensaje .= ' La próxima vez que inicie sesión se le pedirá completar sus datos de repartidor.';
        } elseif (in_array('repartidor', $antes, true)) {
            $mensaje .= ' Su ficha de repartidor se conserva (tiene historial de entregas).';
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
