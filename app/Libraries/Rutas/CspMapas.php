<?php

namespace App\Libraries\Rutas;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Abre la Política de Seguridad de Contenido (CSP) SOLO en las páginas con mapa.
 * El resto del sitio conserva su CSP.
 *
 *  - Leaflet (openrouteservice / local): la librería viene de cdn.jsdelivr.net (con
 *    verificación de integridad SRI) y las imágenes del mapa del servidor de capas
 *    (tile.openstreetmap.org por omisión). Las búsquedas van a nuestro propio servidor.
 *  - Google: dominios de la guía de Google
 *    https://developers.google.com/maps/documentation/javascript/content-security-policy
 */
final class CspMapas
{
    public const CDN = 'https://cdn.jsdelivr.net';

    private const DOMINIOS_GOOGLE = ['https://*.googleapis.com', 'https://*.gstatic.com', '*.google.com'];

    public static function permitir(ResponseInterface $response): void
    {
        if (! config('App')->CSPEnabled) {
            return;
        }

        $cfg = config('Rutas');
        $csp = $response->getCSP();

        if ($cfg->proveedorMapa() === 'leaflet') {
            $csp->addScriptSrc(self::CDN);
            $csp->addScriptSrcElem(self::CDN);
            $csp->addStyleSrc(self::CDN);
            $csp->addStyleSrcElem(self::CDN);
            // data: = bandera SVG del crédito de Leaflet; el CDN = imágenes de su hoja de estilos.
            $csp->addImageSrc([...self::origenesDeCapas($cfg->mapaTiles), self::CDN, 'data:']);

            return;
        }

        if ($cfg->proveedorMapa() !== 'google') {
            return;
        }

        $csp->addScriptSrc([...self::DOMINIOS_GOOGLE, 'unsafe-eval', 'blob:']);
        // El config define script-src-elem, que para <script> manda sobre script-src.
        $csp->addScriptSrcElem(self::DOMINIOS_GOOGLE);
        $csp->addStyleSrc('https://fonts.googleapis.com');
        $csp->addStyleSrcElem('https://fonts.googleapis.com');
        $csp->addImageSrc([...self::DOMINIOS_GOOGLE, '*.googleusercontent.com', 'data:', 'blob:']);
        $csp->addConnectSrc([...self::DOMINIOS_GOOGLE, 'data:', 'blob:']);
        $csp->addFontSrc('https://fonts.gstatic.com');
        $csp->addFrameSrc('*.google.com');
        $csp->addWorkerSrc('blob:');
    }

    /**
     * "https://{s}.tile.ejemplo.org/{z}/{x}/{y}.png" → ["https://*.tile.ejemplo.org"].
     * Solo https; cualquier otra cosa no abre nada.
     *
     * @return list<string>
     */
    public static function origenesDeCapas(string $plantilla): array
    {
        $host = parse_url(str_replace(['{s}', '{z}', '{x}', '{y}', '{r}'], ['a', '0', '0', '0', ''], $plantilla), PHP_URL_HOST);
        $esquema = parse_url($plantilla, PHP_URL_SCHEME);

        if (! is_string($host) || $esquema !== 'https' || preg_match('/^[a-z0-9.-]+$/i', $host) !== 1) {
            return [];
        }

        if (str_contains($plantilla, '{s}.')) {
            $host = '*.' . substr($host, 2); // "a.tile…" → "*.tile…"
        }

        return ['https://' . $host];
    }
}
