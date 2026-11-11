<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clientes</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
</head>
<body class="w3-light-grey">

<!-- ✅ Mensajes de error y éxito -->
<?php if (session()->getFlashdata('error')): ?>
    <div class="w3-panel w3-red w3-animate-opacity">
        <p><?= esc(session()->getFlashdata('error')) ?></p>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('mensaje')): ?>
    <div class="w3-panel w3-green w3-animate-opacity">
        <p><?= esc(session()->getFlashdata('mensaje')) ?></p>
    </div>
<?php endif; ?>

<div class="w3-container w3-padding-32">
    <button onclick="document.getElementById('modalCliente').style.display='block'"
            class="w3-button w3-green">
        + Nuevo Cliente
    </button>
</div>

<div id="modalCliente" class="w3-modal">
    <div class="w3-modal-content">

        <header class="w3-container w3-green">
            <span onclick="document.getElementById('modalCliente').style.display='none'"
                  class="w3-button w3-display-topright">&times;</span>
            <h2>Registrar Cliente</h2>
        </header>

        <!-- ✅ Error dentro del modal también -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="w3-panel w3-red w3-margin">
                <p><?= esc(session()->getFlashdata('error')) ?></p>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('guarda_cliente') ?>" method="post" class="w3-container w3-padding-16">
<?= csrf_field() ?>

            <label><b>Nombre</b></label>
            <input type="text" name="nom" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Apellido Paterno</b></label>
            <input type="text" name="ape_pat" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Apellido Materno</b></label>
            <input type="text" name="ape_mat" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Teléfono</b></label>
            <input type="text" name="tel" class="w3-input w3-border w3-margin-bottom" required>

            <!-- LFPDPPP: quien da sus datos en persona o por teléfono también debe conocer el aviso. -->
            <p class="w3-small w3-text-grey">
                Antes de guardar, informa al cliente que sus datos se tratan conforme al
                <a href="<?= base_url('aviso-de-privacidad') ?>" target="_blank" rel="noopener">Aviso de Privacidad</a>
                (solo para registrar y entregar sus pedidos). Las promociones, recordatorios y encuestas requieren
                que el cliente las autorice desde su propia cuenta.
            </p>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalCliente').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>

<script>
    window.onclick = function(event) {
        const modal = document.getElementById('modalCliente');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };

    // ✅ Si hay error, abre el modal automáticamente
    <?php if (session()->getFlashdata('error')): ?>
        document.getElementById('modalCliente').style.display = 'block';
    <?php endif; ?>
</script>

</body>
</html>