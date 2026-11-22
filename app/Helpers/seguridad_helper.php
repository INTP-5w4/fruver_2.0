<?php

/**
 * Ayudas de seguridad para las vistas. Se cargan con helper('seguridad')
 * (BaseController ya lo hace para todos los controladores).
 */

if (! function_exists('json_seguro')) {
    /**
     * json_encode listo para incrustar dentro de un <script> o de un atributo HTML.
     *
     * Las banderas HEX_* convierten < > & ' " en secuencias \uXXXX, de modo que un
     * nombre como  </script><script>alert(1)</script>  no puede cerrar el script ni
     * romper el atributo (anti XSS).
     */
    function json_seguro(mixed $valor): string
    {
        return json_encode(
            $valor,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        ) ?: 'null';
    }
}

if (! function_exists('campo_envio_unico')) {
    /**
     * Campo oculto token_envio para los formularios que CREAN registros (pedidos).
     * Un token nuevo por cada vez que se muestra el formulario: si el navegador reenvía
     * el mismo formulario (corte de red, doble clic), el servidor lo reconoce y no
     * duplica el registro (App\Models\Modelo_envio_unico).
     */
    function campo_envio_unico(): string
    {
        return '<input type="hidden" name="token_envio" value="'
            . \App\Models\Modelo_envio_unico::nuevoToken() . '">';
    }
}
