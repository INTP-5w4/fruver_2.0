<?php
/**
 * Mensajes de la sesión dentro de un formulario de página completa (crea_* / modifica_*).
 * Antes esas páginas no mostraban nada si el servidor rechazaba los datos.
 */
?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="aviso aviso-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
<?php endif; ?>
<?php if (session()->getFlashdata('aviso')): ?>
    <div class="aviso aviso-atencion" role="status"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span><?= esc(session()->getFlashdata('aviso')) ?></span></div>
<?php endif; ?>
