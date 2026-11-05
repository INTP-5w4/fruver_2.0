<?php
/**
 * Buscador + mapa con pin arrastrable para ubicar una dirección.
 * Va DENTRO del <form>. Variables opcionales:
 *   $geo   fila de `direccion` / `almacen` con lat y lng (para mostrar dónde está)
 *   $geoId sufijo para ids únicos cuando hay varios formularios en la página
 */
$geo   = $geo ?? null;
$geoId = $geoId ?? 'geo';
?>
<div class="geo-caja" data-geo id="<?= esc($geoId, 'attr') ?>"
     data-lat="<?= esc((string) ($geo['lat'] ?? ''), 'attr') ?>"
     data-lng="<?= esc((string) ($geo['lng'] ?? ''), 'attr') ?>">
    <label><b>Ubicación en el mapa</b></label>
    <div class="geo-buscador" data-geo-buscador></div>
    <div class="geo-mapa" data-geo-mapa></div>
    <p class="geo-ayuda">Busca la dirección o toca el mapa / arrastra el pin hasta la entrada exacta.</p>
    <p class="geo-estado" data-mapa-estado hidden></p>
    <input type="hidden" name="geo_lat">
    <input type="hidden" name="geo_lng">
    <input type="hidden" name="geo_place_id">
    <input type="hidden" name="geo_formateada">
    <input type="hidden" name="geo_origen">
</div>
