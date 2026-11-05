<?php
/**
 * Código postal, referencias y "principal" de una dirección (requieren la migración de rutas).
 * Variable opcional $dir con la fila actual; $idSufijo para ids únicos.
 */
$dir      = $dir ?? null;
$idSufijo = $idSufijo ?? '';
?>
<label><b>Código postal</b></label>
<input type="text" name="cp" id="cp<?= esc($idSufijo, 'attr') ?>" class="modal-input" maxlength="10" inputmode="numeric"
       value="<?= esc(old('cp', $dir['codigo_postal'] ?? ''), 'attr') ?>" placeholder="Ej: 72000">

<label><b>Referencias para el repartidor</b></label>
<input type="text" name="referencias" id="referencias<?= esc($idSufijo, 'attr') ?>" class="modal-input" maxlength="300"
       value="<?= esc(old('referencias', $dir['referencias'] ?? ''), 'attr') ?>"
       placeholder="Entre calles, color de fachada, portón…">

<label class="geo-check">
    <input type="checkbox" name="principal" value="1" id="principal<?= esc($idSufijo, 'attr') ?>" <?= ! empty($dir['es_principal']) ? 'checked' : '' ?>>
    Usar como dirección principal del cliente
</label>
