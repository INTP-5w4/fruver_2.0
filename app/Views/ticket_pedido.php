<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/ticket.css') ?>">
    <title>Ticket del pedido #<?= (int) $ticket['id'] ?></title>
</head>
<body>
    <?php include 'Header.php'; ?>
    <?php include 'sidebar.php'; ?>

<?php $hayProductos = $ticket['items'] !== []; ?>

<div class="lista-wrapper">

<div class="flash-container">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="w3-panel w3-red w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('error')) ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="ticket-barra">
    <h2 class="ticket-titulo">Ticket del pedido #<?= (int) $ticket['id'] ?></h2>
    <a href="<?= base_url('lista_p_pedido') ?>" class="w3-button w3-border">
        <i class="fa-solid fa-arrow-left"></i> Volver a pedidos
    </a>
</div>

<section class="ticket-card">

    <dl class="ticket-datos">
        <div><dt>Cliente</dt><dd><?= esc($ticket['cliente']) ?></dd></div>
        <div><dt>Teléfono</dt><dd><?= esc($ticket['telefono'] ?: '—') ?></dd></div>
        <div><dt>Fecha</dt><dd><?= esc($ticket['fecha'] ?? '—') ?></dd></div>
        <div><dt>Estado</dt><dd><span class="w3-tag w3-round w3-blue-gray"><?= esc(str_replace('_', ' ', $ticket['estado'])) ?></span></dd></div>
        <div><dt>Productos</dt><dd><?= count($ticket['items']) ?></dd></div>
        <div><dt>Total</dt><dd><b>$<?= esc(number_format($ticket['total'], 2)) ?></b></dd></div>
    </dl>

    <?php if (! $telefonoValido): ?>
        <div class="w3-panel w3-pale-yellow w3-border">
            <p>
                <b>El teléfono del cliente no es válido para WhatsApp.</b>
                Puedes enviar el mensaje igualmente y elegir el contacto en WhatsApp, o corregir el teléfono en
                <a href="<?= base_url('lista_cliente') ?>">Clientes</a>.
            </p>
        </div>
    <?php endif; ?>

    <?php if (! $hayProductos): ?>
        <div class="w3-panel w3-pale-red w3-border">
            <p><b>Este pedido no tiene productos.</b> No hay nada que enviar.</p>
        </div>
    <?php endif; ?>

    <label for="ticket-mensaje"><b>Mensaje que se enviará</b></label>
    <textarea id="ticket-mensaje" class="ticket-mensaje" readonly
              rows="<?= min(24, substr_count($mensaje, "\n") + 2) ?>"><?= esc($mensaje) ?></textarea>

    <div class="ticket-acciones">
        <?php if ($hayProductos): ?>
            <a href="<?= htmlspecialchars($urlWhatsapp, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn-whatsapp">
                <i class="fa-brands fa-whatsapp"></i>
                <?= $telefonoValido ? 'Enviar por WhatsApp' : 'Enviar por WhatsApp (elegir contacto)' ?>
            </a>
        <?php else: ?>
            <span class="btn-whatsapp btn-deshabilitado" aria-disabled="true">
                <i class="fa-brands fa-whatsapp"></i> Enviar por WhatsApp
            </span>
        <?php endif; ?>

        <button type="button" id="btn-copiar" class="w3-button w3-border">
            <i class="fa-regular fa-copy"></i> Copiar mensaje
        </button>
        <span id="copiado-aviso" class="ticket-copiado" role="status" aria-live="polite"></span>
    </div>

    <p class="ticket-nota">
        Se abrirá WhatsApp con el chat del cliente y el mensaje ya escrito; solo falta presionar «Enviar».
    </p>
</section>

</div>

<?php include 'Footer.php'; ?>

<script src="<?= base_url('js/ticket.js') ?>"></script>
</body>
</html>
