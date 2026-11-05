<?php

namespace App\Libraries\Rutas;

use Config\Services;
use Throwable;

/** Transporte real con el cliente cURL de CodeIgniter. */
final class TransporteCurl implements TransporteHttp
{
    public function postJson(string $url, array $encabezados, array $cuerpo, int $tiempoEsperaSeg): array
    {
        return $this->enviar('POST', $url, [
            'headers' => $encabezados + ['Content-Type' => 'application/json'],
            'body'    => json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ], $tiempoEsperaSeg);
    }

    public function get(string $url, array $parametros, int $tiempoEsperaSeg, array $encabezados = []): array
    {
        return $this->enviar('GET', $url, ['query' => $parametros, 'headers' => $encabezados], $tiempoEsperaSeg);
    }

    private function enviar(string $metodo, string $url, array $opciones, int $tiempoEsperaSeg): array
    {
        // Instancia NO compartida: el servicio compartido arrastra encabezados entre peticiones.
        $cliente = Services::curlrequest([
            'timeout'         => $tiempoEsperaSeg,
            'connect_timeout' => 5,
            'http_errors'     => false,
        ], null, null, false);

        try {
            $respuesta = $cliente->request($metodo, $url, $opciones);
        } catch (Throwable $e) {
            throw new ErrorProveedorRutas('No se pudo conectar con el servicio de mapas: ' . $e->getMessage(), 0, $e);
        }

        return ['status' => $respuesta->getStatusCode(), 'cuerpo' => (string) $respuesta->getBody()];
    }
}
