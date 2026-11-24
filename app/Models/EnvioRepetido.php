<?php

namespace App\Models;

use DomainException;

/**
 * El formulario ya se había procesado (mismo token_envio): no se creó un registro nuevo.
 * Lo lanza Modelo_envio_unico::unaVez() y lo atienden los controladores que crean pedidos.
 */
final class EnvioRepetido extends DomainException
{
    public function __construct(public readonly string $recurso, public readonly ?int $idRecurso)
    {
        parent::__construct(
            $idRecurso !== null && $idRecurso > 0
                ? "Este {$recurso} ya se había guardado (#{$idRecurso}); no se creó otro."
                : "Este {$recurso} ya se había enviado; no se creó otro."
        );
    }
}
