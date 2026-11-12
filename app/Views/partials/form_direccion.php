<?php
/**
 * Campos del formulario de dirección del panel (van DENTRO del <form>).
 * Lo usan crea_direccion, modifica_direccion y los dos modales de lista_direccion,
 * para que los cuatro tengan los mismos campos, reglas y valores de regreso.
 *
 * @var array|null               $dir      fila de `direccion` con los valores actuales (null = alta)
 * @var list<array<string,mixed>> $clientes
 * @var bool                     $conGeo   CP, referencias y mapa (requieren la migración de rutas)
 * @var string                   $sufijo   para ids únicos cuando hay varios formularios en la página
 * @var bool                     $conOld   ¿rellenar con lo que se envió antes (error de validación)?
 *
 * Reglas: las mismas que App\Controllers\Direcciones::$reglas (y el catálogo de public/js/reglas_campo.js).
 */
use App\Models\Modelo_direccion;

$dir    ??= null;
$conGeo ??= false;
$sufijo ??= '';
$conOld ??= true;

// Valor del campo: lo enviado (si hubo error) o el de la fila.
$valor = static function (string $campo, string $columna) use ($dir, $conOld): string {
    $enviado = $conOld ? old($campo, null, false) : null;

    return (string) ($enviado ?? ($dir[$columna] ?? ''));
};
$id = static fn (string $campo): string => esc($campo . $sufijo, 'attr');

$estadoActual  = $valor('edo', 'estado');
$clienteActual = $valor('id_cliente', 'id_cliente');
$estados       = Modelo_direccion::ESTADOS_MX;
// Una dirección antigua con un estado escrito distinto (p. ej. con acento) no se pierde al editar.
if ($estadoActual !== '' && ! in_array($estadoActual, $estados, true)) {
    $estados[] = $estadoActual;
}
?>
<label for="<?= $id('col') ?>"><b>Colonia*</b></label>
<input type="text" name="col" id="<?= $id('col') ?>" class="modal-input" maxlength="200" required
       placeholder="Ej: Centro" autocomplete="address-level3" value="<?= esc($valor('col', 'colonia'), 'attr') ?>">

<label for="<?= $id('calle') ?>"><b>Calle*</b></label>
<input type="text" name="calle" id="<?= $id('calle') ?>" class="modal-input" maxlength="200" required
       placeholder="Ej: Benito Juárez" autocomplete="address-line1" value="<?= esc($valor('calle', 'calle'), 'attr') ?>">

<label for="<?= $id('num') ?>"><b>Número*</b></label>
<input type="text" name="num" id="<?= $id('num') ?>" class="modal-input" maxlength="50" required
       placeholder="Ej: 59 o 12-B, int. 3" value="<?= esc($valor('num', 'numero'), 'attr') ?>">

<label for="<?= $id('mun') ?>"><b>Municipio*</b></label>
<input type="text" name="mun" id="<?= $id('mun') ?>" class="modal-input" maxlength="200" required
       placeholder="Ej: Rafael Lara Grajales" autocomplete="address-level2" value="<?= esc($valor('mun', 'municipio'), 'attr') ?>">

<label for="<?= $id('edo') ?>"><b>Estado*</b></label>
<select name="edo" id="<?= $id('edo') ?>" class="modal-input" required>
    <option value="" disabled<?= $estadoActual === '' ? ' selected' : '' ?>>Elige un estado</option>
    <?php foreach ($estados as $e): ?>
        <option value="<?= esc($e, 'attr') ?>"<?= $estadoActual === $e ? ' selected' : '' ?>><?= esc($e) ?></option>
    <?php endforeach; ?>
</select>

<?php if ($conGeo): ?>
    <label for="<?= $id('cp') ?>"><b>Código postal</b></label>
    <input type="text" name="cp" id="<?= $id('cp') ?>" class="modal-input" maxlength="10" inputmode="numeric"
           autocomplete="postal-code" placeholder="Ej: 72000" value="<?= esc($valor('cp', 'codigo_postal'), 'attr') ?>">

    <label for="<?= $id('referencias') ?>"><b>Referencias para el repartidor</b></label>
    <input type="text" name="referencias" id="<?= $id('referencias') ?>" class="modal-input" maxlength="300"
           placeholder="Entre calles, color de fachada, portón…" value="<?= esc($valor('referencias', 'referencias'), 'attr') ?>">

    <label class="geo-check">
        <input type="checkbox" name="principal" value="1" id="<?= $id('principal') ?>"
            <?= ($conOld && session()->has('_ci_old_input') ? old('principal') === '1' : ! empty($dir['es_principal'])) ? 'checked' : '' ?>>
        Usar como dirección principal del cliente
    </label>

    <?= view('partials/geo_direccion', ['geo' => $dir, 'geoId' => 'geo' . $sufijo]) ?>
<?php endif; ?>

<label for="<?= $id('id_cliente') ?>"><b>Cliente*</b></label>
<select name="id_cliente" id="<?= $id('id_cliente') ?>" class="modal-input" required>
    <option value="" disabled<?= $clienteActual === '' ? ' selected' : '' ?>>Elige un cliente</option>
    <?php foreach ($clientes as $c): ?>
        <option value="<?= (int) $c['id'] ?>"<?= $clienteActual === (string) $c['id'] ? ' selected' : '' ?>>
            <?= esc(trim($c['nombre'] . ' ' . $c['ape_pat'] . ' ' . ($c['ape_mat'] ?? ''))) ?>
        </option>
    <?php endforeach; ?>
</select>
