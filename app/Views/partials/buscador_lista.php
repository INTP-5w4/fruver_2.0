<?php
/**
 * Barra de búsqueda de una lista del panel, con búsqueda mientras se escribe
 * (public/js/busqueda_viva.js: 180 ms después de la última tecla). Sin JavaScript
 * funciona igual con el botón "Buscar".
 * Los resultados que se reemplazan son el elemento con data-busqueda-resultados de la página.
 *
 * @var string $ruta        ruta de la lista (p. ej. 'lista_merma')
 * @var string $buscar      texto buscado
 * @var string $placeholder qué se puede buscar
 */
$buscar ??= '';
?>
<form method="get" action="<?= base_url($ruta) ?>" class="buscador-lista" role="search" data-busqueda-viva>
    <div>
        <input type="search" name="buscar" value="<?= esc($buscar, 'attr') ?>" placeholder="<?= esc($placeholder, 'attr') ?>"
               class="w3-input w3-border" aria-label="<?= esc($placeholder, 'attr') ?>" autocomplete="off" maxlength="100">
        <button type="submit" class="w3-button w3-green"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Buscar</button>
        <a href="<?= base_url($ruta) ?>" class="w3-button w3-border" data-busqueda-limpiar<?= $buscar === '' ? ' hidden' : '' ?>>✕ Limpiar</a>
        <span class="buscador-estado" data-busqueda-estado aria-live="polite"></span>
    </div>
</form>
