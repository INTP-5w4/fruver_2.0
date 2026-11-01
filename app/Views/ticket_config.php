<?php helper('security'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <title>Impresora de tickets</title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="lista-wrapper">

    <h2 style="padding-top:20px;"><i class="fa-solid fa-print"></i> Impresora de tickets</h2>

    <?php if ($navegador): ?>
        <div class="w3-panel w3-pale-yellow w3-leftbar w3-border-yellow">
            <p>
                Modo <b>navegador</b>: los tickets se abren en una vista de 58/80&nbsp;mm para imprimir con el
                controlador normal de la impresora. Para imprimir directo en la térmica (sin diálogo), configura
                <code>impresora.conexion</code> en el archivo <code>.env</code>.
            </p>
        </div>
    <?php endif; ?>

    <table class="w3-table w3-bordered" style="max-width:640px;">
        <tbody>
        <?php foreach ($resumen as $etiqueta => $valor): ?>
            <tr>
                <td style="width:45%;"><b><?= esc($etiqueta) ?></b></td>
                <td><?= esc($valor !== '' ? $valor : '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin:20px 0;">
        <button type="button" class="w3-button w3-blue" data-url="<?= base_url('impresora/conexion') ?>"
                onclick="ticketAccion(this)" <?= $navegador ? 'disabled' : '' ?>>
            <i class="fa-solid fa-plug"></i> Probar conexión
        </button>
        <button type="button" class="w3-button w3-green" data-url="<?= base_url('impresora/prueba') ?>"
                onclick="ticketAccion(this)" <?= $navegador ? 'disabled' : '' ?>>
            <i class="fa-solid fa-print"></i> Imprimir hoja de prueba
        </button>
        <a class="w3-button w3-white w3-border" href="<?= base_url('lista_p_pedido') ?>">Volver a pedidos</a>
    </div>

    <div class="w3-panel w3-light-grey">
        <p><b>Si los acentos o la ñ salen mal en la hoja de prueba:</b></p>
        <ul>
            <li>Prueba <code>impresora.codificacion = cp850</code> o <code>cp437</code> en el <code>.env</code>.</li>
            <li>Si salen caracteres chinos o símbolos raros, activa <code>impresora.cancelarKanji = true</code>.</li>
            <li>Si nada funciona, usa <code>impresora.codificacion = ascii</code> (imprime sin acentos en cualquier impresora).</li>
        </ul>
    </div>

</div>

<script src="<?= base_url('js/impresion_ticket.js') ?>"></script>
</body>
</html>
