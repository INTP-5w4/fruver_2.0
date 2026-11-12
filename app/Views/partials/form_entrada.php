<?php
/**
 * Campos del formulario de entrada de mercancía (van DENTRO del <form>).
 * Lo usan crea_entrada y modifica_entrada (páginas completas).
 *
 * @var array|null               $ent       fila de `entrada` (null = alta)
 * @var list<array<string,mixed>> $productos
 *
 * Reglas: las mismas que App\Controllers\Entradas::reglas() (y public/js/reglas_campo.js).
 */
$ent ??= null;

$valor = static function (string $campo, string $columna) use ($ent): string {
    return (string) (old($campo, null, false) ?? ($ent[$columna] ?? ''));
};
$unidadesCompra = ['Caja', 'Arpilla', 'Bulto', 'Tonelada', 'Mazo'];
$unidadesVenta  = ['Kilogramo', 'Litro', 'Caja', 'Pieza', 'Domo', 'Ramo'];
$uCompra  = $valor('u_com', 'u_compra') ?: 'Caja';
$uVenta   = $valor('u_ven', 'u_venta') ?: 'Kilogramo';
$producto = $valor('id_producto', 'id_producto');
?>
<label for="f_ent"><b>Fecha de entrada*</b></label>
<input type="date" name="f_ent" id="f_ent" class="modal-input" required value="<?= esc($valor('f_ent', 'fecha') ?: date('Y-m-d'), 'attr') ?>">

<label for="f_cad"><b>Fecha de caducidad</b> <span class="opcional">(opcional)</span></label>
<input type="date" name="f_cad" id="f_cad" class="modal-input" value="<?= esc($valor('f_cad', 'fecha_cad'), 'attr') ?>">

<label for="id_producto"><b>Producto*</b></label>
<select name="id_producto" id="id_producto" class="modal-input" required>
    <option value="" disabled<?= $producto === '' ? ' selected' : '' ?>>Elige un producto</option>
    <?php foreach ($productos as $p): ?>
        <option value="<?= (int) $p['id'] ?>"<?= $producto === (string) $p['id'] ? ' selected' : '' ?>><?= esc($p['nombre']) ?></option>
    <?php endforeach; ?>
</select>

<label for="cant"><b>Cantidad*</b> <span class="opcional">(en unidades de compra)</span></label>
<input type="number" name="cant" id="cant" class="modal-input" required min="0.01" step="0.01" inputmode="decimal"
       placeholder="Ej: 50" value="<?= esc($valor('cant', 'cantidad'), 'attr') ?>">

<label for="u_com"><b>Unidad de compra*</b></label>
<select name="u_com" id="u_com" class="modal-input" required>
    <?php foreach ($unidadesCompra as $u): ?>
        <option value="<?= $u ?>"<?= $uCompra === $u ? ' selected' : '' ?>><?= $u ?></option>
    <?php endforeach; ?>
</select>

<label for="u_ven"><b>Unidad de venta*</b></label>
<select name="u_ven" id="u_ven" class="modal-input" required>
    <?php foreach ($unidadesVenta as $u): ?>
        <option value="<?= $u ?>"<?= $uVenta === $u ? ' selected' : '' ?>><?= $u ?></option>
    <?php endforeach; ?>
</select>

<label for="equi"><b>Equivalente*</b> <span class="opcional">(unidades de venta que trae cada unidad de compra)</span></label>
<input type="number" name="equi" id="equi" class="modal-input" required min="0.01" step="0.01" inputmode="decimal"
       placeholder="Ej: 20 (una caja trae 20 kg)" value="<?= esc($valor('equi', 'equivalente'), 'attr') ?>">

<label for="p_compra"><b>Precio de compra*</b> <span class="opcional">(por unidad de compra)</span></label>
<input type="number" name="p_compra" id="p_compra" class="modal-input" required min="0" step="0.01" inputmode="decimal"
       placeholder="Ej: 450.00" value="<?= esc($valor('p_compra', 'precio_compra_u'), 'attr') ?>">

<label for="p_venta"><b>Precio de venta</b> <span class="opcional">(por unidad de venta; opcional)</span></label>
<input type="number" name="p_venta" id="p_venta" class="modal-input" min="0" step="0.01" inputmode="decimal"
       placeholder="Ej: 35.00" value="<?= esc($valor('p_venta', 'precio_venta_u'), 'attr') ?>">
