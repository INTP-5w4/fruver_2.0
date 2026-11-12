<?php
/**
 * Hojas de estilo comunes del panel, el repartidor y el buzón de sugerencias.
 * Va al inicio del <head> de cada vista: así las hojas propias de cada pantalla,
 * que se enlazan después, pueden ajustar lo que haga falta.
 *
 * Orden: tipografías → W3.CSS (mientras se retira) → íconos → base.css → estructura → barra superior.
 *
 * Los archivos propios llevan ?v=<fecha de modificación>: al cambiar un archivo el navegador
 * descarga la versión nueva en lugar de usar la que tenía guardada.
 */
$conVersion = static function (string $archivo): string {
    $fecha = @filemtime(FCPATH . $archivo);

    return base_url($archivo) . ($fecha ? '?v=' . $fecha : '');
};
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= $conVersion('estilos/base.css') ?>">
<link rel="stylesheet" href="<?= $conVersion('estilos/estilosPaginas.css') ?>">
<link rel="stylesheet" href="<?= $conVersion('estilos/Header.css') ?>">
<script src="<?= $conVersion('js/panel.js') ?>" defer></script>
<script src="<?= $conVersion('js/busqueda_viva.js') ?>" defer></script>
<?= view('partials/scripts_formularios') ?>
