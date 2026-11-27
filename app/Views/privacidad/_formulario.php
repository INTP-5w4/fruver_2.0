<?php
/**
 * Formato de consentimiento (sección 9 del Aviso de Consentimiento). Solo los campos:
 * el <form>, el token CSRF y el botón los pone la vista que lo incluye.
 *
 * @var \Config\Privacidad      $cfg
 * @var array<string,bool|string> $valores        ver FormularioConsentimiento::valoresFormulario()
 * @var array<string,string>    $errores
 * @var bool                    $conDeclaracion  ¿se piden "leí el aviso" y "soy mayor de edad"?
 * @var string|null             $correo          correo de la cuenta (si ya existe)
 * @var bool                    $compacto        solo una casilla "Acepto todo" con enlaces; el detalle se abre con "Ver y editar mis opciones"
 */
$errores ??= [];
$correo  ??= null;
$compacto ??= false;
$error   = static fn (string $campo): string => isset($errores[$campo])
    ? '<p class="pv-error" id="err-' . esc($campo, 'attr') . '">' . esc($errores[$campo]) . '</p>'
    : '';
$marca = static fn (string $campo): string => ! empty($valores[$campo]) ? ' checked' : '';

// Enlace al texto completo (aviso de consentimiento) en la ventana modal de aviso_modal.js.
$enlace = static fn (string $ancla, string $texto): string =>
    '<a href="' . site_url('aviso-de-privacidad/consentimiento') . '#' . esc($ancla, 'attr') . '" target="_blank" rel="noopener">'
    . esc($texto) . '</a>';

// De la frase "Autorizo que me envíen promociones…" convierte en enlace la parte que nombra la
// finalidad (su título); si no aparece en la frase, agrega un enlace "Leer el detalle" al final.
$autorizoConEnlace = static function (array $f, string $clave) use ($enlace): string {
    $frase = (string) $f['autorizo'];
    $pos   = mb_stripos($frase, (string) $f['titulo']);
    if ($pos === false) {
        return esc($frase) . ' ' . $enlace('finalidad-' . $clave, 'Leer el detalle');
    }
    $largo = mb_strlen((string) $f['titulo']);

    return esc(mb_substr($frase, 0, $pos))
        . $enlace('finalidad-' . $clave, mb_substr($frase, $pos, $largo))
        . esc(mb_substr($frase, $pos + $largo));
};
?>
<?php if ($compacto): ?>
<div class="pv-compacto" data-pv-compacto>
    <label class="pv-check pv-total">
        <input type="checkbox" data-pv-total>
        <span>
            Acepto el <a href="<?= site_url('aviso-de-privacidad') ?>" target="_blank" rel="noopener">Aviso de Privacidad</a>,
            declaro que soy mayor de edad y autorizo todas las
            <?= $enlace('finalidades-voluntarias', 'finalidades voluntarias') ?> y los
            <?= $enlace('canales-preferidos', 'canales preferidos') ?>.
        </span>
    </label>
    <p class="pv-nota">Las finalidades voluntarias son opcionales: abre tus opciones para quitar las que no quieras o cambiar tu teléfono.</p>

    <details class="pv-personalizar"<?= $errores !== [] ? ' open' : '' ?>>
        <summary>Ver y editar mis opciones</summary>
<?php endif; ?>
<?php if ($conDeclaracion): ?>
    <fieldset class="pv-bloque">
        <legend>Declaración (obligatoria para continuar)</legend>

        <label class="pv-check">
            <input type="checkbox" name="aviso_leido" value="1" required<?= $marca('aviso_leido') ?>
                   <?= isset($errores['aviso_leido']) ? 'aria-describedby="err-aviso_leido"' : '' ?>>
            <span>He leído y comprendido el
                <a href="<?= site_url('aviso-de-privacidad') ?>" target="_blank" rel="noopener">Aviso de Privacidad</a>
                de FRUVER 2.0 y sé que mis datos serán tratados para las finalidades necesarias.</span>
        </label>
        <?= $error('aviso_leido') ?>

        <label class="pv-check">
            <input type="checkbox" name="mayor_edad" value="1" required<?= $marca('mayor_edad') ?>
                   <?= isset($errores['mayor_edad']) ? 'aria-describedby="err-mayor_edad"' : '' ?>>
            <span>Declaro que soy mayor de edad, que los datos que proporciono son verdaderos y que puedo
                <?= $enlace('derechos', 'modificar o revocar mi consentimiento') ?> en cualquier momento.</span>
        </label>
        <?= $error('mayor_edad') ?>
    </fieldset>
<?php endif; ?>

<fieldset class="pv-bloque" data-pv-grupo="finalidades">
    <legend>Finalidades voluntarias <span class="pv-opcional">(opcional; marca solo las que aceptes)</span></legend>
    <p class="pv-nota">
        No son necesarias para usar FRUVER. Si no marcas ninguna, tus pedidos y tu cuenta funcionan igual;
        solo no recibirás promociones, recordatorios, encuestas ni recomendaciones personalizadas.
    </p>

    <label class="pv-check pv-todos">
        <input type="checkbox" data-pv-todos="finalidades">
        <span>Acepto todas las <?= $enlace('finalidades-voluntarias', 'finalidades voluntarias') ?> (V1 a V4).</span>
    </label>

    <?php foreach ($cfg->finalidades as $clave => $f): ?>
        <label class="pv-check">
            <input type="checkbox" name="<?= esc($clave, 'attr') ?>" value="1" data-pv-item="finalidades"<?= $marca($clave) ?>>
            <span>
                <strong><?= esc($f['codigo']) ?>.</strong> <?= $autorizoConEnlace($f, $clave) ?>
                <small>Datos: <?= esc($f['datos']) ?> · Medio: <?= esc($f['medio']) ?></small>
            </span>
        </label>
    <?php endforeach; ?>
</fieldset>

<fieldset class="pv-bloque" data-pv-grupo="canales">
    <legend>Canales preferidos <span class="pv-opcional">(solo si aceptaste V1, V2 o V3)</span></legend>

    <label class="pv-check pv-todos">
        <input type="checkbox" data-pv-todos="canales">
        <span>Acepto todos los <?= $enlace('canales-preferidos', 'canales preferidos') ?>.</span>
    </label>

    <div class="pv-canales">
        <?php foreach ($cfg->canales as $clave => $nombre): ?>
            <label class="pv-check pv-check-linea">
                <input type="checkbox" name="<?= esc($clave, 'attr') ?>" value="1" data-pv-item="canales"<?= $marca($clave) ?>>
                <span><?= $enlace($clave, $nombre) ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <?= $error('canales') ?>

    <label class="pv-campo" for="tel_contacto">
        <span>Teléfono para llamadas o WhatsApp <span class="pv-opcional">(solo si elegiste esos canales)</span></span>
        <input type="tel" id="tel_contacto" name="tel_contacto" maxlength="14" inputmode="numeric" autocomplete="tel-national"
               value="<?= esc((string) ($valores['tel_contacto'] ?? ''), 'attr') ?>" placeholder="10 dígitos"
               <?= isset($errores['tel_contacto']) ? 'aria-describedby="err-tel_contacto"' : '' ?>>
    </label>
    <?= $error('tel_contacto') ?>

    <p class="pv-nota">
        <?php if ($correo !== null && $correo !== ''): ?>
            Por correo electrónico usaremos el de tu cuenta (<strong><?= esc($correo) ?></strong>).
        <?php else: ?>
            Por correo electrónico usaremos el de tu cuenta.
        <?php endif; ?>
        Si no eliges ninguna finalidad voluntaria, no guardamos canales ni teléfono de contacto.
    </p>
</fieldset>

<?php if ($compacto): ?>
    </details>
</div>
<?php endif; ?>

<script src="<?= base_url('js/privacidad_form.js') ?>" defer></script>

