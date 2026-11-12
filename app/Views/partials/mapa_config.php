<?php
/**
 * Configuración del mapa para el navegador + cargador (public/js/mapas.js).
 * Inclúyelo en el <head> de cualquier vista con mapa:  <?= view('partials/mapa_config') ?>
 * El controlador de esa vista debe llamar a App\Libraries\Rutas\CspMapas::permitir().
 *
 * Proveedor de mapa:
 *  - leaflet : OpenStreetMap con Leaflet (openrouteservice o modo local). Sin llave en el navegador.
 *  - google  : Google Maps JavaScript API (llave de navegador).
 */
$cfgRutas    = config('Rutas');
$almacenMapa = $almacenMapa ?? null;
$mapaProv    = $cfgRutas->proveedorMapa();
$atribucion  = $cfgRutas->mapaAtribucion
    . ($cfgRutas->proveedorRutas() === 'ors'
        ? ' | Rutas &copy; <a href="https://openrouteservice.org/">openrouteservice.org</a> by HeiGIT'
        : '');
?>
<?php if ($mapaProv !== ''): ?>
    <meta name="fruver-mapas"
          data-proveedor="<?= esc($mapaProv, 'attr') ?>"
          data-lat="<?= esc((string) ($almacenMapa['lat'] ?? $cfgRutas->latCentro), 'attr') ?>"
          data-lng="<?= esc((string) ($almacenMapa['lng'] ?? $cfgRutas->lngCentro), 'attr') ?>"
          data-idioma="<?= esc($cfgRutas->idioma, 'attr') ?>"
          data-region="<?= esc($cfgRutas->region, 'attr') ?>"
          data-tecnico="<?= function_exists('es_tecnico') && es_tecnico() ? '1' : '0' ?>"
    <?php if ($mapaProv === 'google'): ?>
          data-llave="<?= esc($cfgRutas->llaveNavegador, 'attr') ?>"
          data-map-id="<?= esc($cfgRutas->mapId, 'attr') ?>"
    <?php else: ?>
          data-tiles="<?= esc($cfgRutas->mapaTiles, 'attr') ?>"
          data-atribucion="<?= esc($atribucion, 'attr') ?>"
          data-buscar="<?= $cfgRutas->proveedorRutas() === 'ors' ? esc(base_url('direcciones/buscar'), 'attr') : '' ?>"
    <?php endif; ?>
    >
    <?php if ($mapaProv === 'leaflet'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
    <?php endif; ?>
<?php endif; ?>
    <link rel="stylesheet" href="<?= base_url('estilos/rutas.css') ?>">
    <script src="<?= base_url('js/mapas.js') ?>"></script>
