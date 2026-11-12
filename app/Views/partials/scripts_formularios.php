<?php
/**
 * Ayudas comunes de los formularios (van en el <head> de cada layout):
 *  - reglas_campo.js : muestra las reglas de formato del campo enfocado, una a la vez.
 *  - aviso_modal.js  : abre en ventana modal cualquier enlace al aviso de privacidad.
 * Cada script carga su propia hoja de estilos.
 */
?>
<script src="<?= base_url('js/reglas_campo.js') ?>" defer></script>
<script src="<?= base_url('js/aviso_modal.js') ?>" defer></script>
