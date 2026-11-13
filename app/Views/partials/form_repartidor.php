<?php
/**
 * Campos del formulario de repartidor (van DENTRO del <form>).
 * Lo usan crea_repartidor, modifica_repartidor y los dos modales de lista_repartidor.
 *
 * @var array|null $rep    fila de `repartidor` (null = alta)
 * @var string     $sufijo para ids únicos cuando hay varios formularios en la página
 * @var bool       $conOld ¿rellenar con lo que se envió antes (error de validación)?
 *
 * Reglas: las mismas que App\Controllers\Repartidores (y el catálogo de public/js/reglas_campo.js).
 */
use App\Libraries\Rutas\Combustible;
use App\Models\Modelo_repartidor;

$rep    ??= null;
$sufijo ??= '';
$conOld ??= true;

$valor = static function (string $campo, string $columna) use ($rep, $conOld): string {
    $enviado = $conOld ? old($campo, null, false) : null;

    return (string) ($enviado ?? ($rep[$columna] ?? ''));
};
$id       = static fn (string $campo): string => esc($campo . $sufijo, 'attr');
$vehiculo    = $valor('vehiculo', 'vehiculo') ?: 'moto';
$combustible = $valor('combustible', 'combustible');
$rendimiento = $valor('rendimiento', 'rendimiento_km_l');
$rendimiento = is_numeric($rendimiento) ? rtrim(rtrim(number_format((float) $rendimiento, 2, '.', ''), '0'), '.') : $rendimiento;
$tipicos     = Combustible::RENDIMIENTO_TIPICO;
?>
<label for="<?= $id('nom') ?>"><b>Nombre*</b></label>
<input type="text" name="nom" id="<?= $id('nom') ?>" class="modal-input" maxlength="100" required
       autocomplete="off" value="<?= esc($valor('nom', 'nombre'), 'attr') ?>">

<label for="<?= $id('ape_pat') ?>"><b>Apellido paterno*</b></label>
<input type="text" name="ape_pat" id="<?= $id('ape_pat') ?>" class="modal-input" maxlength="100" required
       autocomplete="off" value="<?= esc($valor('ape_pat', 'ape_pat'), 'attr') ?>">

<label for="<?= $id('ape_mat') ?>"><b>Apellido materno*</b></label>
<input type="text" name="ape_mat" id="<?= $id('ape_mat') ?>" class="modal-input" maxlength="100" required
       autocomplete="off" value="<?= esc($valor('ape_mat', 'ape_mat'), 'attr') ?>">

<label for="<?= $id('tel') ?>"><b>Teléfono*</b></label>
<input type="tel" name="tel" id="<?= $id('tel') ?>" class="modal-input" maxlength="12" inputmode="numeric" required
       pattern="[0-9]*" data-regla-patron="Solo números" placeholder="10 dígitos, ej: 2221234567"
       autocomplete="off" value="<?= esc($valor('tel', 'telefono'), 'attr') ?>">

<label for="<?= $id('dir') ?>"><b>Dirección*</b></label>
<input type="text" name="dir" id="<?= $id('dir') ?>" class="modal-input" maxlength="500" required
       placeholder="Calle, número, colonia y municipio" autocomplete="off" value="<?= esc($valor('dir', 'direccion'), 'attr') ?>">

<label for="<?= $id('not') ?>"><b>Notas</b> <span class="opcional">(opcional)</span></label>
<textarea name="not" id="<?= $id('not') ?>" class="modal-input" rows="3" maxlength="500"><?= esc($valor('not', 'notas')) ?></textarea>

<label for="<?= $id('vehiculo') ?>"><b>Vehículo</b> <span class="opcional">(para calcular la ruta)</span></label>
<select name="vehiculo" id="<?= $id('vehiculo') ?>" class="modal-input" required>
    <?php foreach (Modelo_repartidor::VEHICULOS as $clave => $texto): ?>
        <option value="<?= esc($clave, 'attr') ?>"<?= $vehiculo === $clave ? ' selected' : '' ?>><?= esc($texto) ?></option>
    <?php endforeach; ?>
</select>

<label for="<?= $id('combustible') ?>"><b>Combustible</b> <span class="opcional">(para el costo de traslado; no aplica en bicicleta)</span></label>
<select name="combustible" id="<?= $id('combustible') ?>" class="modal-input">
    <option value="">— Elegir —</option>
    <?php foreach (Combustible::TIPOS as $clave => $texto): ?>
        <option value="<?= esc($clave, 'attr') ?>"<?= $combustible === $clave ? ' selected' : '' ?>><?= esc($texto) ?></option>
    <?php endforeach; ?>
</select>

<label for="<?= $id('rendimiento') ?>"><b>Rendimiento</b> <span class="opcional">(kilómetros por litro)</span></label>
<input type="number" name="rendimiento" id="<?= $id('rendimiento') ?>" class="modal-input" min="1" max="150" step="0.1"
       inputmode="decimal" placeholder="Ej.: moto <?= (int) $tipicos['moto'] ?>, auto <?= (int) $tipicos['auto'] ?>, camioneta <?= (int) $tipicos['camioneta'] ?>"
       data-reglas="<?= esc(json_encode(['Cuántos km recorre con 1 litro', 'Si lo dejas vacío se usa uno típico del vehículo'], JSON_UNESCAPED_UNICODE), 'attr') ?>"
       value="<?= esc($rendimiento, 'attr') ?>">
