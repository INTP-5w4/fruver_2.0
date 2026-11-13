<?php

namespace App\Filters;

use App\Models\Modelo_repartidor;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Un repartidor con la ficha incompleta debe completarla antes de usar sus pantallas.
 *
 * Caso típico: alguien se registra como cliente (solo usuario, correo y contraseña) y
 * después el superadmin le da el rol de repartidor. Su ficha se crea sola, pero vacía
 * (sin apellidos, teléfono ni dirección). La primera vez que entra con ese rol se le pide
 * completarla en repartidor/mis-datos; mientras falten datos, cualquier pantalla del
 * repartidor lo regresa ahí.
 *
 * Se aplica al grupo de rutas "repartidor" (Config\Routes), salvo a mis-datos. Cuando la
 * ficha está completa se recuerda en la sesión para no consultar la base en cada petición.
 */
class RepartidorDatosGuard implements FilterInterface
{
    public const LLAVE_SESION = 'repartidor_datos_ok';

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest || ! auth()->loggedIn()) {
            return null;
        }

        $idUser = (int) auth()->id();
        if ((int) session(self::LLAVE_SESION) === $idUser) {
            return null;
        }

        try {
            $modelo = new Modelo_repartidor();
            $modelo->sincronizarCuentas($idUser); // por si su ficha aún no existe
            $ficha = $modelo->deUsuario($idUser);
        } catch (Throwable $e) {
            log_message('error', 'RepartidorDatosGuard: ' . $e->getMessage());

            return null; // un error de base no debe dejar al repartidor sin sus entregas
        }

        if ($ficha === null || ! Modelo_repartidor::faltanDatos($ficha)) {
            session()->set(self::LLAVE_SESION, $idUser);

            return null;
        }

        $mensaje = 'Antes de empezar, completa tus datos de repartidor.';

        if ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(403)
                ->setJSON(['ok' => false, 'mensaje' => $mensaje, 'redirigir' => site_url('repartidor/mis-datos')]);
        }

        return redirect()->to(site_url('repartidor/mis-datos'))->with('aviso', $mensaje);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
