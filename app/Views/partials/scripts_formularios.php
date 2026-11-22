<?php
/**
 * Ayudas comunes de los formularios (van en el <head> de cada layout):
 *  - reglas_campo.js : muestra las reglas de formato del campo enfocado, una a la vez.
 *  - modales.js      : ventanas modales accesibles que no pierden lo capturado.
 *  - envio_unico.js  : un formulario se envía una sola vez (botón «Guardando…»).
 *  - aviso_modal.js  : abre en ventana modal cualquier enlace al aviso de privacidad.
 * Cada script carga su propia hoja de estilos si la necesita.
 *
 * Llevan ?v=<fecha de modificación> para que el navegador no use una versión vieja.
 */
foreach (['reglas_campo', 'modales', 'envio_unico', 'aviso_modal'] as $scriptFormulario) {
    $archivoFormulario = 'js/' . $scriptFormulario . '.js';
    $fechaFormulario   = @filemtime(FCPATH . $archivoFormulario);
    $urlFormulario     = base_url($archivoFormulario) . ($fechaFormulario ? '?v=' . $fechaFormulario : '');
    echo '<script src="' . htmlspecialchars($urlFormulario, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
}
