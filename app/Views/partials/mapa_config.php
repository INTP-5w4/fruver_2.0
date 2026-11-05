<?php
/**
 * Configuración de Google Maps para el navegador + cargador (public/js/mapas.js).
 * Inclúyelo en el <head> de cualquier vista con mapa:  <?= view('partials/mapa_config') ?>
 * El controlador de esa vista debe llamar a App\Libraries\Rutas\CspMapas::permitir().
 */
$cfgRutas = config('Rutas');
$almacenMapa = $almacenMapa ?? null;
?>
<?php if ($cfgRutas->conMapa()): ?>
    <meta name="fruver-mapas"
          data-llave="<?= esc($cfgRutas->llaveNavegador, 'attr') ?>"
          data-map-id="<?= esc($cfgRutas->mapId, 'attr') ?>"
          data-lat="<?= esc((string) ($almacenMapa['lat'] ?? $cfgRutas->latCentro), 'attr') ?>"
          data-lng="<?= esc((string) ($almacenMapa['lng'] ?? $cfgRutas->lngCentro), 'attr') ?>"
          data-idioma="<?= esc($cfgRutas->idioma, 'attr') ?>"
          data-region="<?= esc($cfgRutas->region, 'attr') ?>">
<?php endif; ?>
    <link rel="stylesheet" href="<?= base_url('estilos/rutas.css') ?>">
    <script src="<?= base_url('js/mapas.js') ?>"></script>
