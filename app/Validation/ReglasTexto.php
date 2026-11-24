<?php

namespace App\Validation;

use App\Libraries\FiltroGroserias;

/**
 * Reglas de validación propias para textos. Se registran en Config\Validation::$ruleSets.
 */
class ReglasTexto
{
    /**
     * sin_groserias: el texto no trae palabras de la lista de Config\Groserias.
     * Vacío pasa (para eso están required / permit_empty). El mensaje está en
     * app/Language/es/Validation.php y no repite la palabra encontrada.
     */
    public function sin_groserias($valor): bool
    {
        if (! is_string($valor) || $valor === '') {
            return true;
        }

        return ! FiltroGroserias::crear()->contiene($valor);
    }

    /**
     * usuario_sin_groserias: para nombres de usuario, donde las palabras van pegadas
     * ("elputo99"): además de las palabras completas, busca la grosería dentro del texto.
     */
    public function usuario_sin_groserias($valor): bool
    {
        if (! is_string($valor) || $valor === '') {
            return true;
        }

        $filtro = FiltroGroserias::crear();

        return ! $filtro->contiene($valor) && $filtro->buscarDentro($valor) === null;
    }
}
