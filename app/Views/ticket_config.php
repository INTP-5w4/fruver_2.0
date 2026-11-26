<?php
helper('security');

use App\Libraries\Termica\AjustesImpresora;

/** @var \Config\Impresora $cfg */
/** @var bool $navegador */
/** @var bool $tecnico */

// Estándar al que vuelve «Restaurar valores por defecto» y con el que se precargan
// los campos de conexión que, de fábrica, irían vacíos.
$def = AjustesImpresora::predeterminados();

// Prefill: valor reenviado tras un error (old) o el valor efectivo actual del config.
$t = static fn (string $k, $def): string => esc((string) old($k, $def), 'attr');
$sel = static fn (string $k, $def, $opt): string => (string) old($k, $def) === (string) $opt ? ' selected' : '';
$chk = static fn (bool $v): string => $v ? ' checked' : '';

// Valor efectivo del config o, si está vacío, el sugerido estándar (campos de conexión).
$pref = static fn (string $k, $cfgVal) => ($cfgVal === '' || $cfgVal === null) ? ($def[$k] ?? '') : $cfgVal;

// data-defecto para el botón «Restaurar valores por defecto» (reset del formulario en el cliente).
$dd  = static fn (string $k): string => ' data-defecto="' . esc((string) ($def[$k] ?? ''), 'attr') . '"';
$ddb = static fn (string $k): string => ' data-defecto="' . (empty($def[$k]) ? '0' : '1') . '"';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <title>Impresora · Fruver</title>
    <style>
        .cfg-form { max-width: 820px; }
        .cfg-fieldset { border: 1px solid var(--linea, #d9e2dc); border-radius: 12px; padding: 16px 18px; margin: 0 0 18px; }
        .cfg-fieldset > legend { font-weight: 700; padding: 0 8px; }
        .cfg-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 18px; }
        .cfg-campo { display: flex; flex-direction: column; gap: 4px; }
        .cfg-campo label { font-size: 13px; font-weight: 600; }
        .cfg-campo .ayuda { font-size: 12px; opacity: .7; }
        .cfg-check { flex-direction: row; align-items: center; gap: 8px; }
        .cfg-check label { font-weight: 500; }
        .cfg-ancho-total { grid-column: 1 / -1; }
        @media (max-width: 640px) { .cfg-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="lista-wrapper">

    <h2 style="padding-top:20px;"><i class="fa-solid fa-print"></i> Impresora de tickets</h2>

    <div class="flash-container">
        <?php if (session()->getFlashdata('error')): ?>
            <div class="w3-panel w3-red w3-animate-opacity"><p><?= esc(session()->getFlashdata('error')) ?></p></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('mensaje')): ?>
            <div class="w3-panel w3-green w3-animate-opacity"><p><?= esc(session()->getFlashdata('mensaje')) ?></p></div>
        <?php endif; ?>
    </div>

    <?php if ($navegador): ?>
        <div class="w3-panel w3-pale-yellow w3-leftbar w3-border-yellow">
            <p>
                Modo <b>navegador</b>: los tickets se abren en una vista de 58/80&nbsp;mm para imprimir con el
                controlador normal de la impresora.
                <?php if ($tecnico): ?>
                    Para imprimir directo en la térmica (sin diálogo), cambia la <b>conexión</b> abajo.
                <?php else: ?>
                    Para imprimir directo en la térmica (sin diálogo), pide al administrador del sistema que la configure.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <form class="cfg-form" method="post" action="<?= base_url('impresora/guardar') ?>">
        <?= csrf_field() ?>

        <fieldset class="cfg-fieldset">
            <legend><i class="fa-solid fa-store" aria-hidden="true"></i> Datos del negocio y del ticket</legend>
            <div class="cfg-grid">
                <div class="cfg-campo">
                    <label for="negocioNombre">Nombre del negocio</label>
                    <input class="w3-input w3-border" type="text" id="negocioNombre" name="negocioNombre" maxlength="40" value="<?= $t('negocioNombre', $cfg->negocioNombre) ?>"<?= $dd('negocioNombre') ?>>
                </div>
                <div class="cfg-campo">
                    <label for="negocioSlogan">Slogan</label>
                    <input class="w3-input w3-border" type="text" id="negocioSlogan" name="negocioSlogan" maxlength="40" value="<?= $t('negocioSlogan', $cfg->negocioSlogan) ?>"<?= $dd('negocioSlogan') ?>>
                </div>
                <div class="cfg-campo">
                    <label for="negocioDireccion">Dirección</label>
                    <input class="w3-input w3-border" type="text" id="negocioDireccion" name="negocioDireccion" maxlength="60" value="<?= $t('negocioDireccion', $cfg->negocioDireccion) ?>"<?= $dd('negocioDireccion') ?> placeholder="Calle, número, colonia">
                </div>
                <div class="cfg-campo">
                    <label for="negocioTelefono">Teléfono</label>
                    <input class="w3-input w3-border" type="text" id="negocioTelefono" name="negocioTelefono" maxlength="20" value="<?= $t('negocioTelefono', $cfg->negocioTelefono) ?>"<?= $dd('negocioTelefono') ?> placeholder="222 123 4567">
                </div>
                <div class="cfg-campo cfg-ancho-total">
                    <label for="mensajePie">Mensaje de pie</label>
                    <input class="w3-input w3-border" type="text" id="mensajePie" name="mensajePie" maxlength="60" value="<?= $t('mensajePie', $cfg->mensajePie) ?>"<?= $dd('mensajePie') ?>>
                </div>
                <div class="cfg-campo">
                    <label for="simboloMoneda">Símbolo de moneda</label>
                    <input class="w3-input w3-border" type="text" id="simboloMoneda" name="simboloMoneda" maxlength="3" value="<?= $t('simboloMoneda', $cfg->simboloMoneda) ?>"<?= $dd('simboloMoneda') ?>>
                </div>
            </div>
        </fieldset>

        <fieldset class="cfg-fieldset">
            <legend><i class="fa-solid fa-receipt" aria-hidden="true"></i> Formato del papel</legend>
            <div class="cfg-grid">
                <div class="cfg-campo">
                    <label for="papelMm">Ancho del papel</label>
                    <select class="w3-select w3-border" id="papelMm" name="papelMm"<?= $dd('papelMm') ?>>
                        <option value="80"<?= $sel('papelMm', $cfg->papelMm, 80) ?>>80 mm</option>
                        <option value="58"<?= $sel('papelMm', $cfg->papelMm, 58) ?>>58 mm</option>
                    </select>
                </div>
                <div class="cfg-campo">
                    <label for="columnas">Columnas</label>
                    <input class="w3-input w3-border" type="number" id="columnas" name="columnas" min="0" max="80" value="<?= $t('columnas', $cfg->columnas) ?>"<?= $dd('columnas') ?>>
                    <span class="ayuda">0 = automático (58 mm → 32, 80 mm → 48). Algunas de 80 mm usan 42.</span>
                </div>
                <div class="cfg-campo">
                    <label for="codificacion">Codificación (acentos y ñ)</label>
                    <select class="w3-select w3-border" id="codificacion" name="codificacion"<?= $dd('codificacion') ?>>
                        <?php foreach (AjustesImpresora::CODIFICACIONES as $op): ?>
                            <option value="<?= esc($op, 'attr') ?>"<?= $sel('codificacion', $cfg->codificacion, $op) ?>><?= esc($op) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="ayuda">cp858 recomendada. ascii quita acentos (universal).</span>
                </div>
                <div class="cfg-campo">
                    <label for="corte">Corte del papel</label>
                    <select class="w3-select w3-border" id="corte" name="corte"<?= $dd('corte') ?>>
                        <?php foreach (AjustesImpresora::CORTES as $op): ?>
                            <option value="<?= esc($op, 'attr') ?>"<?= $sel('corte', $cfg->corte, $op) ?>><?= esc(ucfirst($op)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cfg-campo">
                    <label for="copias">Copias por impresión</label>
                    <input class="w3-input w3-border" type="number" id="copias" name="copias" min="1" max="5" value="<?= $t('copias', $cfg->copias) ?>"<?= $dd('copias') ?>>
                </div>
                <div class="cfg-campo">
                    <label for="lineasFinales">Líneas en blanco antes de cortar</label>
                    <input class="w3-input w3-border" type="number" id="lineasFinales" name="lineasFinales" min="0" max="10" value="<?= $t('lineasFinales', $cfg->lineasFinales) ?>"<?= $dd('lineasFinales') ?>>
                </div>
                <div class="cfg-campo cfg-check">
                    <input type="checkbox" id="cancelarKanji" name="cancelarKanji" value="1"<?= $chk($cfg->cancelarKanji) . $ddb('cancelarKanji') ?>>
                    <label for="cancelarKanji">Cancelar Kanji (si salen símbolos raros)</label>
                </div>
                <div class="cfg-campo cfg-check">
                    <input type="checkbox" id="abrirCajon" name="abrirCajon" value="1"<?= $chk($cfg->abrirCajon) . $ddb('abrirCajon') ?>>
                    <label for="abrirCajon">Abrir cajón de dinero al imprimir</label>
                </div>
            </div>
        </fieldset>

        <?php if ($tecnico): /* Conexión/hardware: solo superadmin/developer. */ ?>
        <fieldset class="cfg-fieldset">
            <legend><i class="fa-solid fa-plug" aria-hidden="true"></i> Conexión de la impresora</legend>
            <div class="cfg-grid">
                <div class="cfg-campo">
                    <label for="conexion">Tipo de conexión</label>
                    <select class="w3-select w3-border" id="conexion" name="conexion"<?= $dd('conexion') ?>>
                        <?php
                        $etiquetasConexion = [
                            'navegador' => 'Navegador (vista imprimible)',
                            'red'       => 'Red / WiFi (TCP 9100)',
                            'windows'   => 'Windows (impresora compartida)',
                            'puerto'    => 'Puerto (COM / LPT / /dev)',
                            'cups'      => 'CUPS (Linux/macOS)',
                            'archivo'   => 'Archivo (solo pruebas)',
                        ];
                        foreach (AjustesImpresora::CONEXIONES as $op): ?>
                            <option value="<?= esc($op, 'attr') ?>"<?= $sel('conexion', $cfg->conexion, $op) ?>><?= esc($etiquetasConexion[$op] ?? $op) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="ayuda">Con «navegador» no se imprime directo: se abre la vista de ticket.</span>
                </div>
                <div class="cfg-campo">
                    <label for="host">Host / IP <span class="ayuda">(red y windows)</span></label>
                    <input class="w3-input w3-border" type="text" id="host" name="host" maxlength="120" value="<?= $t('host', $pref('host', $cfg->host)) ?>"<?= $dd('host') ?> placeholder="192.168.1.50">
                </div>
                <div class="cfg-campo">
                    <label for="puertoRed">Puerto de red <span class="ayuda">(red)</span></label>
                    <input class="w3-input w3-border" type="number" id="puertoRed" name="puertoRed" min="1" max="65535" value="<?= $t('puertoRed', $cfg->puertoRed) ?>"<?= $dd('puertoRed') ?>>
                </div>
                <div class="cfg-campo">
                    <label for="nombreCompartido">Nombre compartido / cola <span class="ayuda">(windows y cups)</span></label>
                    <input class="w3-input w3-border" type="text" id="nombreCompartido" name="nombreCompartido" maxlength="120" value="<?= $t('nombreCompartido', $pref('nombreCompartido', $cfg->nombreCompartido)) ?>"<?= $dd('nombreCompartido') ?> placeholder="POS80">
                </div>
                <div class="cfg-campo">
                    <label for="puerto">Puerto serie/USB <span class="ayuda">(puerto)</span></label>
                    <input class="w3-input w3-border" type="text" id="puerto" name="puerto" maxlength="40" value="<?= $t('puerto', $pref('puerto', $cfg->puerto)) ?>"<?= $dd('puerto') ?> placeholder="COM5 · /dev/usb/lp0">
                </div>
                <div class="cfg-campo">
                    <label for="baudios">Baudios <span class="ayuda">(puerto COM/serie)</span></label>
                    <input class="w3-input w3-border" type="number" id="baudios" name="baudios" min="1200" max="115200" value="<?= $t('baudios', $cfg->baudios) ?>"<?= $dd('baudios') ?>>
                </div>
                <div class="cfg-campo">
                    <label for="timeoutSegundos">Tiempo de espera (segundos)</label>
                    <input class="w3-input w3-border" type="number" id="timeoutSegundos" name="timeoutSegundos" min="1" max="30" value="<?= $t('timeoutSegundos', $cfg->timeoutSegundos) ?>"<?= $dd('timeoutSegundos') ?>>
                </div>
                <div class="cfg-campo cfg-check">
                    <input type="checkbox" id="consultarEstado" name="consultarEstado" value="1"<?= $chk($cfg->consultarEstado) . $ddb('consultarEstado') ?>>
                    <label for="consultarEstado">Consultar estado antes de imprimir (sin papel / tapa abierta, solo red)</label>
                </div>
            </div>
        </fieldset>
        <?php endif; ?>

        <div style="margin:4px 0 6px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <button type="submit" class="btn-guardar"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar configuración</button>
            <button type="button" id="btnRestaurar" class="w3-button w3-border w3-round"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restaurar valores por defecto</button>
            <a class="btn-cancelar" href="<?= base_url('lista_p_pedido') ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver a pedidos</a>
        </div>
        <p class="ayuda" style="font-size:12px;opacity:.7;margin:0 0 24px;">«Restaurar» solo rellena el formulario con los valores estándar; se aplican al pulsar <b>Guardar configuración</b>.</p>
    </form>

    <div style="margin:0 0 20px;">
        <button type="button" class="w3-button w3-blue" data-url="<?= base_url('impresora/conexion') ?>"
                onclick="ticketAccion(this)" <?= $navegador ? 'disabled' : '' ?>>
            <i class="fa-solid fa-plug"></i> Probar conexión
        </button>
        <button type="button" class="w3-button w3-green" data-url="<?= base_url('impresora/prueba') ?>"
                onclick="ticketAccion(this)" <?= $navegador ? 'disabled' : '' ?>>
            <i class="fa-solid fa-print"></i> Imprimir hoja de prueba
        </button>
        <span class="ayuda" style="font-size:12px;opacity:.7;">Guarda primero los cambios: las pruebas usan la configuración guardada.</span>
    </div>

    <div class="w3-panel w3-light-grey">
        <p><b>Si los acentos o la ñ salen mal en la hoja de prueba:</b></p>
        <?php if ($tecnico): ?>
        <ul>
            <li>Prueba la codificación <code>cp850</code> o <code>cp437</code> arriba.</li>
            <li>Si salen caracteres chinos o símbolos raros, activa <b>Cancelar Kanji</b>.</li>
            <li>Si nada funciona, usa <code>ascii</code> (imprime sin acentos en cualquier impresora).</li>
        </ul>
        <?php else: ?>
        <p>Guarda la hoja de prueba y avisa al administrador del sistema para que ajuste la impresora.</p>
        <?php endif; ?>
    </div>

</div>

<script src="<?= base_url('js/impresion_ticket.js') ?>"></script>
<script>
// «Restaurar valores por defecto»: repone cada campo del formulario a su valor estándar
// (atributo data-defecto). No guarda nada: la persona revisa y pulsa «Guardar configuración».
(function () {
    'use strict';
    var boton = document.getElementById('btnRestaurar');
    var form  = document.querySelector('.cfg-form');
    if (!boton || !form) { return; }

    boton.addEventListener('click', function () {
        if (!window.confirm('¿Restaurar todos los campos a los valores por defecto? Se perderán los cambios sin guardar.')) {
            return;
        }
        form.querySelectorAll('[data-defecto]').forEach(function (campo) {
            var def = campo.getAttribute('data-defecto');
            if (campo.type === 'checkbox') {
                campo.checked = (def === '1');
            } else {
                campo.value = def;
            }
        });
        if (form.querySelector('[data-defecto]')) {
            form.querySelector('[data-defecto]').focus();
        }
    });
})();
</script>
</body>
</html>
