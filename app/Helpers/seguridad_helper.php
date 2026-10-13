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
