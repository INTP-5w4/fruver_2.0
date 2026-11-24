<?php

namespace App\Libraries\Rutas;

/**
 * Mínimo necesario para hablar con Google. Existe para poder probar los
 * optimizadores sin red (las pruebas inyectan un transporte falso).
 */
interface TransporteHttp
{
    /**
     * @param array<string,string> $encabezados
     * @return array{status:int,cuerpo:string}
     */
    public function postJson(string $url, array $encabezados, array $cuerpo, int $tiempoEsperaSeg): array;

    /**
     * @param array<string,string> $parametros
     * @param array<string,string> $encabezados
     * @return array{status:int,cuerpo:string}
     */
    public function get(string $url, array $parametros, int $tiempoEsperaSeg, array $encabezados = []): array;
}
