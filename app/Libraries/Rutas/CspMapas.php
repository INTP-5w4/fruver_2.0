<?php

namespace App\Libraries\Rutas;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Abre la Política de Seguridad de Contenido (CSP) SOLO en las páginas con mapa,
 * para que cargue Google Maps JavaScript API. El resto del sitio conserva su CSP.
 *
 * Basado en la guía de Google:
 * https://developers.google.com/maps/documentation/javascript/content-security-policy
 * Si Google agrega dominios nuevos, la consola del navegador mostrará la violación
 * de CSP y bastará con añadirlo aquí.
 */
final class CspMapas
{
    private const DOMINIOS = ['https://*.googleapis.com', 'https://*.gstatic.com', '*.google.com'];

    public static function permitir(ResponseInterface $response): void
    {
        if (! config('App')->CSPEnabled) {
            return;
        }

        $csp = $response->getCSP();

        $csp->addScriptSrc([...self::DOMINIOS, 'unsafe-eval', 'blob:']);
        // El config define script-src-elem, que para <script> manda sobre script-src.
        $csp->addScriptSrcElem(self::DOMINIOS);
        $csp->addStyleSrc('https://fonts.googleapis.com');
        $csp->addStyleSrcElem('https://fonts.googleapis.com');
        $csp->addImageSrc([...self::DOMINIOS, '*.googleusercontent.com', 'data:', 'blob:']);
        $csp->addConnectSrc([...self::DOMINIOS, 'data:', 'blob:']);
        $csp->addFontSrc('https://fonts.gstatic.com');
        $csp->addFrameSrc('*.google.com');
        $csp->addWorkerSrc('blob:');
    }
}
