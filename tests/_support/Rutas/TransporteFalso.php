<?php

namespace Tests\Support\Rutas;

use App\Libraries\Rutas\TransporteHttp;

/**
 * Transporte falso: guarda lo que se mandó y responde lo que se le indique.
 */
final class TransporteFalso implements TransporteHttp
{
    public array $enviados = [];

    /** @param list<array{status:int,cuerpo:string}> $respuestas */
    public function __construct(private array $respuestas)
    {
    }

    public function postJson(string $url, array $encabezados, array $cuerpo, int $tiempoEsperaSeg): array
    {
        $this->enviados[] = compact('url', 'encabezados', 'cuerpo');

        return array_shift($this->respuestas) ?? ['status' => 500, 'cuerpo' => '{}'];
    }

    public function get(string $url, array $parametros, int $tiempoEsperaSeg, array $encabezados = []): array
    {
        $this->enviados[] = compact('url', 'parametros', 'encabezados');

        return array_shift($this->respuestas) ?? ['status' => 500, 'cuerpo' => '{}'];
    }
}
